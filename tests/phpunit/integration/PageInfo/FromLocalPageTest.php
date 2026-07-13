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
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testOrdinaryPage() {
		$page = $this->getExistingTestPage( 'PageInfoFromLocalPageOrdinary' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertNull( $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( '', $info->namespaceText );
		$this->assertSame( 'PageInfoFromLocalPageOrdinary', $info->title );
		$this->assertSame( 'en', $info->getLanguageCode() );
	}

	public function testMainNamespaceProjectPage() {
		$title = Title::newFromText( '!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_MAIN, $info->namespace );
		$this->assertSame( '', $info->namespaceText );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title );
		$this->assertSame( 'eo', $info->getLanguageCode() );
	}

	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->translationProject );
		$this->assertSame( NS_CATEGORY, $info->namespace );
		$this->assertSame( 'Category', $info->namespaceText );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title );
		$this->assertSame( 'eo', $info->getLanguageCode() );
	}
}
