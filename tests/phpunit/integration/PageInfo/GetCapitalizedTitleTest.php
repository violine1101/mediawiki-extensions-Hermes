<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getCapitalizedTitle
 */
class GetCapitalizedTitleTest extends HermesIntegrationTestCase {

	public function testMainNamespace() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = new PageInfo();
		$page->namespace = NS_MAIN;
		$page->translationProject = 'eo';
		$page->title = 'foo';

		$title = $page->getCapitalizedTitle();

		$this->assertSame( '!eo:Foo', $title->getPrefixedText() );
	}

	public function testOtherNamespace() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = new PageInfo();
		$page->namespace = NS_CATEGORY;
		$page->translationProject = 'eo';
		$page->title = 'foo';

		$title = $page->getCapitalizedTitle();

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

		$title = $page->getCapitalizedTitle();

		$this->assertSame( '!eo:Foo', $title->getPrefixedText() );
		$this->assertSame( 'Bar', $title->getFragment() );
	}
}
