<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\TestCase;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\SiteTypes\ChildrenListCandidates;
use QUI\SiteTypes\ChildrenListFilter;
use QUI\SiteTypes\ChildrenListViewPermission;
use QUI\Tags\Manager;

require_once __DIR__ . '/Fixtures/FilterTestChildrenList.php';

class ChildrenListCandidatesTest extends TestCase
{
    public function testLargeUnfilteredListCountsOnlyVisibleRowsAndHydratesOnlyLastPage(): void
    {
        $ids = range(1, 1200);
        $sources = ['active' => 1];
        $Project = $this->createMock(Project::class);
        $Parent = $this->createMock(Site::class);
        $Parent->method('getProject')->willReturn($Project);
        $Parent->expects($this->once())->method('getChildrenIds')->with(['where' => $sources])->willReturn($ids);
        $Parent->expects($this->never())->method('getChildren');
        $Child = $this->createMock(Site::class);
        $Child->expects($this->once())->method('setAttribute')->with('parent', $Parent);
        $Project->expects($this->once())->method('get')->with(1199)->willReturn($Child);
        $Permissions = $this->createMock(ChildrenListViewPermission::class);
        $Permissions->method('allows')->willReturnCallback(static fn (bool $hasRule): bool => !$hasRule);
        $Candidates = $this->getMockBuilder(ChildrenListCandidates::class)
            ->setConstructorArgs([$Project, $Permissions])
            ->onlyMethods(['read'])
            ->getMock();
        // Allow different batch sizes while catching a read per candidate.
        $Candidates->expects($this->atMost(12))->method('read')
            ->willReturnCallback(static function (array $batch, bool $search, bool $pinned, string $order): array {
                self::assertLessThanOrEqual(500, count($batch));
                self::assertFalse($search);
                self::assertFalse($pinned);
                self::assertSame('', $order);
                $rows = [];
                foreach ($batch as $id) {
                    $rows[$id] = [
                        'permission_id' => $id === 1200 || $id === 1 ? $id : null,
                        'permission_value' => 'restricted'
                    ];
                }
                return $rows;
            });
        $List = new FilterTestChildrenList($this->createMock(Manager::class), [], $Candidates);
        $Filter = new ChildrenListFilter('list-site-7-', ['list-site-7-page' => 900], true, [], true);

        self::assertSame([[$Child], 400, 1197, 400], $List->page($Parent, 3, $Filter));
    }

    public function testBrickFiltersInSourceOrderAndOnlyLoadsTheCappedPinnedMatch(): void
    {
        $Project = $this->createMock(Project::class);
        $Parent = $this->createMock(Site::class);
        $Project->expects($this->once())->method('getSitesIds')->with([
            'where' => ['active' => 1],
            'where_or' => [
                'id' => ['type' => 'IN', 'value' => [1, 2, 3, 4, 5]],
                'type' => ['type' => 'IN', 'value' => ['quiqqer/news:types/news-entry']]
            ],
            'order' => 'title ASC'
        ])->willReturn([['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]]);
        $Child = $this->createMock(Site::class);
        $Project->expects($this->once())->method('get')->with(5)->willReturn($Child);
        $Permissions = $this->createMock(ChildrenListViewPermission::class);
        $Permissions->method('allows')->willReturnCallback(static fn (bool $hasRule): bool => !$hasRule);
        $Candidates = $this->getMockBuilder(ChildrenListCandidates::class)
            ->setConstructorArgs([$Project, $Permissions])
            ->onlyMethods(['read'])
            ->getMock();
        $Candidates->expects($this->once())->method('read')->with([1, 2, 3, 4, 5], true, true, '')->willReturn([
            2 => [
                'title' => 'Äpfel',
                'short' => '',
                'extra' => '',
                'permission_value' => null
            ],
            3 => [
                'title' => 'Birnen',
                'short' => '',
                'extra' => '{"quiqqer.settings.news.pinned":1}',
                'permission_value' => null
            ],
            4 => [
                'title' => 'Äpfel',
                'short' => '',
                'extra' => '{"quiqqer.settings.blog.pinned":1}',
                'permission_id' => 4,
                'permission_value' => 'restricted'
            ],
            5 => [
                'title' => 'Äpfel',
                'short' => '',
                'extra' => '{"quiqqer.settings.blog.pinned":1}',
                'permission_value' => null
            ]
        ]);

        $Tags = $this->createMock(Manager::class);
        $Tags->expects($this->once())->method('getSiteIdsFromTags')->with(['obst'])
            ->willReturn([2 => 1, 3 => 1, 4 => 1, 5 => 1]);
        $Tags->expects($this->once())->method('getList')->with(['search' => 'äPFEL', 'page' => 0])
            ->willReturn([]);
        $List = new FilterTestChildrenList($Tags, [
            'Project' => $Project,
            'parentInputList' => '1;2;3;4;5;quiqqer/news:types/news-entry',
            'order' => 'title ASC',
            'filterMax' => 2,
            'pinnedAttribute' => ['quiqqer.settings.blog.pinned', 'quiqqer.settings.news.pinned']
        ], $Candidates);
        $Filter = new ChildrenListFilter('list-brick-test-', [
            'list-brick-test-q' => 'äPFEL',
            'list-brick-test-tag' => 'obst'
        ], true, ['obst'], false);

        self::assertSame([[$Child], 1, 0, 1], $List->page($Parent, 2, $Filter));
    }
}
