<?php

namespace QUITests\SiteTypes\Controls;

use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site as SiteInterface;

class PinnedChildrenList extends ChildrenList
{
    public function supportsPinnedSorting(): bool
    {
        return parent::supportsPinnedSorting();
    }

    public function getPinnedChildren(SiteInterface $Site, array $where, int $start, int $limit): array
    {
        return parent::getPinnedChildren($Site, $where, $start, $limit);
    }

    public function sortPinnedChildren(array $children): array
    {
        return parent::sortPinnedChildren($children);
    }
}
