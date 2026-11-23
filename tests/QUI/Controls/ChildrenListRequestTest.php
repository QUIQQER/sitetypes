<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\TestCase;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Projects\Site\PermissionDenied;
use QUI\SiteTypes\ChildrenListPage;
use QUI\SiteTypes\ChildrenListRequest;

class ChildrenListRequestTest extends TestCase
{
    public function testNativeControlUsesSavedConfigurationAndSiteContext(): void
    {
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getId')->willReturn(42);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getAttribute')->willReturnMap([
            ['active', 1],
            ['type', ChildrenListPage::TYPE],
            ['quiqqer.settings.sitetypes.list.template', 'cards'],
            ['quiqqer.settings.sitetypes.list.filter', 'input'],
            ['quiqqer.settings.sitetypes.list.max', 8]
        ]);
        $Site->expects($this->once())->method('load')->willReturnSelf();

        $List = ChildrenListRequest::resolve($Site, 'site');

        self::assertNotNull($List);
        self::assertSame($Project, $List->getAttribute('Project'));
        self::assertSame($Site, $List->getAttribute('Site'));
        self::assertSame('site-42', $List->getAttribute('filterKey'));
        self::assertSame(8, $List->getAttribute('limit'));
    }

    public function testOtherPageTypesAndDeniedSitesAreNotResolved(): void
    {
        $Denied = $this->createMock(PermissionDenied::class);
        $Denied->expects($this->never())->method('load');

        self::assertNull(ChildrenListRequest::resolve($Denied, 'site'));

        $Other = $this->createMock(Site::class);
        $Other->method('getAttribute')->willReturnMap([
            ['active', 1],
            ['type', 'quiqqer/news:types/news-list']
        ]);
        $Other->expects($this->never())->method('load');

        self::assertNull(ChildrenListRequest::resolve($Other, 'site'));
        self::assertNull(ChildrenListRequest::resolve($Other, 'brick-invalid/placement'));
    }

    public function testMultipleHostsAndHttpsAliasesUseTheirConfiguredLanguage(): void
    {
        $vhosts = [
            'example.test' => [
                'project' => 'website',
                'lang' => 'de',
                'path_langs' => 'fr',
                'httpshost' => 'tls.example.test'
            ],
            'de.example.test' => ['project' => 'website', 'lang' => 'de'],
            'en.example.test' => ['project' => 'website', 'lang' => 'en'],
            'other.test' => ['project' => 'other', 'lang' => 'de']
        ];

        self::assertTrue(ChildrenListRequest::allowsHost('example.test', 'website', 'de', $vhosts, []));
        self::assertTrue(ChildrenListRequest::allowsHost('de.example.test', 'website', 'de', $vhosts, []));
        self::assertTrue(ChildrenListRequest::allowsHost('tls.example.test', 'website', 'fr', $vhosts, []));
        self::assertFalse(ChildrenListRequest::allowsHost('example.test', 'website', 'en', $vhosts, []));
        self::assertFalse(ChildrenListRequest::allowsHost('other.test', 'website', 'de', $vhosts, []));
        self::assertFalse(ChildrenListRequest::allowsHost('foreign.test', 'website', 'de', $vhosts, []));
    }

    public function testUnassignedProjectsCanUseTheInstallationHosts(): void
    {
        $installationHosts = [
            'https://example.test' => ['httpshost' => 'https://tls.example.test']
        ];

        self::assertTrue(ChildrenListRequest::allowsHost('example.test', 'website', 'de', [], $installationHosts));
        self::assertTrue(ChildrenListRequest::allowsHost('tls.example.test', 'website', 'de', [], $installationHosts));
        self::assertFalse(ChildrenListRequest::allowsHost('foreign.test', 'website', 'de', [], $installationHosts));
    }
}
