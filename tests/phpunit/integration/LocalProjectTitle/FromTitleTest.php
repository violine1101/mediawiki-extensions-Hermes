<?php

namespace MediaWiki\Extension\Hermes\Tests\LocalProjectTitle;

use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LocalProjectTitle::fromTitle
 */
class FromTitleTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	public function testOrdinaryPage() {
		$page = $this->getExistingTestPage( 'FromTitleOrdinary' );
		$parsed = LocalProjectTitle::fromTitle( $page->getTitle() );

		$this->assertTrue( $parsed->project->isBase );
		$this->assertSame( NS_MAIN, $parsed->namespace );
		$this->assertSame( '', $parsed->getNamespaceText() );
		$this->assertSame( 'FromTitleOrdinary', $parsed->baseTitle );
		$this->assertSame( 'en', $parsed->project->code );
	}

	public function testMainNamespaceProjectPage() {
		$title = Title::newFromText( '!eo:FromTitleProject' );
		$parsed = LocalProjectTitle::fromTitle( $title );

		$this->assertSame( 'eo', $parsed->project->code );
		$this->assertSame( NS_MAIN, $parsed->namespace );
		$this->assertSame( '', $parsed->getNamespaceText() );
		$this->assertSame( 'FromTitleProject', $parsed->baseTitle );
		$this->assertSame( 'eo', $parsed->project->code );
	}

	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:FromTitleProject' );
		$parsed = LocalProjectTitle::fromTitle( $title );

		$this->assertSame( 'eo', $parsed->project->code );
		$this->assertSame( NS_CATEGORY, $parsed->namespace );
		$this->assertSame( 'Category', $parsed->getNamespaceText() );
		$this->assertSame( 'FromTitleProject', $parsed->baseTitle );
		$this->assertSame( 'eo', $parsed->project->code );
	}

	public function testNonProjectNamespaceIsNeverProject() {
		$title = Title::newFromText( 'File:!eo:FromTitleFile' );
		$parsed = LocalProjectTitle::fromTitle( $title );

		$this->assertTrue( $parsed->project->isBase );
		$this->assertSame( '!eo:FromTitleFile', $parsed->baseTitle );
	}
}
