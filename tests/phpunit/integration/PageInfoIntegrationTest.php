<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo
 */
class PageInfoIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testFromLocalPageForOrdinaryPage() {
		$page = $this->getExistingTestPage( 'PageInfoIntegrationTestOrdinary' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertSame( 'en', $info->language );
		$this->assertNull( $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( 'PageInfoIntegrationTestOrdinary', $info->title );
		$this->assertSame( 'PageInfoIntegrationTestOrdinary', $info->fullTitle );
	}

	public function testFromLocalPageForMainNamespaceProjectPage() {
		$title = Title::newFromText( '!eo:PageInfoIntegrationTestProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->language );
		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( 'PageInfoIntegrationTestProject', $info->title );
		$this->assertSame( '!eo:PageInfoIntegrationTestProject', $info->fullTitle );
	}

	public function testFromLocalPageForNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoIntegrationTestProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->language );
		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_CATEGORY, $info->namespace );
		$this->assertSame( 'PageInfoIntegrationTestProject', $info->title );
		$this->assertSame( 'Category:!eo:PageInfoIntegrationTestProject', $info->fullTitle );
	}

	public function testGetProjectLanguageReturnsLanguageForEffectiveLanguage() {
		$page = $this->getExistingTestPage( 'PageInfoIntegrationTestLang' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertSame( 'en', $info->getLanguage()->getCode() );
	}
}
