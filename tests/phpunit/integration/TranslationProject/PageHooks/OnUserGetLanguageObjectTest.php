<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\PageHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\PageHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\PageHooks::onUserGetLanguageObject
 */
class OnUserGetLanguageObjectTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testSetForProjectTitle() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$user = $this->getTestUser()->getUser();
		$code = 'en';
		( new PageHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'eo', $code );
	}

	public function testBaseLanguageForOrdinaryTitle() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( 'Foo' ) );
		$user = $this->getTestUser()->getUser();
		$code = 'fr';
		( new PageHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'en', $code );
	}

	public function testUsesDisplayLanguagePreference() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$user = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()
			->setOption( $user, DisplayLanguage::OPTION, DisplayLanguage::BASE );
		$code = 'fr';
		( new PageHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( LanguageStore::getLocalBaseLanguage(), $code );
	}

	public function testRespectsExplicitUselang() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$context->setRequest( new FauxRequest( [ 'uselang' => 'de' ] ) );
		$user = $this->getTestUser()->getUser();
		$code = 'de';
		( new PageHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'de', $code );
	}
}
