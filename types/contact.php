<?php

/**
 * This file contains the contact site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

use QUI\Bricks\Controls\SimpleContact;

$Contact = new SimpleContact([
    'data-ajax' => 0,
    'mailTo' => $Site->getAttribute('quiqqer.settings.sitetypes.contact.email'),
    'showPrivacyPolicyCheckbox' => boolval(
        $Site->getAttribute('quiqqer.settings.sitetypes.contact.showPrivacyPolicyCheckbox')
    ),
    'useCaptcha' => boolval($Site->getAttribute('quiqqer.settings.sitetypes.contact.useCaptcha'))
]);

$Engine->assign([
    'Contact' => $Contact
]);
