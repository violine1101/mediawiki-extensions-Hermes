<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\PageHooks;

use MediaWiki\Extension\Hermes\Hooks\TranslationProject\PageHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\PageHooks::onPageContentLanguage
 */
class OnPageContentLanguageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testSetForProjectTitle() {
		$title = Title::newFromText( 'Category:!eo:Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new PageHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'eo', $pageLang->getCode() );
	}

	public function testUnchangedForOrdinaryTitle() {
		$title = Title::newFromText( 'Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new PageHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'en', $pageLang->getCode() );
	}
}
