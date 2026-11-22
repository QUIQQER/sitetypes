<?php

/**
 * This file contains QUI\Controls\ChildrenList
 */

namespace QUI\Controls;

use Exception;
use QUI;
use QUI\Projects\Site\PermissionDenied;
use QUI\Projects\Site\Utils;
use QUI\SiteTypes\ChildrenListCandidates;
use QUI\SiteTypes\ChildrenListFilter;
use QUI\SiteTypes\ChildrenListRequest;

use function array_slice;
use function ceil;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function in_array;
use function is_array;
use function preg_match;
use function trim;
use function ucfirst;

/**
 * Class ChildrenList
 */
class ChildrenList extends QUI\Control
{
    /** @var array<int, QUI\Projects\Site> */
    protected array $renderedChildren = [];

    protected int $renderedStart = 0;

    /** @var array{q?: mixed, tag?: mixed, page?: mixed}|null */
    private ?array $fragmentState = null;

    private const INDEXED_PIN_ATTRIBUTES = [
        'quiqqer.settings.blog.pinned',
        'quiqqer.settings.news.pinned'
    ];

    private const INDEXED_PIN_ORDER = [
        'id', 'name', 'title', 'short', 'release_from', 'release_to', 'c_date', 'e_date', 'order_field'
    ];

    // sizes scale with the container's inline size (cqi), so they shrink in
    // narrow contexts (e.g. sidebar) and grow in the main content area. The
    // upper clamp bound keeps the main-area size, the lower bound the sidebar.
    private const TITLE_SIZE_PRESETS = [
        'extraSmall' => 'clamp(0.8rem, 0.72rem + 0.5cqi, 1rem)',
        'small' => 'clamp(0.9rem, 0.78rem + 0.8cqi, 1.15rem)',
        'normal' => 'clamp(0.95rem, 0.8rem + 1.1cqi, 1.2rem)',
        'large' => 'clamp(1.1rem, 0.85rem + 1.8cqi, 1.6rem)',
        'extraLarge' => 'clamp(1.25rem, 0.95rem + 2.5cqi, 2.1rem)',
    ];

    private const GAP_PRESETS = [
        'none' => 'clamp(0rem, 0cqi, 0rem)',
        'xs' => 'clamp(0.5rem, 0.45rem + 0.2cqi, 0.65rem)',
        's' => 'clamp(0.75rem, 0.7rem + 0.25cqi, 0.95rem)',
        'normal' => 'clamp(1rem, 0.9rem + 0.5cqi, 1.5rem)',
        'large' => 'clamp(1.5rem, 1.3rem + 0.8cqi, 2.25rem)',
        'extraLarge' => 'clamp(2rem, 1.7rem + 1cqi, 3rem)',
    ];

    // image column width of the media list; scales with the list's inline
    // size (cqi), so the image shrinks in narrow contexts (e.g. sidebar)
    private const MEDIA_IMAGE_WIDTH_PRESETS = [
        'small' => 'clamp(8rem, 25cqi, 14rem)',
        'normal' => 'clamp(11rem, 35cqi, 22rem)',
        'large' => 'clamp(14rem, 45cqi, 30rem)',
        'half' => '50%',
    ];

