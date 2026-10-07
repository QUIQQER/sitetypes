<?php

namespace QUITests\SiteTypes\Controls;

use PHPUnit\Framework\TestCase;
use QUI\Controls\ChildrenList;
use QUI\Controls\Utils\MetaList;
use QUI\Projects\Site;

require_once __DIR__ . '/Fixtures/JsonLdChildrenList.php';

class ChildrenListJsonLdTest extends TestCase
{
    public function testListKeepsArticleMetadataOffListItems(): void
    {
        $First = $this->createSite('First &amp; </script>', 'https://example.org/first');
        $Second = $this->createSite('Second', 'https://example.org/second');
        $List = new JsonLdChildrenList();
        $calls = 0;
        $List->addEvent('onMetaList', function (ChildrenList $List, Site $Site, MetaList $Meta) use ($First, &$calls) {
            $calls++;

            if ($Site === $First) {
                $Meta->add('author', 'Ada');
                $Meta->add('datePublished', '2026-10-07 12:00:00');
            }
        });
        $html = $List->getListJsonLd([$First, $Second], 9);
        $items = $this->decode($html)['itemListElement'];

        $this->assertSame(2, $calls);
        $this->assertSame([10, 11], array_column($items, 'position'));
        $this->assertSame('ListItem', $items[0]['@type']);
        $this->assertArrayNotHasKey('datePublished', $items[0]);
        $this->assertSame('WebPage', $items[0]['item']['@type']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ada'], $items[0]['item']['author']);
        $this->assertNotEmpty($items[0]['item']['datePublished']);
        $this->assertSame('https://example.org/first', $items[0]['item']['url']);
        $this->assertArrayNotHasKey('author', $items[1]['item']);
        $this->assertSame(1, substr_count($html, '</script>'));
        $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('First & </script>', $decoded['itemListElement'][0]['item']['name']);
    }

    public function testBlogUsesBlogPostRelationAndTypedAuthor(): void
    {
        $List = new JsonLdChildrenList([
            'itemtype' => 'https://schema.org/Blog',
            'child-itemtype' => 'https://schema.org/BlogPosting',
            'child-itemprop' => 'blogPost'
        ]);
        $List->addEvent('onMetaList', function (ChildrenList $List, Site $Site, MetaList $Meta) {
            $Meta->add('headline', 'A blog post');
            $Meta->add('author', 'Ada');
            $Meta->add('publisher', new class {
                public function toArray(): array
                {
                    return ['@type' => 'Organization', 'name' => 'Publisher'];
                }
            });
            $Meta->add('image', 'https://example.org/image.png');
        });
        $node = $this->decode($List->getListJsonLd([
            $this->createSite('A blog post', 'https://example.org/post')
        ]));

        $this->assertSame('Blog', $node['@type']);
        $this->assertArrayNotHasKey('itemListElement', $node);
        $this->assertSame('BlogPosting', $node['blogPost'][0]['@type']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ada'], $node['blogPost'][0]['author']);
        $this->assertSame('Organization', $node['blogPost'][0]['publisher']['@type']);
        $this->assertSame('https://example.org/image.png', $node['blogPost'][0]['image']);
    }

    public function testRepeatedRenderingContainsOnlyCurrentChildren(): void
    {
        $List = new JsonLdChildrenList();
        $First = $this->createSite('First', 'https://example.org/first');
        $Second = $this->createSite('Second', 'https://example.org/second');
        $this->assertCount(2, $this->decode($List->getListJsonLd([$First, $Second]))['itemListElement']);
        $items = $this->decode($List->getListJsonLd([$Second]))['itemListElement'];
        $this->assertCount(1, $items);
        $this->assertSame('Second', $items[0]['item']['name']);
        $this->assertSame('', $List->getListJsonLd([]));
        $List->setAttribute('itemtype', false);
        $this->assertSame('', $List->getListJsonLd([$First]));
    }

    public function testDisabledOwnJsonLdDoesNotBuildMetadataOrModifyGlobalGraph(): void
    {
        $Page = \QUI::getTemplateManager()->getJsonLd();
        $before = $Page->getJsonLdNodes();
        $List = new JsonLdChildrenList(['ownJsonLd' => false]);
        $Child = $this->createSite('First', 'https://example.org/first');
        $calls = 0;
        $List->addEvent('onMetaList', function () use (&$calls) {
            $calls++;
        });
        $this->assertSame('', $List->getListJsonLd([$Child]));
        $this->assertSame(0, $calls);
        $this->assertSame($before, $Page->getJsonLdNodes());
    }

    public function testSiteTypeCanExplicitlyExportRenderedListIntoPageGraph(): void
    {
        $Page = new \QUI\Utils\JsonLd();
        $Page->set('type', 'WebPage');
        $Page->setJsonLdNode('breadcrumb', ['@type' => 'BreadcrumbList']);
        $List = new JsonLdChildrenList(['ownJsonLd' => false]);
        $this->assertNull($List->getJsonLd());
        $List->setRenderedChildren([$this->createSite('First', 'https://example.org/first')], 9);
        $ListJsonLd = $List->getJsonLd();
        $this->assertNotNull($ListJsonLd);
        $Page->setJsonLdNode('siteList', $ListJsonLd->getJsonLdData());
        $header = $this->decode($Page->getJsonLdSchema());
        $this->assertSame('WebPage', $header['@graph'][0]['@type']);
        $this->assertSame('BreadcrumbList', $header['@graph'][1]['@type']);
        $this->assertSame('ItemList', $header['@graph'][2]['@type']);
        $this->assertSame(10, $header['@graph'][2]['itemListElement'][0]['position']);
        $this->assertSame('First', $header['@graph'][2]['itemListElement'][0]['item']['name']);
    }

    public function testOwnModeIsDefaultAndDoesNotModifyPageGraph(): void
    {
        $Page = \QUI::getTemplateManager()->getJsonLd();
        $before = $Page->getJsonLdNodes();
        $List = new JsonLdChildrenList();
        $this->assertTrue($List->getAttribute('ownJsonLd'));
        $html = $List->getListJsonLd([$this->createSite('Post', 'https://example.org/post')]);
        $this->assertSame('ItemList', $this->decode($html)['@type']);
        $this->assertSame($before, $Page->getJsonLdNodes());
    }

    private function decode(string $html): array
    {
        $json = substr($html, strlen('<script type="application/ld+json">'), -strlen('</script>'));
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    private function createSite(string $title, string $url): Site
    {
        $Site = $this->createMock(Site::class);
        $Site->method('load')->willReturnSelf();
        $Site->method('getAttribute')->willReturnCallback(static fn ($name) => $name === 'title' ? $title : null);
        $Site->method('getUrlRewrittenWithHost')->willReturn($url);
        return $Site;
    }
}
