<?php

/**
 * This file contains QUI\Controls\ChildrenList
 */

namespace QUI\Controls;

use Exception;
use QUI;
use QUI\Projects\Site\Utils;

use function ceil;
use function count;
use function dirname;
use function file_exists;
use function is_array;

/**
 * Class ChildrenList
 *
 * @package quiqqer/sitetypes
 */
class ChildrenList extends QUI\Control
{
    /**
     * constructor
     *
     * @param array $attributes
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
            // if true returns all sites of certain type
            'byType' => false,
            'where' => false,
            'itemtype' => 'https://schema.org/ItemList',
            'child-itemtype' => 'https://schema.org/ListItem',
            'child-itemprop' => 'itemListElement',
            // layout / design
            'display' => 'childrenlist',
            // Custom children template (path to html file); overwrites "display"
            'displayTemplate' => false,
            // Custom children template css (path to css file); overwrites "display"
            'displayCss' => false,
            'nodeName' => 'section',
            // list of sites to display,
            'children' => false,
            // load all children of list site if the 'children' attribute is empty
            'loadAllChildrenOnEmptyList' => true,
            'fontColor' => '#fff', // relevant for some templates (e.g. bigBanner)

            // tags
            'tags' => [],
            'filter' => 'disabled' // 'all' / 'input' / 'tags' / 'disabled'
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

        // sheets
        $sheets = ceil($count_children / $limit);
        $loadAllChildrenOnEmptyList = $this->getAttribute('loadAllChildrenOnEmptyList');
        $where = $this->getAttribute('where');

        if (empty($where)) {
            $where = [];
        }

        $where['active'] = 1;

        if ($this->getAttribute('parentInputList')) {
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

        $showFilter = match($this->getAttribute('filter')) {
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
                    'id'          => $_Child->getId(),
                    'title'       => $_Child->getAttribute('title'),
                    'description' => $_Child->getAttribute('short'),
                    'tags'        => $_Child->getAttribute('quiqqer.tags.tagList'),
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
            'itemsData' => json_encode($itemsData, JSON_UNESCAPED_UNICODE)
        ]);

        $filterHtml = $Engine->fetch(dirname(__FILE__).'/ChildrenList.Filter.html');
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
            default:
            case 'childrenList':
                $css = dirname(__FILE__) . '/ChildrenList.css';
                $template = dirname(__FILE__) . '/ChildrenList.html';
                break;

            case 'longFooter':
                $css = dirname(__FILE__) . '/ChildrenList.LongFooter.css';
                $template = dirname(__FILE__) . '/ChildrenList.LongFooter.html';
                break;

            case 'authorTop':
                $css = dirname(__FILE__) . '/ChildrenList.AuthorTop.css';
                $template = dirname(__FILE__) . '/ChildrenList.AuthorTop.html';
                break;

            case '1er':
                $css = dirname(__FILE__) . '/ChildrenList.1er.css';
                $template = dirname(__FILE__) . '/ChildrenList.1er.html';
                break;

            case '2er':
                $css = dirname(__FILE__) . '/ChildrenList.2er.css';
                $template = dirname(__FILE__) . '/ChildrenList.2er.html';
                break;

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

        $this->addCSSFile($css);

        return $Engine->fetch($template);
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

        $this->setAttribute('Site', $Site);

        return $Site;
    }

    protected function getTags (): array
    {
        if (!QUI::getPackageManager()->isInstalled('quiqqer/tags')) {
            return [];
        }

        $tags = $this->getAttribute('tags');

        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $tagTitles = [];
        $tagsData = [];
        $Project = $this->getProject();

        if (!empty($tags)) {
            $TagManager = new QUI\Tags\Manager($Project);

            foreach ($tags as $tag) {
                try {
                    $tagData = $TagManager->get($tag);
                    $tagsData[] = $TagManager->get($tag);
                    $tagTitles[] = $tagData['title'];
                } catch (\Exception $e) {
                    // Fehlerbehandlung, falls Tag nicht gefunden
                }
            }
        }

        return $tagsData;
    }
}