    /**
     * constructor
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        // default options
        $this->setAttributes([
            'class' => 'qui-control-list',
            'limit' => 2,
            'showSheets' => true,
            'showImages' => true,
            'showShort' => true,
            'showHeader' => true,
            'showContent' => false,
            'showDate' => false,
            'showTime' => false,
            'showCreator' => false,
            'Site' => false,
            'parentInputList' => false,
            // if true, returns all sites of a certain type
            'byType' => false,
            'where' => false,
            // Disable automatic JSON-LD output when the caller handles structured data.
            'ownJsonLd' => true,
            'itemtype' => 'https://schema.org/ItemList',
            'child-itemtype' => 'https://schema.org/ListItem',
            'child-itemprop' => 'itemListElement',
            // layout / design
            'display' => 'childrenlist',
            // Custom children template (path to an HTML file); overwrites "display"
            'displayTemplate' => false,
            // Custom children template CSS (path to CSS file); overwrites "display"
            'displayCss' => false,
            'nodeName' => 'section',
            // list of sites to display,
            'children' => false,
            // load all children of list site if the 'children' attribute is empty
            'loadAllChildrenOnEmptyList' => true,
            'fontColor' => '#fff', // relevant for some templates (e.g. bigBanner)

            // card template (display 'cards')
            'cardLayout' => 'standard',
            'cardColumns' => 3,
            // empty inherits the desktop column count
            'cardColumnsTablet' => '',
            'cardColumnsMobile' => 1,
            'cardImageFit' => 'cover',
            'cardAspectRatio' => '3/2',
            'cardGap' => 'normal',

            // media list template (display 'mediaList'); image fit, aspect
            // ratio and gap are shared with the card settings above
            'mediaImagePosition' => 'left', // 'left' / 'right' / 'alternate'
            'mediaImageWidth' => 'normal',

            // general, template independent (consumed by templates that opt in)
            'titleSize' => 'normal',
            'buttonSize' => 'normal',
            'buttonWidth' => 'normal',

            // tags
            'tags' => [],
            'filter' => 'disabled', // 'all' / 'input' / 'tags' / 'disabled'
            // Stable placement key and optional overall result cap for embedded lists.
            'filterKey' => '',
            'filterMax' => 0,
            'filterPlacement' => '',
            // max tag badges per entry (cards / mediaList); 0 hides them
            'tagsMax' => 3,
            // Site attribute name(s) used to mark pinned entries; false disables pin sorting
            'pinnedAttribute' => false,
            'pinnedOrder' => 'release_from DESC'
        ]);

        parent::__construct($attributes);

        $this->setAttribute('cacheable', 0);
    }

    /**
     * Return the inner body of the element
     * Can be overwritten
     *
     * @return String
     *
     * @throws QUI\Exception
     * @throws Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Site = $this->getSite();

        $this->applyCardCustomVariables();

        $Pagination = new QUI\Controls\Navigating\Pagination();
        $Pagination->setAttribute('Site', $Site);

        $limit = (int)$this->getAttribute('limit');
        $limit = $limit > 0 ? $limit : 6;
        $where = $this->getAttribute('where');
        $where = is_array($where) ? $where : [];
        $where['active'] = 1;
        $showFilter = $this->isFilterEnabled();
        $customTemplate = $this->getCustomTemplate();
        $useLegacyFilter = $customTemplate !== null && $this->hasFilterMode();

        $tags = [];
        $itemsData = [];
        $filterHtml = '';
        $filterState = null;
        $hasTextFilter = false;

        if ($showFilter || $useLegacyFilter) {
            $mode = $this->getAttribute('filter');
            $hasTextFilter = $mode === 'all' || $mode === 'input';

            if ($mode === 'all' || $mode === 'tags' || $useLegacyFilter) {
                $tags = $this->getTags();
            }

            $this->addCSSFile(__DIR__ . '/ChildrenList.Filter.css');
        }

        if ($showFilter) {
            $filterState = $this->getFilterState($Site, $hasTextFilter, array_column($tags, 'tag'));
            $this->setAttribute('data-name', 'filterableList');
            $this->setAttribute('data-list-prefix', $filterState->prefix);

            [$children, $sheets, $start, $filterPage] = $this->getFilteredPage($Site, $where, $limit, $filterState);
            $Pagination->setAttributes([
                'sheet' => $filterPage,
                'nodeName' => 'nav',
                'aria-label' => QUI::getLocale()->get('quiqqer/sitetypes', 'quiqqer.sitetypes.list.settings.showSheets')
            ]);
            $Pagination->setPageUrlBuilder(
                static fn (int $page): string => $filterState->getUrl($filterState->tag, $page)
            );
        } elseif ($useLegacyFilter) {
            $Pagination->loadFromRequest();

            // Keep the custom template contract and its on-page filter.
            $legacyState = new ChildrenListFilter('', ['sheet' => $_REQUEST['sheet'] ?? 1], false, [], true);
            [$children, $sheets, $start, $filterPage] = $this->getFilteredPage($Site, $where, $limit, $legacyState);
            $Pagination->setAttribute('sheet', $filterPage);

            foreach ($children as $Child) {
                $Child->load();
                $itemsData[] = [
                    'id' => $Child->getId(),
                    'title' => $Child->getAttribute('title'),
                    'description' => $Child->getAttribute('short'),
                    'tags' => $Child->getAttribute('quiqqer.tags.tagList')
                ];
            }
        } else {
            $Pagination->loadFromRequest();
            [$children, $sheets, $start] = $this->getUnfilteredPage($Site, $where, $limit);
        }

        $this->renderedChildren = $children;
        $this->renderedStart = $start;
        $Pagination->setAttribute('limit', $limit);
        $Pagination->setAttribute('sheets', $sheets);

        $Engine->assign([
            'this' => $this,
            'Site' => $this->getSite(),
            'Project' => $this->getProject(),
            // Filterable layouts use the persistent AJAX container rendered below.
            'sheets' => $showFilter ? 0 : $sheets,
            'children' => $children,
            'Pagination' => $Pagination,
            'MetaList' => new QUI\Controls\Utils\MetaList(),
            'Events' => $this->Events,
            'showFilter' => $showFilter || $useLegacyFilter,
            'tags' => $tags,
            'itemsData' => json_encode($itemsData, JSON_UNESCAPED_UNICODE),
            'filterPlacement' => $showFilter ? ChildrenListRequest::getPlacement(
                $this->getAttribute('filterPlacement')
            ) : '',
            'filterSiteId' => $Site->getId(),
            'filterState' => $filterState,
            'hasTextFilter' => $hasTextFilter,
            'cardTemplate' => dirname(__FILE__) . '/ChildrenList.Card.'
                . ucfirst($this->normalizeCardLayout($this->getAttribute('cardLayout'))) . '.html',
            'mediaImagePosition' => $this->normalizeMediaImagePosition(
                $this->getAttribute('mediaImagePosition')
            ),
            'childTags' => $this->getChildTagTitles($children),
            'childCreatorNames' => $this->getChildCreatorNames($children)
        ]);

        if ($showFilter) {
            $filterHtml = $Engine->fetch(__DIR__ . '/ChildrenList.FilterControls.html');
        } elseif ($useLegacyFilter) {
            $filterHtml = $Engine->fetch(__DIR__ . '/ChildrenList.Filter.html');
        }

        $Engine->assign(['filterHtml' => $filterHtml]);

        // load custom template (if set)
        if ($customTemplate !== null) {
            if (
                $this->getAttribute('displayCss')
                && file_exists($this->getAttribute('displayCss'))
            ) {
                $this->addCSSFile($this->getAttribute('displayCss'));
            }

            return $Engine->fetch($customTemplate);
        }

        $jsonLd = '';

        try {
            $jsonLd = $this->getListJsonLd($children, $start);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        switch ($this->getAttribute('display')) {
            // 'childrenList' is @deprecated (use 'mediaList' instead) but
            // stays the default fallback until a successor is decided (v3)
            default:
            case 'childrenList':
                $css = dirname(__FILE__) . '/ChildrenList.css';
                $template = dirname(__FILE__) . '/ChildrenList.html';
                break;

            // @deprecated use 'mediaList' instead; removal planned for v3
            case 'longFooter':
                $css = dirname(__FILE__) . '/ChildrenList.LongFooter.css';
                $template = dirname(__FILE__) . '/ChildrenList.LongFooter.html';
                break;

            // @deprecated use 'mediaList' instead; removal planned for v3
            case 'authorTop':
                $css = dirname(__FILE__) . '/ChildrenList.AuthorTop.css';
                $template = dirname(__FILE__) . '/ChildrenList.AuthorTop.html';
                break;

            // @deprecated use 'mediaList' instead; removal planned for v3
            case '1er':
                $css = dirname(__FILE__) . '/ChildrenList.1er.css';
                $template = dirname(__FILE__) . '/ChildrenList.1er.html';
                break;

            // @deprecated use 'cards' instead; removal planned for v3
            case '2er':
                $css = dirname(__FILE__) . '/ChildrenList.2er.css';
                $template = dirname(__FILE__) . '/ChildrenList.2er.html';
                break;

            // @deprecated use 'cards' instead; removal planned for v3
            case '3er':
                $css = dirname(__FILE__) . '/ChildrenList.3er.css';
                $template = dirname(__FILE__) . '/ChildrenList.3er.html';
                break;

            case '4er':
                $css = dirname(__FILE__) . '/ChildrenList.4er.css';
                $template = dirname(__FILE__) . '/ChildrenList.4er.html';
                break;

            case 'simpleArticleList':
                $css = dirname(__FILE__) . '/ChildrenList.SimpleArticleList.css';
                $template = dirname(__FILE__) . '/ChildrenList.SimpleArticleList.html';
                break;

            case 'advancedArticleList':
                $css = dirname(__FILE__) . '/ChildrenList.AdvancedArticleList.css';
                $template = dirname(__FILE__) . '/ChildrenList.AdvancedArticleList.html';
                break;

            // @deprecated use 'cards' instead; removal planned for v3
            case 'imageTopBorder':
                $css = dirname(__FILE__) . '/ChildrenList.ImageTopBorder.css';
                $template = dirname(__FILE__) . '/ChildrenList.ImageTopBorder.html';
                break;

            // @deprecated use 'cards' instead; removal planned for v3
            case 'imageTop':
                $css = dirname(__FILE__) . '/ChildrenList.ImageTop.css';
                $template = dirname(__FILE__) . '/ChildrenList.ImageTop.html';
                break;

            // @deprecated use 'mediaList' instead; removal planned for v3
            case 'cardRows':
                $css = dirname(__FILE__) . '/ChildrenList.CardRows.css';
                $template = dirname(__FILE__) . '/ChildrenList.CardRows.html';
                break;

            case 'featuredCards':
                $css = dirname(__FILE__) . '/ChildrenList.FeaturedCards.css';
                $template = dirname(__FILE__) . '/ChildrenList.FeaturedCards.html';
                break;

            case 'cards':
                $css = dirname(__FILE__) . '/ChildrenList.Cards.css';
                $template = dirname(__FILE__) . '/ChildrenList.Cards.html';
                break;

            case 'mediaList':
                $css = dirname(__FILE__) . '/ChildrenList.MediaList.css';
                $template = dirname(__FILE__) . '/ChildrenList.MediaList.html';
                break;

            case 'CSSGridCards':
                $css = dirname(__FILE__) . '/ChildrenList.CSSGridCards.css';
                $template = dirname(__FILE__) . '/ChildrenList.CSSGridCards.html';
                break;

            case 'gallery':
                $css = dirname(__FILE__) . '/ChildrenList.Gallery.css';
                $template = dirname(__FILE__) . '/ChildrenList.Gallery.html';
                break;

            case 'galleryOverlay':
                $css = dirname(__FILE__) . '/ChildrenList.GalleryOverlay.css';
                $template = dirname(__FILE__) . '/ChildrenList.GalleryOverlay.html';
                break;

            case 'bigBanner':
                $css = dirname(__FILE__) . '/ChildrenList.BigBanner.css';
                $template = dirname(__FILE__) . '/ChildrenList.BigBanner.html';
                break;
        }

        $this->addCSSFile(dirname(__FILE__) . '/ChildrenList.Base.css');
        $this->addCSSFile($css);

        $html = $Engine->fetch($template);
        if ($showFilter) {
            $html .= '<div data-name="pagination">';
            if ($sheets > 1) {
                $html .= $Pagination->create();
            }
            $html .= '</div>';
        }

        // Controls may render after the page head, so emit their JSON-LD here.
        return $html . $jsonLd;
    }

    public function isFilterEnabled(): bool
    {
        if (!$this->hasFilterMode() || $this->getCustomTemplate() !== null) {
            return false;
        }

        // The remaining values are layouts that never rendered the filter.
        return !in_array($this->getAttribute('display'), [
            'authorTop',
            '1er',
            '2er',
            '3er',
            '4er',
            'simpleArticleList',
            'advancedArticleList',
            'bigBanner'
        ], true);
    }

    /**
     * Render this one control with the current request's filter values.
     *
     * @param array{q?: mixed, tag?: mixed, page?: mixed} $state
     */
    public function renderFilterFragment(array $state): string
    {
        $Control = clone $this;
        $Control->fragmentState = $state;
        $Control->setAttributes([
            'content' => '',
            'showTitle' => false,
            'ownJsonLd' => false
        ]);

        return $Control->create();
    }

