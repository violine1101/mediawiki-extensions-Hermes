<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectStore;
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
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	public function testOrdinaryPage() {
		$page = $this->getExistingTestPage( 'PageInfoFromLocalPageOrdinary' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertTrue( $info->title->getProject()->isBase );
		$this->assertSame( NS_MAIN, $info->title->namespace );
		$this->assertSame( '', $info->title->getNamespaceText() );
		$this->assertSame( 'PageInfoFromLocalPageOrdinary', $info->title->baseTitle );
		$this->assertSame( 'en', $info->title->getProject()->code );
	}

	public function testMainNamespaceProjectPage() {
		$title = Title::newFromText( '!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->title->getProject()->code );
		$this->assertSame( NS_MAIN, $info->title->namespace );
		$this->assertSame( '', $info->title->getNamespaceText() );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title->baseTitle );
		$this->assertSame( 'eo', $info->title->getProject()->code );
	}

	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoFromLocalPageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->title->getProject()->code );
		$this->assertSame( NS_CATEGORY, $info->title->namespace );
		$this->assertSame( 'Category', $info->title->getNamespaceText() );
		$this->assertSame( 'PageInfoFromLocalPageProject', $info->title->baseTitle );
		$this->assertSame( 'eo', $info->title->getProject()->code );
	}

	public function testNonProjectNamespaceIsNeverProject() {
		$title = Title::newFromText( 'File:!eo:PageInfoFromLocalPageFile' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertTrue( $info->title->getProject()->isBase );
		$this->assertSame( '!eo:PageInfoFromLocalPageFile', $info->title->baseTitle );
	}
}
