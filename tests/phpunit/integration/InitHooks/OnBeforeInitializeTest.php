<?php

namespace MediaWiki\Extension\Hermes\Tests\InitHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\InitHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Extension\Hermes\WikiStore;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\InitHooks::onBeforeInitialize
 */
class OnBeforeInitializeTest extends HermesIntegrationTestCase {

	public function testRegistersCurrentWiki() {
		$this->overrideConfigValues( [
			'LanguageCode' => 'de',
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		$context = new RequestContext();
		$title = Title::newFromText( 'Foo' );
		$user = $this->getTestUser()->getUser();

		( new InitHooks() )->onBeforeInitialize(
			$title, null, $context->getOutput(), $user, $context->getRequest(), null
		);

		$this->assertSame( WikiMap::getCurrentWikiId(), LanguageStore::getWikiForLanguage( 'de' ) );
		$this->assertSame(
			'https://example.org/wiki/$1',
			WikiStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}
}