    private function hasFilterMode(): bool
    {
        return in_array($this->getAttribute('filter'), ['all', 'input', 'tags'], true);
    }

    private function getCustomTemplate(): ?string
    {
        $template = $this->getAttribute('displayTemplate');

        return is_string($template) && $template !== '' && file_exists($template) ? $template : null;
    }

    /**
     * @param string[] $tags
     */
    private function getFilterState(
        QUI\Interfaces\Projects\Site $Site,
        bool $hasTextFilter,
        array $tags
    ): ChildrenListFilter {
        $key = (string)$this->getAttribute('filterKey');
        $key = $key === '' ? 'site-' . $Site->getId() : $key;
        $prefix = 'list-' . $key . '-';
        $isMainList = $this->getAttribute('filterPlacement') === 'site';
        $params = $_GET;
        unset($params['_url']);

        if ($this->fragmentState !== null) {
            $filterPrefix = $isMainList ? ChildrenListFilter::MAIN_FILTER_PREFIX : $prefix;
            $params = [
                $filterPrefix . 'q' => $this->fragmentState['q'] ?? '',
                $filterPrefix . 'tag' => $this->fragmentState['tag'] ?? '',
                $prefix . 'page' => $this->fragmentState['page'] ?? 1
            ];
        }

        return new ChildrenListFilter(
            $prefix,
            $params,
            $hasTextFilter,
            $tags,
            str_starts_with($key, 'site-'),
            $isMainList ? $Site->getUrlRewritten() : null
        );
    }

