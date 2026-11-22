<?php

namespace QUITests\SiteTypes\Controls;

use QUI\Controls\ChildrenList;
use QUI\Interfaces\Projects\Site as SiteInterface;
use QUI\Projects\Project;
use QUI\SiteTypes\ChildrenListCandidates;
use QUI\SiteTypes\ChildrenListFilter;
use QUI\Tags\Manager;

class FilterTestChildrenList extends ChildrenList
{
    public function __construct(
        private readonly Manager $Tags,
        array $attributes = [],
        private readonly ?ChildrenListCandidates $Candidates = null
    ) {
        parent::__construct($attributes);
    }

    public function page(SiteInterface $Site, int $limit, ChildrenListFilter $Filter): array
    {
        return $this->getFilteredPage($Site, ['active' => 1], $limit, $Filter);
    }

    protected function canReadCandidateRows(SiteInterface $Site): bool
    {
        return $this->Candidates !== null && parent::canReadCandidateRows($Site);
    }

    protected function getFilterCandidateSource(Project $Project): ChildrenListCandidates
    {
        return $this->Candidates ?? parent::getFilterCandidateSource($Project);
    }

    protected function getFilterTagManager(): Manager
    {
        return $this->Tags;
    }
}
