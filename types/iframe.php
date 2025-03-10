<?php

/**
 * This file contains the iframe site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$InlineFrame = new QUI\Controls\InlineFrame([
    'url' => $Site->getAttribute('quiqqer.settings.sitetypes.iframe.url'),
    'iFrameHeightDesktop' => $Site->getAttribute('quiqqer.settings.sitetypes.iframe.height.desktop'),
    'iFrameHeightMobile' => $Site->getAttribute('quiqqer.settings.sitetypes.iframe.height.mobile'),
]);

$Engine->assign([
    'InlineFrame' => $InlineFrame
]);
