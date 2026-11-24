<?php

namespace QUITests\SiteTypes\Controls;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use QUI\Bricks\Controls\Children\Listing;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Projects\Site\PermissionDenied;
use QUI\SiteTypes\ChildrenListFilter;
use QUI\Tags\Manager;

require_once __DIR__ . '/Fixtures/FilterTestChildrenList.php';

class ChildrenListFilterTest extends TestCase
{
    public function testFiltersTheFullSourceBeforeSortingAndSlicing(): void
    {
        $PinnedMiss = $this->site(1, ['title' => 'Äpfel', 'pinned' => true]);
        $FirstMatch = $this->site(2, ['title' => 'Neue Äpfel']);
        $ShortMatch = $this->site(3, ['short' => 'Auswahl: ÄPFEL']);
        $TagMatch = $this->site(4, ['pinned' => true]);
        $TaggedMiss = $this->site(5, ['title' => 'Birnen', 'pinned' => true]);
        $Denied = $this->createMock(PermissionDenied::class);
        $Denied->expects($this->never())->method('getId');
        $Denied->expects($this->never())->method('getAttribute');
        $Parent = $this->createMock(Site::class);
        $Parent->expects($this->once())->method('getChildren')
            ->with(['where' => ['active' => 1]])
            ->willReturn([$PinnedMiss, $FirstMatch, $Denied, $ShortMatch, $TagMatch, $TaggedMiss]);

        $Tags = $this->createMock(Manager::class);
        $Tags->expects($this->once())->method('getSiteIdsFromTags')->with(['ernte'])
            ->willReturn([2 => 1, 3 => 1, 4 => 1, 5 => 1]);
        $Tags->expects($this->never())->method('getSiteTags');
        $Tags->expects($this->once())->method('getList')->with(['search' => 'ÄpFeL', 'page' => 0])->willReturn([
            ['tag' => 'sommer-äpfel', 'title' => 'Sommer'],
            ['tag' => 'obst', 'title' => 'Äpfel']
        ]);
        $Tags->expects($this->once())->method('getTagSiteCacheData')->with(['sommer-äpfel'])->willReturn([
            ['sites' => ',4,']
        ]);

        $List = $this->createList($Tags, ['pinnedAttribute' => 'pinned']);
        $State = new ChildrenListFilter('list-site-7-', [
            'list-site-7-tag' => 'ernte',
            'list-site-7-q' => ' ÄpFeL ',
            'list-site-7-page' => '2'
        ], true, ['ernte'], false);

        self::assertSame(
            [[$ShortMatch], 2, 2, 2],
            $List->page($Parent, 2, $State)
        );
    }

    public function testDeniedChildrenAreExcludedFromUnfilteredPagination(): void
    {
        $Denied = $this->createMock(PermissionDenied::class);
        $Visible = $this->site(2, ['title' => 'Visible']);
        $Parent = $this->createMock(Site::class);
        $Parent->method('getChildren')->willReturn([$Denied, $Visible]);
        $List = $this->createList($this->createMock(Manager::class));
        $State = new ChildrenListFilter('list-site-7-', [], true, [], true);

        self::assertSame([[$Visible], 1, 0, 1], $List->page($Parent, 1, $State));
    }

    public function testSearchOnlyMatchesWithinTheBrickMaximumWithoutPagination(): void
    {
        $Miss = $this->site(1, ['title' => 'Other']);
        $FirstMatch = $this->site(2, ['title' => 'Needle 1']);
        $SecondMatch = $this->site(3, ['title' => 'Needle 2']);
        $ThirdMatch = $this->site(4, ['title' => 'Needle 3']);
        $Parent = $this->createMock(Site::class);
        $Parent->expects($this->once())->method('getChildren')
            ->with(['where' => ['active' => 1]])
            ->willReturn([$Miss, $FirstMatch, $SecondMatch, $ThirdMatch]);

        $List = $this->createList($this->createMock(Manager::class), [
            'filterMax' => 2,
            'showSheets' => false
        ]);
        $State = new ChildrenListFilter('list-brick-42-', ['list-brick-42-q' => 'needle'], true, [], false);

        self::assertSame(
            [[$FirstMatch], 0, 0, 1],
            $List->page($Parent, 1, $State)
        );
    }

