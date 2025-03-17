<?php

/**
 * This file contains the iframe site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/


$settingsRaw = $Site->getAttribute('quiqqer.settings.sitetypes.externalContent.settings');

if (!$settingsRaw || !is_string($settingsRaw)) {
    QUI\System\Log::addWarning('ExternalContent: No settings found or settings are not a valid string.');

    return;
}

$settings = json_decode($settingsRaw, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings)) {
    QUI\System\Log::addWarning(
        'Site type external content: Failed to decode settings – JSON error: ' . json_last_error_msg()
    );

    return;
}

$ExternalContent = new QUI\Controls\ExternalContent([
    'externalContentType' => $settings['externalContent-type'] ?? 'text',
    'externalContentText' => $settings['externalContent-text'] ?? '',
    'iframeUrl' => $settings['externalContent-url'] ?? '',
    'iFrameHeightDesktop' => $settings['externalContent-height_desktop'] ?? null,
    'iFrameHeightMobile' => $settings['externalContent-height_mobile'] ?? null
]);

$Engine->assign([
    'ExternalContent' => $ExternalContent
]);