    /**
     * Keep the request and source behavior for layouts without filtering.
     *
     * @param array<string, mixed> $where
     * @return array{array<int, QUI\Projects\Site>, int, int}
     */
    private function getUnfilteredPage(QUI\Interfaces\Projects\Site $Site, array $where, int $limit): array
    {
        $start = 0;
        $parents = $this->getAttribute('parentInputList');
        $Project = $this->getProject();
        $children = [];

        if (!$parents) {
            $parents = $Site->getId();
        }

        if ($this->getAttribute('showSheets')) {
            if (isset($_REQUEST['sheet'])) {
                $start = ((int)$_REQUEST['sheet'] - 1) * $limit;
            }
        }

        $loadAllChildrenOnEmptyList = $this->getAttribute('loadAllChildrenOnEmptyList');
        if ($this->supportsPinnedSorting()) {
            $children = $this->getPinnedChildren($Site, $where, 0, PHP_INT_MAX);
            $count_children = count($children);
        } elseif ($this->getAttribute('parentInputList')) {
            // for bricks
            $count_children = Utils::getSitesByInputList($Project, $parents, [
                'count' => 'count',
                'order' => $this->getAttribute('order')
            ]);
        } else {
            // for site types
            if ($this->getAttribute('byType')) {
                $count_children = $Project->getSitesIds([
                    'count' => 'count',
                    'where' => [
                        'type' => $this->getAttribute('byType')
                    ]
                ]);

                if (isset($count_children[0]['count'])) {
                    $count_children = $count_children[0]['count'];
                }
            } else {
                $count_children = $Site->getChildren([
                    'count' => 'count',
                    'where' => $this->getAttribute('where')
                ]);
            }
        }

        if (is_array($count_children)) {
            $count_children = count($count_children);
        }

        if ($this->supportsPinnedSorting()) {
            $children = array_slice($children, $start, $limit);
        } elseif ($this->getAttribute('parentInputList')) {
            // for bricks
            $children = Utils::getSitesByInputList($Project, $parents, [
                'where' => $where,
                'limit' => $start . ',' . $limit,
                'order' => $this->getAttribute('order')
            ]);
        } elseif ($this->getAttribute('children') || !$loadAllChildrenOnEmptyList) {
            $children = $this->getAttribute('children');
        } else {
            // for site types
            if ($this->getAttribute('byType')) {
                // get all sites, not just the direct children of a site
                $childIds = $Project->getSitesIds([
                    'where' => [
                        'active' => 1,
                        'type' => $this->getAttribute('byType'),
                    ],
                    'order' => 'release_from DESC',
                    'limit' => $start . ',' . $limit
                ]);

                foreach ($childIds as $id) {
                    $children[] = $Project->get($id['id']);
                }
            } else {
                // get only direct children of a site
                $children = $Site->getChildren([
                    'where' => $where,
                    'limit' => $start . ',' . $limit
                ]);
            }
        }

        return [is_array($children) ? $children : [], (int)ceil($count_children / $limit), $start];
    }

