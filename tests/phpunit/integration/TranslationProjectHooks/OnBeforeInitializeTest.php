<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProjectHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks::onBeforeInitialize
 */
class OnBeforeInitializeTest extends HermesIntegrationTestCase {

	private string $redirect = '';

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function runHook( string $titleText, array $query = [] ): bool {
		$context = new RequestContext();
		$title = Title::newFromText( $titleText );
		$context->setRequest( new FauxRequest( $query ) );
		$user = $this->getTestUser()->getUser();

		$result = ( new TranslationProjectHooks() )->onBeforeInitialize(
			$title, null, $context->getOutput(), $user, $context->getRequest(), null
		);

		$this->redirect = $context->getOutput()->getRedirect();
		return $result !== false;
	}

	public function testRedirectsMiscapitalizedProjectPage() {
		$continued = $this->runHook( '!eo:foo', [ 'action' => 'edit' ] );

		$this->assertFalse( $continued );
		$this->assertNotSame( '', $this->redirect );

		$query = [];
		parse_str( (string)parse_url( $this->redirect, PHP_URL_QUERY ), $query );
		$this->assertSame( 'edit', $query[ 'action' ] ?? null );
		$this->assertStringNotContainsString( 'foo', parse_url( $this->redirect, PHP_URL_PATH ) ?? '' );
	}

	public function testNoRedirectWhenPageAlreadyExists() {
		// A pre-existing miscapitalized page must stay directly accessible at its actual
		// title, rather than being redirected away to a likely-nonexistent "corrected" one.
		$this->insertPage( '!eo:foo' );

		$continued = $this->runHook( '!eo:foo' );

		$this->assertTrue( $continued );
		$this->assertSame( '', $this->redirect );
	}

	public function testNoRedirectWhenAlreadyCorrectlyCapitalized() {
		$continued = $this->runHook( '!eo:Foo' );

		$this->assertTrue( $continued );
		$this->assertSame( '', $this->redirect );
	}

	public function testNoRedirectForNonProjectPage() {
		$continued = $this->runHook( 'foo' );

		$this->assertTrue( $continued );
		$this->assertSame( '', $this->redirect );
	}

	public function testNoRedirectWhenCapitalLinksDisabled() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, false );

		$continued = $this->runHook( '!eo:foo' );

		$this->assertTrue( $continued );
		$this->assertSame( '', $this->redirect );
	}
}
