<?php

/**
 * This file contains QUI\Controls\ChildrenList
 */

namespace QUI\Controls;

use Exception;
use QUI;
use QUI\Projects\Site\Utils;

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
            // max tag badges per entry (cards / mediaList); 0 hides them
            'tagsMax' => 3,
            // Name of the site attribute used to mark pinned entries; false disables pin sorting
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
        $Pagination->loadFromRequest();
        $Pagination->setAttribute('Site', $Site);

        $start = 0;
        $limit = $this->getAttribute('limit');
        $parents = $this->getAttribute('parentInputList');
        $Project = $this->getProject();
        $children = [];

        if (!$parents) {
            $parents = $Site->getId();
        }

        if (!$limit) {
            $limit = 6;
        }

        if ($this->getAttribute('showSheets')) {
            if (isset($_REQUEST['sheet'])) {
                $start = ((int)$_REQUEST['sheet'] - 1) * $limit;
            }
        }

        if ($this->getAttribute('parentInputList')) {
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

                if (is_array($count_children[0])) {
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

        $loadAllChildrenOnEmptyList = $this->getAttribute('loadAllChildrenOnEmptyList');
        $where = $this->getAttribute('where');

        if (empty($where)) {
            $where = [];
        }

        $where['active'] = 1;

        if ($this->supportsPinnedSorting()) {
            $children = $this->getPinnedChildren($Site, $where, $start, $limit);
            $count_children = $this->getPinnedChildrenCount($Site, $where);
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

        // sheets
        $sheets = ceil($count_children / $limit);

        $showFilter = match ($this->getAttribute('filter')) {
            'all', 'input', 'tags' => true,
            default => false
        };

        $tags = [];
        $itemsData = [];

        if ($showFilter) {
            $tags = $this->getTags();
            $this->addCSSFile(dirname(__FILE__) . '/ChildrenList.Filter.css');

            foreach ($children as $Child) {
                $_Child = $Child->load();
                $itemsData[] = [
                    'id' => $_Child->getId(),
                    'title' => $_Child->getAttribute('title'),
                    'description' => $_Child->getAttribute('short'),
                    'tags' => $_Child->getAttribute('quiqqer.tags.tagList')
                ];
            }
        }

        $Pagination->setAttribute('limit', $limit);
        $Pagination->setAttribute('sheets', $sheets);

        $Engine->assign([
            'this' => $this,
            'Site' => $this->getSite(),
            'Project' => $this->getProject(),
            'sheets' => $sheets,
            'children' => $children,
            'Pagination' => $Pagination,
            'MetaList' => new QUI\Controls\Utils\MetaList(),
            'Events' => $this->Events,
            'showFilter' => $showFilter,
            'tags' => $tags,
            'itemsData' => json_encode($itemsData, JSON_UNESCAPED_UNICODE),
            'cardTemplate' => dirname(__FILE__) . '/ChildrenList.Card.'
                . ucfirst($this->normalizeCardLayout($this->getAttribute('cardLayout'))) . '.html',
            'mediaImagePosition' => $this->normalizeMediaImagePosition(
                $this->getAttribute('mediaImagePosition')
            ),
            'childTags' => is_array($children) ? $this->getChildTagTitles($children) : []
        ]);

        $filterHtml = $Engine->fetch(dirname(__FILE__) . '/ChildrenList.Filter.html');
        $Engine->assign(['filterHtml' => $filterHtml]);

        // load custom template (if set)
        if (
            $this->getAttribute('displayTemplate')
            && file_exists($this->getAttribute('displayTemplate'))
        ) {
            if (
                $this->getAttribute('displayCss')
                && file_exists($this->getAttribute('displayCss'))
            ) {
                $this->addCSSFile($this->getAttribute('displayCss'));
            }

            return $Engine->fetch($this->getAttribute('displayTemplate'));
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

            case 'imageTopBorder':
                $css = dirname(__FILE__) . '/ChildrenList.ImageTopBorder.css';
                $template = dirname(__FILE__) . '/ChildrenList.ImageTopBorder.html';
                break;

            case 'imageTop':
                $css = dirname(__FILE__) . '/ChildrenList.ImageTop.css';
                $template = dirname(__FILE__) . '/ChildrenList.ImageTop.html';
                break;

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

        return $Engine->fetch($template);
    }

    /**
     * Pin sorting is intentionally limited to direct child lists and byType
     * lists for now. Other ChildrenList sources such as parentInputList or
     * externally provided children use different loading semantics and are
     * not covered by the current implementation.
     */
    protected function supportsPinnedSorting(): bool
    {
        return (bool)$this->getAttribute('pinnedAttribute')
            && !$this->getAttribute('parentInputList')
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
        if ($this->getAttribute('byType')) {
            $children = $this->getPinnedByTypeChildren();
        } else {
            $children = $Site->getChildren([
                'where' => $where
            ]);

            if (!is_array($children)) {
                $children = [];
            }
        }

        $children = $this->sortPinnedChildren($children);

        return array_slice($children, $start, $limit);
    }

    /**
     * @param array<string, mixed> $where
     * @throws QUI\Exception
     */
    protected function getPinnedChildrenCount(
        QUI\Interfaces\Projects\Site $Site,
        array $where
    ): int {
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
        $pinnedAttribute = (string)$this->getAttribute('pinnedAttribute');
        $pinned = [];
        $normal = [];

        foreach ($children as $Child) {
            if ($Child->getAttribute($pinnedAttribute)) {
                $pinned[] = $Child;
                continue;
            }

            $normal[] = $Child;
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