    public function testNoMatchesHaveNoPagesEvenWhenTheUrlRequestsOne(): void
    {
        $Miss = $this->site(1, ['title' => 'Other']);
        $Parent = $this->createMock(Site::class);
        $Parent->method('getChildren')->willReturn([$Miss]);
        $List = $this->createList($this->createMock(Manager::class));
        $State = new ChildrenListFilter('list-site-7-', [
            'list-site-7-q' => 'needle',
            'list-site-7-page' => '42'
        ], true, [], false);

        self::assertSame([[], 0, 0, 1], $List->page($Parent, 2, $State));
    }

    #[TestWith(['imageTop', 'disabled'])]
    #[TestWith(['imageTopBorder', 'disabled'])]
    #[TestWith(['imageTop', 'input'])]
    #[TestWith(['imageTopBorder', 'input'])]
    public function testImageLayoutsRenderOnePagination(string $layout, string $filter): void
    {
        $children = [$this->site(1, ['title' => 'First']), $this->site(2, ['title' => 'Second'])];
        $Parent = $this->createMock(Site::class);
        $Parent->method('getChildren')->willReturnCallback(
            static fn (array $params) => isset($params['count']) ? count($children) : $children
        );
        $List = $this->createList($this->createMock(Manager::class), [
            'Site' => $Parent,
            'Project' => $this->createMock(Project::class),
            'display' => $layout,
            'filter' => $filter,
            'limit' => 1,
            'showSheets' => true,
            'showImages' => false,
            'showShort' => false,
            'showCreator' => false,
            'showDate' => false,
            'ownJsonLd' => false,
            'tagsMax' => 0
        ]);

        $Document = new DOMDocument();
        $Document->loadHTML($List->getBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $XPath = new DOMXPath($Document);
        self::assertSame(1.0, $XPath->evaluate('count(//a[contains(@class, "quiqqer-sheets-next")])'));
    }

    public function testCustomTemplateRetainsTheLegacyFilterAndItsItemData(): void
    {
        $Child = $this->site(8, ['title' => 'Custom result']);
        $Child->method('load')->willReturnSelf();
        $Parent = $this->createMock(Site::class);
        $Parent->method('getChildren')->willReturn([$Child]);
        $Project = $this->createMock(Project::class);
        $List = $this->createList($this->createMock(Manager::class), [
            'filter' => 'input',
            'Site' => $Parent,
            'Project' => $Project,
            'displayTemplate' => __DIR__ . '/Fixtures/CustomChildrenList.html',
            'tagsMax' => 0
        ]);

        $html = $List->getBody();

        self::assertStringContainsString('frontend/ChildrenListFilter"', $html);
        self::assertStringContainsString('data-itemsData=', $html);
        self::assertStringContainsString('data-id="8">Custom result</article>', $html);
    }

    public function testCustomTemplateKeepsTheCompletePageSuppliedByItsCaller(): void
    {
        $children = [];
        foreach ([7, 3, 9] as $id) {
            $Child = $this->site($id, ['title' => 'Entry ' . $id]);
            $Child->method('load')->willReturnSelf();
            $children[] = $Child;
        }

        $Parent = $this->createMock(Site::class);
        $Parent->expects(self::never())->method('getChildren');
        $List = $this->createList($this->createMock(Manager::class), [
            'Site' => $Parent,
            'Project' => $this->createMock(Project::class),
            'displayTemplate' => __DIR__ . '/Fixtures/CustomChildrenList.html',
            'children' => $children,
            'limit' => 2,
            'filter' => 'input',
            'tagsMax' => 0
        ]);

        preg_match_all('/<article data-name="item" data-id="(\d+)"/', $List->getBody(), $matches);
        self::assertSame(['7', '3', '9'], $matches[1]);
    }

    public function testOnlyExplicitBrickPlacementsBindTheRemoteControl(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getChildren')->willReturn([]);
        $Project = $this->createMock(Project::class);
        $Project->method('getName')->willReturn('website');
        $Project->method('getLang')->willReturn('de');
        $List = $this->createList($this->createMock(Manager::class), [
            'filter' => 'input',
            'Site' => $Site,
            'Project' => $Project,
            'display' => 'mediaList',
            'filterKey' => 'brick-42',
            'tagsMax' => 0
        ]);

        $html = $List->getBody();
        self::assertStringContainsString('ChildrenListNavigation', $html);
        self::assertStringNotContainsString('ChildrenListRemoteFilter', $html);
        self::assertSame('list-brick-42-', $List->getAttribute('data-list-prefix'));

        $placement = 'brick-9eae34da-8a6a-4d52-a7c7-3c2e65a6530c';
        $List->setAttribute('filterPlacement', $placement);

        $html = $List->getBody();
        self::assertSame('list-brick-42-', $List->getAttribute('data-list-prefix'));
        if (method_exists(Listing::class, 'fromPlacement')) {
            self::assertStringContainsString('ChildrenListRemoteFilter', $html);
            self::assertStringContainsString('data-placement="' . $placement . '"', $html);
        } else {
            self::assertStringContainsString('ChildrenListNavigation', $html);
            self::assertStringNotContainsString('ChildrenListRemoteFilter', $html);
            self::assertStringNotContainsString('data-placement=', $html);
            self::assertStringContainsString('name="list-brick-42-q"', $html);
        }
    }

    public function testUnregisteredSitePlacementUsesRegularLinksAndGetForm(): void
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getId')->willReturn(42);
        $Site->method('getAttribute')->willReturnMap([['type', 'example/unregistered:types/list']]);
        $Site->method('getChildren')->willReturn([
            $this->site(1, ['title' => 'First']),
            $this->site(2, ['title' => 'Second'])
        ]);
        $List = $this->createList($this->createMock(Manager::class), [
            'Site' => $Site,
            'Project' => $this->createMock(Project::class),
            'display' => 'mediaList',
            'filter' => 'input',
            'isMainList' => true,
            'limit' => 1,
            'tagsMax' => 0
        ]);

        $html = $List->getBody();
        self::assertStringContainsString('ChildrenListNavigation', $html);
        self::assertStringNotContainsString('ChildrenListRemoteFilter', $html);
        self::assertStringContainsString('name="list-site-42-q"', $html);
        self::assertStringContainsString('href="?list-site-42-page=2"', $html);
    }

