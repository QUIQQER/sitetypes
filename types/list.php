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
$ChildrenList = require __DIR__ . '/list.children.php';

if ($ChildrenList->isFilterEnabled()) {
    $query = $_GET['list-q'] ?? '';

    if (
        (is_string($query) && trim($query) !== '')
        || !empty($_GET['list-tag'])
        || (int)($_GET['list-site-' . $Site->getId() . '-page'] ?? 1) > 1
    ) {
        $Site->setAttribute('meta.robots', 'noindex,follow');
    }
} else {
    try {
        $ChildrenList->checkLimit();
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::addWarning($Exception->getMessage());
    }
}

// Prepare the visible list and explicitly register its structured data before the page head.
$childrenListHtml = $ChildrenList->create();

try {
    $ListJsonLd = $ChildrenList->getJsonLd();

    if ($ListJsonLd !== null) {
        $Template->getJsonLd()->setJsonLdNode('siteList', $ListJsonLd->getJsonLdData());
    }
} catch (QUI\Exception $Exception) {
    QUI\System\Log::addWarning($Exception->getMessage());
}

$Engine->assign([
    'childrenListHtml' => $childrenListHtml,
    'ChildrenList' => $ChildrenList
]);
