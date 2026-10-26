<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\CategoryHooks;

use MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks::onGetDefaultSortkey
 */
class OnGetDefaultSortkeyTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	public function testStripsProjectPrefixFromSortkey() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!eo:TestPage' );

		$sortkey = $title->getText();
		$hooks->onGetDefaultSortkey( $title, $sortkey );

		$this->assertSame( 'TestPage', $sortkey );
	}

	public function testSortkeyUsesSpacesLikeCore() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!eo:Test_Page' );

		$sortkey = $title->getText();
		$hooks->onGetDefaultSortkey( $title, $sortkey );

		$this->assertSame( 'Test Page', $sortkey );
	}

	public function testLeavesNonProjectPageSortkeyUntouched() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, 'TestPage' );

		$sortkey = $title->getText();
		$hooks->onGetDefaultSortkey( $title, $sortkey );

		$this->assertSame( 'TestPage', $sortkey );
	}
}
