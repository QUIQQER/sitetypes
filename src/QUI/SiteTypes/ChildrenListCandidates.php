<?php

namespace QUI\SiteTypes;

use Doctrine\DBAL\ArrayParameterType;
use QUI;
use QUI\Interfaces\Projects\Site;
use QUI\Permissions\Manager;
use QUI\Projects\Project;
use QUI\Projects\Site\PermissionDenied;
use QUI\Tags\Manager as TagManager;

/**
 * Read the data needed for filtering without constructing every page.
 */
class ChildrenListCandidates
{
    public const BATCH_SIZE = 500;

    public function __construct(
        private readonly Project $Project,
        private readonly ?ChildrenListViewPermission $Permissions = null
    ) {
    }

    /**
     * @param array<string, mixed> $where
     * @param array<array-key, mixed>|string|false $input
     * @return list<int>
     */
    public function getIds(Site $Site, array $where, array | string | false $input, string $byType, string $order): array
    {
        if ($input) {
            $rows = $this->getInputListIds($input, $where, $order);
        } elseif ($byType !== '') {
            $where['type'] = $byType;
            $rows = $this->Project->getSitesIds([
                'where' => $where,
                'order' => 'release_from DESC'
            ]);
        } else {
            $ids = $Site->getChildrenIds(['where' => $where]);

            return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
        }

        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Return visible, matching IDs in display order. A source maximum applies before filtering.
     *
     * @param list<int> $ids
     * @param string[] $pinAttributes
     * @return list<int>
     */
    public function getMatchingIds(
        array $ids,
        ChildrenListFilter $Filter,
        ?TagManager $Tags,
        int $max,
        array $pinAttributes,
        string $pinOrder,
        bool $ascending
    ): array {
        $tagged = $Tags && $Filter->tag !== '' ? $Tags->getSiteIdsFromTags([$Filter->tag]) : null;

        if ($max === 0 && $tagged !== null) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => isset($tagged[$id])));
        }

        if ($ids === []) {
            return [];
        }

        $Permissions = $this->Permissions ?? new ChildrenListViewPermission();
        $pinned = [];
        $normal = [];
        $excluded = [];
        $matchesByTag = null;
        $search = $Filter->query !== '';

        foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
            $rows = $this->read($batch, $search, $pinAttributes !== [], $pinOrder);

            foreach ($batch as $id) {
                $row = $rows[$id] ?? null;
                if ($row === null || !$Permissions->allows(isset($row['permission_id']), $row['permission_value'])) {
                    continue;
                }

                $matches = $tagged === null || isset($tagged[$id]);
                if (
                    $matches && $search
                    && !$Filter->matchesSearchText((string)$row['title'], (string)$row['short'])
                    && $Tags
                ) {
                    $matchesByTag ??= $Filter->getSearchTagSiteIds($Tags);
                    $matches = isset($matchesByTag[$id]);
                }

                if (!$matches) {
                    if ($max === 0) {
                        continue;
                    }
                    // A nonmatching entry still occupies its place in the limited brick.
                    $excluded[$id] = true;
                }

                if ($pinAttributes !== [] && self::isCandidatePinned((string)$row['extra'], $pinAttributes)) {
                    $pinned[$id] = $row['pin_order'] ?? null;
                } else {
                    $normal[$id] = $row['pin_order'] ?? null;
                }

                if ($max > 0 && $pinAttributes === [] && count($normal) >= $max) {
                    break 2;
                }
            }
        }

        if ($pinOrder !== '') {
            if ($ascending) {
                asort($pinned);
                asort($normal);
            } else {
                arsort($pinned);
                arsort($normal);
            }
        }

        $ordered = $pinned + $normal;
        if ($max > 0) {
            $ordered = array_slice($ordered, 0, $max, true);
        }

        return array_keys(array_diff_key($ordered, $excluded));
    }

    /**
     * @param list<int> $matched
     * @return array{array<int, QUI\Projects\Site>, int, int, int}
     */
    public function loadPage(
        array $matched,
        ChildrenListFilter $Filter,
        int $limit,
        bool $paging,
        ?Site $Parent
    ): array {
        [$sheets, $start, $page, $length] = $Filter->getPageBounds(count($matched), $limit, $paging);
        $children = [];

        foreach (array_slice($matched, $start, $length) as $id) {
            try {
                $Child = $this->Project->get($id);
                if (!$Child instanceof PermissionDenied) {
                    if ($Parent !== null) {
                        $Child->setAttribute('parent', $Parent);
                    }
                    $children[] = $Child;
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        return [$children, $sheets, $start, $page];
    }

    /**
     * @param string[] $attributes
     */
    private static function isCandidatePinned(string $extra, array $attributes): bool
    {
        $data = json_decode($extra, true);

        foreach ($attributes as $attribute) {
            if (!empty($data[$attribute])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match Site\Utils::getSitesByInputList() selector semantics and ordering.
     *
     * @param array<array-key, mixed>|string $input
     * @param array<string, mixed> $where
     * @return array<int, array<string, mixed>>
     */
    private function getInputListIds(array | string $input, array $where, string $order): array
    {
        $entries = is_string($input) ? explode(';', $input) : $input;
        $ids = [];
        $parents = [];
        $types = [];

        foreach ($entries as $entry) {
            if (is_numeric($entry)) {
                $ids[] = (int)$entry;
            } elseif (is_string($entry)) {
                if (str_starts_with($entry, 'p') && !str_contains($entry, '/') && !str_contains($entry, ':')) {
                    $parents[] = (int)str_replace('p', '', $entry);
                } else {
                    $types[] = $entry;
                }
            }
        }

        foreach ($parents as $parentId) {
            try {
                $Parent = $this->Project->get($parentId);
                $children = $Parent->getChildrenIds(['order' => $order]);
                if (is_array($children)) {
                    $ids = array_merge($ids, $children);
                }
            } catch (QUI\Exception) {
                // Keep valid sources if a selected parent is no longer available.
            }
        }

        if ($parents !== [] && $ids === []) {
            return [];
        }

        $selection = [];

        if ($ids !== []) {
            $selection['id'] = [
                'type' => 'IN',
                'value' => $ids
            ];
        }

        if ($types !== []) {
            $selection['type'] = [
                'type' => 'IN',
                'value' => $types
            ];
        }

        if ($parents === [] && count($selection) > 1) {
            return $this->Project->getSitesIds([
                'where' => $where,
                'where_or' => $selection,
                'order' => $order
            ]);
        }

        return $this->Project->getSitesIds([
            'where' => array_merge($where, $selection),
            'order' => $order
        ]);
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function read(array $ids, bool $search, bool $pinned, string $pinOrder): array
    {
        $Connection = QUI::getDataBaseConnection();
        $quote = $Connection->getDatabasePlatform()->quoteSingleIdentifier(...);
        $id = $quote('id');
        $Query = $Connection->createQueryBuilder()
            ->select(
                'site.' . $id,
                'permission.' . $id . ' AS ' . $quote('permission_id'),
                'permission.' . $quote('value') . ' AS ' . $quote('permission_value')
            )
            ->from($quote($this->Project->table()), 'site')
            ->leftJoin(
                'site',
                $quote(Manager::table() . '2sites'),
                'permission',
                'permission.' . $id . ' = site.' . $id
                . ' AND permission.' . $quote('project') . ' = :project'
                . ' AND permission.' . $quote('lang') . ' = :language'
                . ' AND permission.' . $quote('permission') . ' = :viewPermission'
            )
            ->where('site.' . $id . ' IN (:ids)')
            ->andWhere('site.' . $quote('active') . ' = 1')
            ->andWhere('site.' . $quote('deleted') . ' = 0')
            ->setParameter('project', $this->Project->getName())
            ->setParameter('language', $this->Project->getLang())
            ->setParameter('viewPermission', ChildrenListViewPermission::SITE_VIEW)
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER);

        if ($search) {
            $Query->addSelect('site.' . $quote('title'), 'site.' . $quote('short'));
        }

        if ($pinned) {
            $Query->addSelect('site.' . $quote('extra'));
        }

        if ($pinOrder !== '') {
            $Query->addSelect('site.' . $quote($pinOrder) . ' AS ' . $quote('pin_order'));
        }

        return $Query->executeQuery()->fetchAllAssociativeIndexed();
    }
}
