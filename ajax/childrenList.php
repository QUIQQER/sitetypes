<?php

use QUI\SiteTypes\ChildrenListRequest;

QUI::getAjax()->registerFunction(
    'package_quiqqer_sitetypes_ajax_childrenList',
    ChildrenListRequest::render(...),
    ['project', 'lang', 'siteId', 'placement', 'filter']
);
