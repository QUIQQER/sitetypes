<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Controls\ChildrenList;
use QUI\Interfaces\Users\User;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Template;
use QUI\Users\Manager;

class ChildrenListCreatorTest extends TestCase
{
    private ?Manager $previousUsers;
    private ?Template $previousTemplate;
    private Manager $Users;

    protected function setUp(): void
    {
        $this->previousUsers = QUI::$Users;
        $this->previousTemplate = QUI::$Template;
        $Engine = QUI::getTemplateManager()->getEngine();
        $Template = $this->createMock(Template::class);
        $Template->method('getEngine')->willReturn($Engine);
        $this->Users = $this->createMock(Manager::class);
        QUI::$Users = $this->Users;
        QUI::$Template = $Template;
    }

    protected function tearDown(): void
    {
        QUI::$Users = $this->previousUsers;
        QUI::$Template = $this->previousTemplate;
    }

    public function testHiddenCreatorRendersEntryWithoutLoadingUser(): void
    {
        $this->Users->expects($this->never())->method('get');

        $html = $this->createList('0')->getBody();

        $this->assertStringContainsString('Test entry', $html);
        $this->assertStringNotContainsString('qui-control-childrenlist-simpleArticleList-author', $html);
    }

    public function testVisibleCreatorRendersUserNameInAuthorElement(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getAttribute')->with('firstname')->willReturn('Ada Example');
        $this->Users->method('get')->with('creator-uuid')->willReturn($User);

        $html = $this->createList('1')->getBody();
        $Document = new \DOMDocument();
        $Document->loadHTML($html);
        $XPath = new \DOMXPath($Document);

        $this->assertStringContainsString('Test entry', $html);
        $this->assertSame(
            'Ada Example',
            trim($XPath->evaluate(
                'string(//span[contains(@class, "qui-control-childrenlist-simpleArticleList-author")]/span[last()])'
            ))
        );
    }

    private function createList(string $showCreator): ChildrenList
    {
        $Project = $this->createMock(Project::class);
        $Site = $this->createMock(Site::class);
        $Site->method('getId')->willReturn(42);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getChildren')->willReturn(1);
        $Site->method('getAttribute')->willReturnCallback(static fn ($key) => match ($key) {
            'c_user' => 'creator-uuid',
            'title' => 'Test entry',
            default => ''
        });

        return new ChildrenList([
            'Site' => $Site,
            'Project' => $Project,
            'children' => [$Site],
            'loadAllChildrenOnEmptyList' => false,
            'showCreator' => $showCreator,
            'ownJsonLd' => false,
            'display' => 'simpleArticleList',
            'tagsMax' => 0
        ]);
    }
}
