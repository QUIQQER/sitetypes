<?php

/**
 * This file contains the sitemap site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$Engine->assign(
    'childTpl',
    dirname(__FILE__) . '/sitemapChildren.html'
);
