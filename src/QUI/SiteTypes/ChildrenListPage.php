<?php

namespace QUI\SiteTypes;

use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site;

final class ChildrenListPage
{
    public const TYPE = 'quiqqer/sitetypes:types/list';

    public static function create(Site $Site): ChildrenList
    {
        return new ChildrenList([
            'ownJsonLd' => false,
            'showTitle' => false,
            'Site' => $Site,
            'Project' => $Site->getProject(),
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
            'filterKey' => 'site-' . $Site->getId(),
            'filterPlacement' => 'site',
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
    }
}
