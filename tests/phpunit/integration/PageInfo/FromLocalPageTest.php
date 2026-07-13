<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::fromLocalPage
 */
class FromLocalPageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testOrdinaryPage() {
		$page = $this->getExistingTestPage( 'PageInfoFromLocalPageOrdinary' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertSame( 'en', $info->language );
		$this->assertNull( $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( 'PageInfoFromLocalPageOrdinary', $info->title );
		$this->assertSame( 'PageInfoFromLocalPageOrdinary', $info->fullTitle );
	}

	public function testMainNamespaceProjectPage() {
		$title = Title::newFromText( '!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->language );
		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title );
		$this->assertSame( '!eo:PageInfoFromLocalPageProject', $info->fullTitle );
	}

	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->language );
		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_CATEGORY, $info->namespace );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title );
		$this->assertSame( 'Category:!eo:PageInfoFromLocalPageProject', $info->fullTitle );
	}
}