    public function testFilteredListRendersStandardPaginationWithIndependentPageUrls(): void
    {
        $children = [];
        foreach ([1, 2, 3, 4] as $id) {
            $Child = $this->site($id, ['title' => 'Stöcke + Steine ' . $id]);
            $Child->method('load')->willReturnSelf();
            $children[] = $Child;
        }

        $Parent = $this->createMock(Site::class);
        $Project = $this->createMock(Project::class);
        $Parent->method('getProject')->willReturn($Project);
        $Parent->method('getChildren')->willReturn($children);

        $List = $this->createList($this->createMock(Manager::class), [
            'Site' => $Parent,
            'Project' => $Project,
            'display' => 'mediaList',
            'filter' => 'input',
            'filterKey' => 'brick-42',
            'limit' => 1,
            'showImages' => false,
            'showHeader' => false,
            'showShort' => false,
            'ownJsonLd' => false,
            'tagsMax' => 0
        ]);

        $previousQuery = $_GET;
        $_GET = [
            'list-site-7-page' => '3',
            'list-brick-42-q' => 'Stöcke + Steine',
            'list-brick-42-page' => '2'
        ];

        try {
            $html = $List->getBody();
        } finally {
            $_GET = $previousQuery;
        }

        $Document = new DOMDocument();
        $Document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $XPath = new DOMXPath($Document);
        $pagination = '//div[@data-name="pagination"]/nav[contains(@class, "quiqqer-pagination")]';

        self::assertSame(1.0, $XPath->evaluate('count(' . $pagination . ')'));
        self::assertSame('2', $XPath->evaluate('string(' . $pagination . '//*[@aria-current="page"])'));
        self::assertNotSame(
            '',
            $XPath->evaluate('normalize-space(' . $pagination . '//select[@data-name="pageSelect"]/@onchange)')
        );
        self::assertSame(
            '2',
            $XPath->evaluate('string(' . $pagination . '//select[@data-name="pageSelect"]/option[@selected]/@data-page)')
        );

        $query = '?list-site-7-page=3&list-brick-42-q=St%C3%B6cke%20%2B%20Steine';
        $arrowUrls = [
            'first' => $query,
            'prev' => $query,
            'next' => $query . '&list-brick-42-page=3',
            'last' => $query . '&list-brick-42-page=4'
        ];
        foreach ($arrowUrls as $arrow => $url) {
            $link = $pagination . '//a[contains(@class, "quiqqer-sheets-' . $arrow . '")]';
            self::assertSame($url, $XPath->evaluate('string(' . $link . '/@href)'));
        }

        self::assertSame(
            $query . '&list-brick-42-page=3',
            $XPath->evaluate('string(' . $pagination . '//option[@data-page="3"]/@value)')
        );

        $List->setAttribute('limit', 4);
        $fragment = $List->renderFilterFragment(['q' => 'Stöcke + Steine', 'page' => 2]);
        self::assertStringContainsString('<div data-name="pagination"></div>', $fragment);
    }

