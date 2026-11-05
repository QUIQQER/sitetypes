<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Controls\ChildrenList;
use QUI\Controls\Utils\MetaList;
use QUI\Interfaces\Template\EngineInterface;
use QUI\Interfaces\Users\User;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Template;
use QUI\Users\Manager;

class ChildrenListCreatorTest extends TestCase
{
    public static function visibilityCases(): iterable
    {
        yield 'disabled' => [false, false, 0];
        yield 'disabled string' => ['0', false, 0];
        yield 'enabled' => [true, false, 1];
        yield 'enabled string' => ['1', false, 1];
        yield 'metadata with hidden author' => [false, true, 1];
        yield 'metadata with visible author' => [true, true, 2];
    }

    #[DataProvider('visibilityCases')]
    public function testRenderingOnlyLoadsVisibleCreatorNamesAndKeepsMetadataIndependent(
        bool | string $showCreator,
        bool $metadata,
        int $expectedLookups
    ): void {
        $previousUsers = QUI::$Users;
        $previousTemplate = QUI::$Template;
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getId')->willReturn(42);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getChildren')->willReturn(1);
        $Site->method('load')->willReturnSelf();
        $Site->method('getUrlRewrittenWithHost')->willReturn('https://example.org/post');
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => match ($key) {
            'c_user' => 'creator-uuid',
            'title' => 'Post',
            default => ''
        });
        $User = $this->createMock(User::class);
        $User->method('getName')->willReturn('Existing author');
        $User->method('getAttribute')->willReturn('Existing');
        $Users = $this->createMock(Manager::class);
        $Users->expects($this->exactly($expectedLookups))->method('get')
            ->with('creator-uuid')->willReturn($User);
        $assigned = [];
        $Engine = $this->createMock(EngineInterface::class);
        $Engine->method('fetch')->willReturn('');
        $Engine->method('assign')->willReturnCallback(static function ($key, $value = false) use (&$assigned): void {
            $values = is_array($key) ? $key : [$key => $value];
            $assigned = array_replace($assigned, $values);
        });
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        QUI::$Users = $Users;
        QUI::$Template = $Template;

        try {
            $List = new ChildrenList([
                'Site' => $Site,
                'Project' => $Project,
                'children' => [$Site],
                'loadAllChildrenOnEmptyList' => false,
                'showCreator' => $showCreator,
                'ownJsonLd' => $metadata,
                'tagsMax' => 0
            ]);
            $List->addEvent('onMetaList', static function (ChildrenList $List, Site $Child, MetaList $Meta): void {
                $Author = QUI::getUsers()->get($Child->getAttribute('c_user'));
                $Meta->add('author', $Author->getName());
            });
            $html = $List->getBody();
            $expectedNames = $showCreator ? [42 => 'Existing'] : [];
            $this->assertSame($expectedNames, $assigned['childCreatorNames']);

            if ($metadata) {
                $this->assertStringContainsString('application/ld+json', $html);
                $this->assertStringContainsString('Existing author', $html);
            } else {
                $this->assertSame('', $html);
            }
        } finally {
            QUI::$Users = $previousUsers;
            QUI::$Template = $previousTemplate;
        }
    }
}