    /**
     * @param array<string, mixed> $where
     * @return array{array<int, QUI\Projects\Site>, int, int, int}
     */
    protected function getFilteredPage(
        QUI\Interfaces\Projects\Site $Site,
        array $where,
        int $limit,
        ChildrenListFilter $Filter
    ): array {
        $paging = (bool)$this->getAttribute('showSheets');
        $max = max(0, (int)$this->getAttribute('filterMax'));

        if ($this->canReadCandidateRows($Site)) {
            return $this->getCandidatePage($Site, $where, $limit, $Filter, $paging, $max);
        }

        $children = array_filter(
            $this->getFilterSource($Site, $where),
            static fn ($Child) => !$Child instanceof PermissionDenied
        );

        if (
            $this->getCustomTemplate() !== null
            && !$this->getAttribute('parentInputList')
            && ($this->getAttribute('children') || !$this->getAttribute('loadAllChildrenOnEmptyList'))
        ) {
            // The caller supplies the current page and owns its pagination.
            return [array_values($children), 0, 0, 1];
        }

        if ($this->supportsPinnedSorting()) {
            $children = $this->sortPinnedChildren($children);
        }

        if ($max > 0) {
            $children = array_slice($children, 0, $max);
        }

        if ($Filter->hasFilters()) {
            $TagManager = $this->getFilterTagManager();

            if ($Filter->tag !== '') {
                $taggedSiteIds = $TagManager->getSiteIdsFromTags([$Filter->tag]);
                $children = array_filter(
                    $children,
                    static fn ($Child) => isset($taggedSiteIds[$Child->getId()])
                );
            }

            if ($Filter->query !== '') {
                $children = $this->searchChildren($children, $Filter, $TagManager);
            }
        }

        [$sheets, $start, $page, $length] = $Filter->getPageBounds(count($children), $limit, $paging);

        return [array_slice($children, $start, $length), $sheets, $start, $page];
    }

    protected function canReadCandidateRows(QUI\Interfaces\Projects\Site $Site): bool
    {
        if (
            !$Site instanceof QUI\Projects\Site
            || QUI::isBackend()
            || $this->getCustomTemplate() !== null
            || $this->getAttribute('children')
            || !$this->getAttribute('loadAllChildrenOnEmptyList')
        ) {
            return false;
        }

        // Custom pin attributes may need a complete Site object.
        return !$this->supportsPinnedSorting()
            || (
                array_diff((array)$this->getAttribute('pinnedAttribute'), self::INDEXED_PIN_ATTRIBUTES) === []
                && ($this->getAttribute('parentInputList')
                    || in_array($this->getPinOrderField(), self::INDEXED_PIN_ORDER, true))
            );
    }

    /**
     * @param array<string, mixed> $where
     * @return array{array<int, QUI\Projects\Site>, int, int, int}
     */
    private function getCandidatePage(
        QUI\Interfaces\Projects\Site $Site,
        array $where,
        int $limit,
        ChildrenListFilter $Filter,
        bool $paging,
        int $max
    ): array {
        $input = $this->getAttribute('parentInputList');
        $type = (string)$this->getAttribute('byType');
        $Project = $input || $type !== '' ? $this->getProject() : $Site->getProject();
        $Source = $this->getFilterCandidateSource($Project);
        $ids = $Source->getIds(
            $Site,
            $where,
            $input ?: false,
            $type,
            (string)($this->getAttribute('order') ?? 'release_from ASC')
        );

        if (!$paging && !$Filter->hasFilters()) {
            $max = $max > 0 ? min($max, $limit) : $limit;
        }

        $pinAttributes = $this->supportsPinnedSorting() ? (array)$this->getAttribute('pinnedAttribute') : [];

        $sortPinned = $pinAttributes !== [] && !$input;
        $Tags = $ids !== [] && $Filter->hasFilters() ? $this->getFilterTagManager() : null;
        $matches = $Source->getMatchingIds(
            $ids,
            $Filter,
            $Tags,
            $max,
            $pinAttributes,
            $sortPinned ? $this->getPinOrderField() : '',
            ascending: $sortPinned && $this->getPinOrderDirection() === 'ASC'
        );

        return $Source->loadPage($matches, $Filter, $limit, $paging, !$input && $type === '' ? $Site : null);
    }

    protected function getFilterCandidateSource(QUI\Projects\Project $Project): ChildrenListCandidates
    {
        return new ChildrenListCandidates($Project);
    }

    protected function getFilterTagManager(): QUI\Tags\Manager
    {
        return new QUI\Tags\Manager($this->getProject());
    }

