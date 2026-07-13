<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProjectHooks;

use MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks::onPageContentLanguage
 */
class OnPageContentLanguageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testSetForProjectTitle() {
		$title = Title::newFromText( 'Category:!eo:Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new TranslationProjectHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'eo', $pageLang->getCode() );
	}

	public function testUnchangedForOrdinaryTitle() {
		$title = Title::newFromText( 'Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new TranslationProjectHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'en', $pageLang->getCode() );
	}
}
