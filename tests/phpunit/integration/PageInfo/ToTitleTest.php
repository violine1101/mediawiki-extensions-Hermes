<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::toTitle
 */
class ToTitleTest extends HermesIntegrationTestCase {

	public function testMainNamespace() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = new PageInfo();
		$page->namespace = NS_MAIN;
		$page->translationProject = 'eo';
		$page->title = 'foo';

		$title = $page->toTitle();

		$this->assertSame( '!eo:Foo', $title->getPrefixedText() );
	}

	public function testOtherNamespace() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = new PageInfo();
		$page->namespace = NS_CATEGORY;
		$page->translationProject = 'eo';
		$page->title = 'foo';

		$title = $page->toTitle();

		$this->assertSame( NS_CATEGORY, $title->getNamespace() );
		$this->assertSame( '!eo:Foo', $title->getText() );
	}

	public function testWithSection() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = new PageInfo();
		$page->namespace = NS_MAIN;
		$page->translationProject = 'eo';
		$page->title = 'foo';
		$page->section = 'Bar';

		$title = $page->toTitle();

		$this->assertSame( '!eo:Foo', $title->getPrefixedText() );
		$this->assertSame( 'Bar', $title->getFragment() );
	}

	public function testWithProjectOfTemplateTarget() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$title = PageInfo::fromLocalPage( \MediaWiki\Title\Title::makeTitle( NS_TEMPLATE, 'Foo_bar' ) )
			->withProject( 'eo' )
			->toTitle();

		$this->assertSame( 'Template:!eo:Foo bar', $title->getPrefixedText() );
	}
}