    /**
     * @param array<int, QUI\Projects\Site> $children
     * @return array<int, QUI\Projects\Site>
     */
    private function searchChildren(array $children, ChildrenListFilter $Filter, QUI\Tags\Manager $TagManager): array
    {
        $matches = [];
        $taggedIds = null;

        foreach ($children as $Child) {
            if (
                $Filter->matchesSearchText(
                    (string)$Child->getAttribute('title'),
                    (string)$Child->getAttribute('short')
                )
            ) {
                $matches[] = $Child;
                continue;
            }

            $taggedIds ??= $Filter->getSearchTagSiteIds($TagManager);

            if (isset($taggedIds[$Child->getId()])) {
                $matches[] = $Child;
            }
        }

        return $matches;
    }

    /**
     * Build structured data for the most recently rendered list on explicit request.
     * Call create() first. This never modifies the global page graph.
     */
    public function getJsonLd(): ?QUI\Utils\JsonLd
    {
        return $this->buildListJsonLd($this->renderedChildren, $this->renderedStart);
    }

    /**
     * @param array<int, QUI\Projects\Site> $children
     */
    protected function getListJsonLd(array $children, int $start = 0): string
    {
        if (!$this->getAttribute('ownJsonLd')) {
            return '';
        }

        return $this->buildListJsonLd($children, $start)?->getJsonLdSchema() ?? '';
    }

    /**
     * Keep MetaList as a collector for existing, type-hinted onMetaList callbacks.
     * Its legacy Microdata create() method must not be called here.
     *
     * @param array<int, QUI\Projects\Site> $children
     */
    protected function buildListJsonLd(array $children, int $start = 0): ?QUI\Utils\JsonLd
    {
        if ($children === [] || !$this->getAttribute('itemtype')) {
            return null;
        }

        $listType = preg_replace('#^https?://schema.org/#', '', $this->getAttribute('itemtype'));
        $childType = preg_replace('#^https?://schema.org/#', '', $this->getAttribute('child-itemtype'));
        $childProperty = $this->getAttribute('child-itemprop');
        $List = new QUI\Utils\JsonLd();
        $List->set('type', $listType);

        $items = [];
        $position = max(0, $start) + 1;

        foreach ($children as $Child) {
            $Child->load();
            $MetaList = new QUI\Controls\Utils\MetaList();
            // Article metadata belongs to the linked page, never to ListItem.
            $MetaList->set('type', $childType === 'ListItem' ? 'WebPage' : $childType);
            $this->Events->fireEvent('metaList', [$this, $Child, $MetaList]);
            $MetaList->set('name', $Child->getAttribute('title'));
            $MetaList->set('url', $Child->getUrlRewrittenWithHost());
            $item = $MetaList->getJsonLdData();

            if ($childType === 'ListItem') {
                $item = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'item' => $item
                ];
            }

            $items[] = $item;
            $position++;
        }