    public function testMainListPreservesFiltersAndOtherListsWhenResettingItsPage(): void
    {
        $State = new ChildrenListFilter('list-site-7-', [
            'list-brick-42-q' => 'Other list',
            'list-q' => ' Stöcke + Steine ',
            'list-tag' => 'wald',
            'sheet' => '5'
        ], true, ['wald'], true, '/Children-List');

        self::assertSame('wald', $State->tag);
        self::assertSame(5, $State->page);
        self::assertSame(
            '?list-brick-42-q=Other%20list'
            . '&list-q=St%C3%B6cke%20%2B%20Steine&list-tag=wald',
            $State->getUrl('wald', 1)
        );
        self::assertSame([
            ['name' => 'list-brick-42-q', 'value' => 'Other list'],
            ['name' => 'list-tag', 'value' => 'wald']
        ], $State->getFormFields());
    }

    public function testMainListReadsOldBookmarksAndGeneratesCleanPageLinks(): void
    {
        $bookmarks = [
            ['list-site-21-page' => 2],
            ['list-site-21-page' => 2, 'sheet' => 5, 'list-site-21-q' => 'old', 'list-site-21-tag' => 'old']
        ];

        foreach ($bookmarks as $params) {
            $State = new ChildrenListFilter('list-site-21-', $params, true, ['old'], true, '/Children-List');

            self::assertSame(2, $State->page);
            self::assertFalse($State->hasFilters());
            self::assertSame('/Children-List', $State->getUrl('', 1));
            self::assertSame('?sheet=2', $State->getUrl('', 2));
            self::assertSame([], $State->getFormFields());
        }
    }

    public function testBrickKeepsMainSheetWhenPagingAndSearching(): void
    {
        $State = new ChildrenListFilter('list-brick-42-', ['sheet' => 3], true, ['Technik'], false);

        self::assertSame(1, $State->page);
        self::assertSame('?sheet=3&list-brick-42-tag=Technik&list-brick-42-page=2', $State->getUrl('Technik', 2));
        self::assertSame([['name' => 'sheet', 'value' => '3']], $State->getFormFields());
    }

    private function site(int $id, array $attributes): Site
    {
        $Site = $this->createMock(Site::class);
        $Site->method('getId')->willReturn($id);
        $Site->method('getAttribute')->willReturnCallback(static fn (string $name) => $attributes[$name] ?? null);

        return $Site;
    }

    private function createList(Manager $Tags, array $attributes = []): FilterTestChildrenList
    {
        return new FilterTestChildrenList($Tags, $attributes);
    }
}
