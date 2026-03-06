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
$response = QUI::getGlobalResponse();

$outputType = $Site->getAttribute('quiqqer.settings.sitetypes.standalone.outputType');
$allowedOutputTypes = [
    'text/html',
    'text/plain',
    'application/json',
    'application/xml'
];

if (!in_array($outputType, $allowedOutputTypes, true)) {
    $outputType = 'text/html';
}

$engine->assign([
    'Site' => $Site
]);

$response->headers->set('Content-Type', $outputType . '; charset=UTF-8');
$response->setContent(
    $engine->fetch(dirname(__FILE__) . '/standalone.html')
);
$response->send();

exit;
