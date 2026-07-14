<?php

/**
 * This file contains the list site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

if (
    isset($_REQUEST['sheet'])
    && is_numeric($_REQUEST['sheet'])
    && (int)$_REQUEST['sheet'] > 1

    || isset($_REQUEST['limit'])
) {
    $Site->setAttribute('meta.robots', 'noindex,follow');
}

/**
 * Site list
 */
$a = $Site->getAttribute('quiqqer.tags.tagList');
$ChildrenList = new QUI\Controls\ChildrenList([
    'showTitle' => false,
    'Site' => $Site,
    'limit' => $Site->getAttribute('quiqqer.settings.sitetypes.list.max'),
    'showCreator' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showCreator'),
    'showDate' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showDate'),
    'showTime' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showTime'),
    'showSheets' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showSheets'),
    'showImages' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showImages'),
    'showHeader' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showHeader'),
    'showShort' => $Site->getAttribute('quiqqer.settings.sitetypes.list.showShort'),
    'showContent' => false,
    'itemtype' => 'https://schema.org/ItemList',
    'child-itemtype' => 'https://schema.org/ListItem',
    'display' => $Site->getAttribute('quiqqer.settings.sitetypes.list.template'),
    'filter' => $Site->getAttribute('quiqqer.settings.sitetypes.list.filter'),
    'tags' => $Site->getAttribute('quiqqer.settings.sitetypes.list.tags'),
    'tagsMax' => $Site->getAttribute('quiqqer.settings.sitetypes.list.tagsMax'),

    // card template (display 'cards')
    'cardLayout' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.layout'),
    'cardColumns' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.columns'),
    'cardColumnsTablet' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.columnsTablet'),
    'cardColumnsMobile' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.columnsMobile'),
    'cardImageFit' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.imageFit'),
    'cardAspectRatio' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.aspectRatio'),
    'cardGap' => $Site->getAttribute('quiqqer.settings.sitetypes.list.cards.gap'),

    // media list template (display 'mediaList')
    'mediaImagePosition' => $Site->getAttribute('quiqqer.settings.sitetypes.list.media.imagePosition'),
    'mediaImageWidth' => $Site->getAttribute('quiqqer.settings.sitetypes.list.media.imageWidth'),

    // general, template independent
    'titleSize' => $Site->getAttribute('quiqqer.settings.sitetypes.list.titleSize'),
    'buttonSize' => $Site->getAttribute('quiqqer.settings.sitetypes.list.buttonSize'),
    'buttonWidth' => $Site->getAttribute('quiqqer.settings.sitetypes.list.buttonWidth')
]);

try {
    $ChildrenList->checkLimit();
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

$Engine->assign([
    'ChildrenList' => $ChildrenList
]);
