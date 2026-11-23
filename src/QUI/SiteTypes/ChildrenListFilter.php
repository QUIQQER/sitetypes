<?php

namespace QUI\SiteTypes;

use QUI\Tags\Manager;

/**
 * Filter and pagination state for one list on the current page.
 */
final class ChildrenListFilter
{
    public const MAIN_FILTER_PREFIX = 'list-';

    public readonly string $filterPrefix;
    public readonly string $query;
    public readonly string $tag;
    public readonly int $page;

    /**
     * @param array<array-key, mixed> $params
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $prefix,
        private readonly array $params,
        bool $allowInput,
        array $tags,
        private readonly bool $legacyPage,
        private readonly ?string $mainListUrl = null
    ) {
        $this->filterPrefix = $mainListUrl !== null ? self::MAIN_FILTER_PREFIX : $prefix;
        $query = $params[$this->filterPrefix . 'q'] ?? '';
        $tag = $params[$this->filterPrefix . 'tag'] ?? '';
        // Older links may contain both parameters; their list-specific page took precedence.
        $page = $params[$prefix . 'page'] ?? (($mainListUrl !== null || $legacyPage) ? ($params['sheet'] ?? 1) : 1);

        $this->query = $allowInput && is_string($query) ? trim($query) : '';
        $this->tag = is_string($tag) && in_array($tag, $tags, true) ? $tag : '';
        $this->page = is_scalar($page) && ctype_digit((string)$page) ? max(1, (int)$page) : 1;
    }

    public function hasFilters(): bool
    {
        return $this->query !== '' || $this->tag !== '';
    }

    /**
     * @return array{int, int, int, int}
     */
    public function getPageBounds(
        int $count,
        int $limit,
        bool $paging
    ): array {
        $sheets = $paging ? (int)ceil($count / $limit) : 0;
        $page = max(1, min($sheets, $this->page));
        $start = ($page - 1) * $limit;
        $length = $paging || !$this->hasFilters() ? min($count - $start, $limit) : $count;

        return [$sheets, $start, $page, $length];
    }

    public function matchesSearchText(string $title, string $short): bool
    {
        return mb_stripos($title, $this->query, 0, 'UTF-8') !== false
            || mb_stripos($short, $this->query, 0, 'UTF-8') !== false;
    }

    /**
     * @return array<int, true>
     */
    public function getSearchTagSiteIds(Manager $TagManager): array
    {
        $matchingTags = [];
        foreach ($TagManager->getList(['search' => $this->query, 'page' => 0]) as $tagData) {
            $tag = $tagData['tag'] ?? null;
            if (is_string($tag) && mb_stripos($tag, $this->query, 0, 'UTF-8') !== false) {
                $matchingTags[] = $tag;
            }
        }

        $ids = [];
        foreach ($TagManager->getTagSiteCacheData($matchingTags) as $row) {
            foreach (explode(',', (string)($row['sites'] ?? '')) as $id) {
                if ($id !== '') {
                    $ids[(int)$id] = true;
                }
            }
        }

        return $ids;
    }

    public function getUrl(string $tag, int $page): string
    {
        $query = http_build_query($this->getParams($tag, $page), '', '&', PHP_QUERY_RFC3986);

        return $query === '' ? ($this->mainListUrl ?? '?') : '?' . $query;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getParams(string $tag, int $page): array
    {
        $params = $this->params;
        unset($params[$this->filterPrefix . 'q'], $params[$this->filterPrefix . 'tag'], $params[$this->prefix . 'page']);

        if ($this->query !== '') {
            $params[$this->filterPrefix . 'q'] = $this->query;
        }

        if ($tag !== '') {
            $params[$this->filterPrefix . 'tag'] = $tag;
        }

        if ($this->mainListUrl !== null) {
            unset($params['sheet'], $params[$this->prefix . 'q'], $params[$this->prefix . 'tag']);
            if ($page > 1) {
                $params['sheet'] = $page;
            }
        } elseif ($page > 1 || ($this->legacyPage && isset($params['sheet']))) {
            $params[$this->prefix . 'page'] = $page;
        }

        return $params;
    }

    /**
     * Preserve query parameters, including other lists' state, in the GET form.
     *
     * @return list<array{name: string, value: string}>
     */
    public function getFormFields(): array
    {
        $params = $this->getParams($this->tag, 1);
        unset($params[$this->filterPrefix . 'q']);

        $fields = [];
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ($query === '') {
            return $fields;
        }

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = explode('=', $pair, 2);
            $fields[] = [
                'name' => rawurldecode($name),
                'value' => rawurldecode($value)
            ];
        }

        return $fields;
    }
}
