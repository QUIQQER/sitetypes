<?php

namespace QUI\SiteTypes;

use QUI;
use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site;

final class ChildrenListPage
{
    public const TYPE = 'quiqqer/sitetypes:types/list';

    /**
     * Keep the original entry point for callers building the standard list.
     */
    public static function create(Site $Site): ChildrenList
    {
        return require dirname(__DIR__, 3) . '/types/list.children.php';
    }

    public static function getDefinitionFile(Site $Site): ?string
    {
        $siteType = $Site->getAttribute('type');
        if (!is_string($siteType) || !str_contains($siteType, ':')) {
            return null;
        }

        [$package, $type] = explode(':', $siteType, 2);
        if (!preg_match('~^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$~D', $package) || $type === '') {
            return null;
        }

        try {
            $root = realpath(QUI::getPackage($package)->getDir());
            if ($root === false || !is_file($root . '/site.xml')) {
                return null;
            }

            foreach (QUI\Utils\Text\XML::getTypesFromXml($root . '/site.xml') as $Type) {
                if ($Type instanceof \DOMElement && $Type->getAttribute('type') === $type) {
                    $file = realpath($root . '/' . $type . '.children.php');
                    return $file !== false && str_starts_with($file, $root . '/') && is_file($file) ? $file : null;
                }
            }
        } catch (QUI\Exception) {
            return null;
        }

        return null;
    }
}
