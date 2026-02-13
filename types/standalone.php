<?php

/**
 * This file contains the list site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

$engine = QUI::getTemplateManager()->getEngine();

$engine->assign([
    'Site' => $Site
]);

echo $engine->fetch(dirname(__FILE__) . '/standalone.html');
exit;