        $List->set($childProperty, $items);
        return $List;
    }

    /**
     * @param array<int, QUI\Projects\Site> $children
     * @return array<int, string>
     */
    protected function getChildCreatorNames(array $children): array
    {
        if (!$this->getAttribute('showCreator')) {
            return [];
        }

        $creatorNames = [];

        foreach ($children as $Child) {
            $creatorNames[$Child->getId()] = $this->getChildCreatorName($Child);
        }

        return $creatorNames;
    }

    protected function getChildCreatorName(QUI\Interfaces\Projects\Site $Child): string
    {
        // c_user contains a user UUID (string) since QUIQQER v2,
        // legacy sites may still hold a numeric id
        $userId = trim((string)$Child->getAttribute('c_user'));

        if ($userId === '' || $userId === '0') {
            return '';
        }

        try {
            $User = QUI::getUsers()->get($userId);
        } catch (Exception) {
            return '';
        }

        $firstname = trim((string)$User->getAttribute('firstname'));

        if ($firstname !== '') {
            return $firstname;
        }

        return trim((string)$User->getName());
    }

    /**
     * Sort loaded lists before pagination. Externally provided children retain
     * their caller-defined order and pagination.
     */
    protected function supportsPinnedSorting(): bool
    {
        return (bool)$this->getAttribute('pinnedAttribute')
            && !$this->getAttribute('children')
            && $this->getAttribute('loadAllChildrenOnEmptyList');
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int, QUI\Projects\Site>
     * @throws QUI\Exception
     */
    protected function getPinnedChildren(
        QUI\Interfaces\Projects\Site $Site,
        array $where,
        int $start,
        int $limit
    ): array {
        $children = $this->getFilterSource($Site, $where);
        $children = $this->sortPinnedChildren($children);

        return array_slice($children, $start, $limit);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int, QUI\Projects\Site>
     */
    private function getFilterSource(
        QUI\Interfaces\Projects\Site $Site,
        array $where
    ): array {
        if ($this->getAttribute('parentInputList')) {
            return Utils::getSitesByInputList($this->getProject(), $this->getAttribute('parentInputList'), [
                'where' => $where,
                'limit' => false,
                'order' => $this->getAttribute('order')
            ]);
        }

        $children = $this->getAttribute('children');

        if ($children || !$this->getAttribute('loadAllChildrenOnEmptyList')) {
            return is_array($children) ? $children : [];
        }

        if ($this->getAttribute('byType')) {
            return $this->getPinnedByTypeChildren();
        }

        $children = $Site->getChildren(['where' => $where]);

        return is_array($children) ? $children : [];
    }

    /**
     * @param array<string, mixed> $where
     * @throws QUI\Exception
     */
    protected function getPinnedChildrenCount(
        QUI\Interfaces\Projects\Site $Site,
        array $where
    ): int {
        if ($this->getAttribute('parentInputList')) {
            return count($this->getPinnedChildren($Site, $where, 0, PHP_INT_MAX));
        }

        if ($this->getAttribute('byType')) {
            return count($this->getPinnedByTypeChildren());
        }

        $children = $Site->getChildren([
            'where' => $where
        ]);

        if (is_array($children)) {
            return count($children);
        }

        return (int)$children;
    }

    /**
     * @return array<int, QUI\Projects\Site>
     * @throws QUI\Exception
     */
    protected function getPinnedByTypeChildren(): array
    {
        $Project = $this->getProject();
        $where = [
            'active' => 1,
            'type' => $this->getAttribute('byType')
        ];

        $configuredWhere = $this->getAttribute('where');

        if (is_array($configuredWhere)) {
            $where = array_merge($configuredWhere, $where);
        }

        $childIds = $Project->getSitesIds([
            'where' => $where,
            'order' => 'release_from DESC'
        ]);

        $children = [];

        foreach ($childIds as $id) {
            $children[] = $Project->get($id['id']);
        }

        return $children;
    }

    /**
     * @param array<int, QUI\Projects\Site> $children
     * @return array<int, QUI\Projects\Site>
     */
    protected function sortPinnedChildren(array $children): array
    {
        $pinnedAttributes = (array)$this->getAttribute('pinnedAttribute');
        $pinned = [];
        $normal = [];

        foreach ($children as $Child) {
            foreach ($pinnedAttributes as $pinnedAttribute) {
                if ($Child->getAttribute($pinnedAttribute)) {
                    $pinned[] = $Child;
                    continue 2;
                }
            }

            $normal[] = $Child;
        }

        if ($this->getAttribute('parentInputList')) {
            // The input list has already applied the configured brick ordering.
            return array_merge($pinned, $normal);
        }

        $sortBy = $this->getPinOrderField();
        $sortDirection = $this->getPinOrderDirection();
        $sortChildren = static function (
            QUI\Interfaces\Projects\Site $siteA,
            QUI\Interfaces\Projects\Site $siteB
        ) use (
            $sortBy,
            $sortDirection
): int {
            $valueA = $siteA->getAttribute($sortBy);
            $valueB = $siteB->getAttribute($sortBy);

            if ($valueA === $valueB) {
                return 0;
            }

            $result = $valueA <=> $valueB;

            if ($sortDirection === 'DESC') {
                return $result * -1;
            }

            return $result;
        };

        usort($pinned, $sortChildren);
        usort($normal, $sortChildren);

        return array_merge($pinned, $normal);
    }

    protected function getPinOrderField(): string
    {
        $pinnedOrder = (string)$this->getAttribute('pinnedOrder');

        if ($pinnedOrder === '') {
            return 'release_from';
        }

        $parts = explode(' ', $pinnedOrder, 2);

        return $parts[0];
    }

    protected function getPinOrderDirection(): string
    {
        $pinnedOrder = (string)$this->getAttribute('pinnedOrder');
        $parts = explode(' ', $pinnedOrder, 2);

        if (isset($parts[1]) && strtoupper($parts[1]) === 'ASC') {
            return 'ASC';
        }

        return 'DESC';
    }

    /**
     * Check if the limit can execute
     *
     * @throws QUI\Exception
     */
    public function checkLimit(): void
    {
        $Site = $this->getSite();
        $sheet = 1;
        $limit = $this->getAttribute('limit');

        if (!$limit) {
            $limit = 2;
        }

        if ($this->getAttribute('showSheets')) {
            if (isset($_REQUEST['sheet'])) {
                $sheet = (int)$_REQUEST['sheet'];
            }
        }

        $count_children = $Site->getChildren([
            'count' => 'count'
        ]);

        $sheets = ceil($count_children / $limit);

        if ($sheets < $sheet || $sheet < 0) {
            throw new QUI\Exception('Sites not found', 404);
        }
    }

    /**
     * @return QUI\Interfaces\Projects\Site
     * @throws QUI\Exception
     */
    protected function getSite(): QUI\Interfaces\Projects\Site
    {
        if (
            $this->getAttribute('Site')
            && $this->getAttribute('Site') instanceof QUI\Interfaces\Projects\Site
        ) {
            return $this->getAttribute('Site');
        }

        $Site = QUI::getRewrite()->getSite();

        if (!$Site instanceof QUI\Interfaces\Projects\Site) {
            throw new QUI\Exception('Site not found');
        }

        $this->setAttribute('Site', $Site);

        return $Site;
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTags(): array
    {
        if (!class_exists('QUI\Tags\Manager')) {
            return [];
        }

        $tags = $this->getAttribute('tags');

        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $tagsData = [];

        try {
            $Project = $this->getProject();
        } catch (Exception) {
            return [];
        }

        if (!empty($tags)) {
            $TagManager = new QUI\Tags\Manager($Project);

            foreach ($tags as $tag) {
                try {
                    $tagsData[] = $TagManager->get($tag);
                } catch (Exception) {
                    // Error handling if tag not found
                }
            }
        }

        return $tagsData;
    }

    /**
     * Resolve the tag slugs of each child to display titles, capped at the
     * 'tagsMax' setting. Returns a map of site id => list of tag titles;
     * templates render them as badges (cards / mediaList).
     *
     * @param array<int, QUI\Interfaces\Projects\Site> $children
     * @return array<int, array<int, string>>
     */
    protected function getChildTagTitles(array $children): array
    {
        // an unset value (site never saved since the setting exists) falls
        // back to the default; only an explicit 0 hides the badges
        $max = $this->getAttribute('tagsMax');
        $max = $max === false || $max === null || $max === '' ? 3 : (int)$max;

        if ($max <= 0 || !class_exists('QUI\Tags\Manager')) {
            return [];
        }

        try {
            $TagManager = new QUI\Tags\Manager($this->getProject());
        } catch (Exception) {
            return [];
        }

        $result = [];

        foreach ($children as $Child) {
            $tagList = $Child->load()->getAttribute('quiqqer.tags.tagList');

            if (!is_array($tagList) || $tagList === []) {
                continue;
            }

            $titles = [];

            foreach (array_slice($tagList, 0, $max) as $tag) {
                try {
                    $titles[] = $TagManager->get($tag)['title'];
                } catch (Exception) {
                    // tag no longer exists; fall back to the slug
                    $titles[] = $tag;
                }
            }

            $result[$Child->getId()] = $titles;
        }

        return $result;
    }

    /**
     * Write the card / general design settings as control config CSS
     * variables. The card template's CSS picks them up via the long,
     * themeable variables as a fallback layer (3-level pattern).
     */
    protected function applyCardCustomVariables(): void
    {
        $this->setCustomVariable(
            'cardColumns',
            (string)$this->normalizeColumnCount($this->getAttribute('cardColumns'), 3)
        );

        $tablet = $this->getAttribute('cardColumnsTablet');

        if ($tablet !== '' && $tablet !== false && $tablet !== null && (int)$tablet > 0) {
            $this->setCustomVariable('cardColumnsTablet', (string)(int)$tablet);
        }

        $this->setCustomVariable(
            'cardColumnsMobile',
            (string)$this->normalizeColumnCount($this->getAttribute('cardColumnsMobile'), 1)
        );
        $this->setCustomVariable('cardImageFit', $this->normalizeImageFit($this->getAttribute('cardImageFit')));
        $this->setCustomVariable('cardAspectRatio', $this->normalizeAspectRatio($this->getAttribute('cardAspectRatio')));
        $this->setCustomVariable('cardGap', $this->normalizeGap($this->getAttribute('cardGap')));
        $this->setCustomVariable('titleSize', $this->normalizeTitleSize($this->getAttribute('titleSize')));
        $this->setCustomVariable(
            'mediaImageWidth',
            $this->normalizeMediaImageWidth($this->getAttribute('mediaImageWidth'))
        );
    }

    /**
     * Write a control config CSS variable to the root element style. The CSS
     * picks it up via the long, themeable variable as a fallback layer.
     */
    protected function setCustomVariable(string $name, string $value): void
    {
        if ($name === '' || $value === '') {
            return;
        }

        $this->setStyle('--_q-controlConf-' . $name, $value);
    }

    protected function normalizeCardLayout(mixed $layout): string
    {
        // additional card layouts (e.g. 'overlay') are added here later
        return in_array($layout, ['standard'], true) ? (string)$layout : 'standard';
    }

    protected function normalizeColumnCount(mixed $value, int $default): int
    {
        $value = (int)$value;

        return $value > 0 ? $value : $default;
    }

    protected function normalizeImageFit(mixed $fit): string
    {
        return $fit === 'contain' ? 'contain' : 'cover';
    }

    protected function normalizeAspectRatio(mixed $ratio): string
    {
        if ($ratio === 'auto') {
            return 'auto';
        }

        if (is_string($ratio) && preg_match('/^\d+\s*\/\s*\d+$/', $ratio)) {
            $parts = explode('/', $ratio);

            return (int)trim($parts[0]) . ' / ' . (int)trim($parts[1]);
        }

        return '1 / 1';
    }

    protected function normalizeGap(mixed $gap): string
    {
        return self::GAP_PRESETS[(string)$gap] ?? self::GAP_PRESETS['normal'];
    }

    protected function normalizeMediaImagePosition(mixed $position): string
    {
        return in_array($position, ['left', 'right', 'alternate'], true) ? (string)$position : 'left';
    }

    protected function normalizeMediaImageWidth(mixed $width): string
    {
        return self::MEDIA_IMAGE_WIDTH_PRESETS[(string)$width] ?? self::MEDIA_IMAGE_WIDTH_PRESETS['normal'];
    }

    protected function normalizeTitleSize(mixed $size): string
    {
        return self::TITLE_SIZE_PRESETS[(string)$size] ?? self::TITLE_SIZE_PRESETS['normal'];
    }
}
