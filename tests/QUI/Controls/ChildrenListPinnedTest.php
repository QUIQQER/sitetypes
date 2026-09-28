<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\TestCase;
use QUI\Projects\Project;
use QUI\Projects\Site;

require_once __DIR__ . '/Fixtures/PinnedChildrenList.php';

class ChildrenListPinnedTest extends TestCase
{
    public function testSiteListsKeepPublicationOrderWithinBothGroups(): void
    {
        $Old = $this->createSite(['release_from' => '2026-01-01']);
        $New = $this->createSite(['release_from' => '2026-09-01']);
        $PinnedOld = $this->createSite(['pinned' => 1, 'release_from' => '2025-01-01']);
        $PinnedNew = $this->createSite(['pinned' => 1, 'release_from' => '2026-08-01']);
        $Parent = $this->createMock(Site::class);
        $Parent->expects($this->once())->method('getChildren')
            ->with(['where' => ['active' => 1]])
            ->willReturn([$Old, $PinnedOld, $New, $PinnedNew]);

        $List = new PinnedChildrenList(['pinnedAttribute' => 'pinned']);

        $this->assertSame(
            [$PinnedNew, $PinnedOld, $New, $Old],
            $List->getPinnedChildren($Parent, ['active' => 1], 0, PHP_INT_MAX)
        );
    }

    public function testBrickLoadsAllMatchesAndPinsMixedEntriesBeforePagination(): void
    {
        $NormalA = $this->createSite([]);
        $NormalB = $this->createSite([]);
        $News = $this->createSite(['quiqqer.settings.news.pinned' => 1]);
        $Blog = $this->createSite(['quiqqer.settings.blog.pinned' => 1]);
        $Project = $this->createMock(Project::class);
        $Project->expects($this->once())->method('getSites')->with([
            'where' => ['active' => 1, 'id' => ['type' => 'IN', 'value' => [1, 2, 3, 4]]],
            'limit' => false,
            'order' => 'title ASC'
        ])->willReturn([$NormalA, $News, $NormalB, $Blog]);

        $List = new PinnedChildrenList([
            'Project' => $Project,
            'parentInputList' => '1;2;3;4',
            'order' => 'title ASC',
            'pinnedAttribute' => ['quiqqer.settings.blog.pinned', 'quiqqer.settings.news.pinned']
        ]);

        $this->assertSame(
            [$Blog, $NormalA],
            $List->getPinnedChildren($this->createMock(Site::class), ['active' => 1], 1, 2)
        );
    }

    public function testBrickPreservesOrderAndDoesNotDuplicateEntriesWithBothFlags(): void
    {
        $NormalA = $this->createSite([]);
        $NormalB = $this->createSite([]);
        $Both = $this->createSite(['blog.pinned' => 1, 'news.pinned' => 1]);
        $News = $this->createSite(['news.pinned' => 1]);
        $List = new PinnedChildrenList([
            'parentInputList' => '1;2;3;4',
            'pinnedAttribute' => ['blog.pinned', 'news.pinned']
        ]);

        $this->assertSame(
            [$Both, $News, $NormalA, $NormalB],
            $List->sortPinnedChildren([$NormalA, $Both, $NormalB, $News])
        );
    }

    public function testPinnedSortingRequiresOptInAndExcludesExternalChildren(): void
    {
        $List = new PinnedChildrenList(['parentInputList' => '1;2']);
        $this->assertFalse($List->supportsPinnedSorting());

        $List->setAttribute('pinnedAttribute', ['blog.pinned', 'news.pinned']);
        $this->assertTrue($List->supportsPinnedSorting());

        $List->setAttribute('children', [$this->createSite([])]);
        $this->assertFalse($List->supportsPinnedSorting());
    }

    public function testEmptyListRemainsEmpty(): void
    {
        $Parent = $this->createMock(Site::class);
        $Parent->expects($this->once())->method('getChildren')->willReturn([]);
        $List = new PinnedChildrenList(['pinnedAttribute' => 'pinned']);

        $this->assertSame([], $List->getPinnedChildren($Parent, ['active' => 1], 0, 3));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createSite(array $attributes): Site
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getAttribute')->willReturnCallback(
            static fn ($name) => $attributes[$name] ?? null
        );

        return $Site;
    }
}
