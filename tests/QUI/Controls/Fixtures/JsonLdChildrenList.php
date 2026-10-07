<?php

namespace QUITests\SiteTypes\Controls;

use QUI\Controls\ChildrenList;

class JsonLdChildrenList extends ChildrenList
{
    public function setRenderedChildren(array $children, int $start = 0): void
    {
        $this->renderedChildren = $children;
        $this->renderedStart = $start;
    }

    public function getListJsonLd(array $children, int $start = 0): string
    {
        return parent::getListJsonLd($children, $start);
    }
}
