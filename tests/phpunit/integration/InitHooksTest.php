<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\InitHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\InitHooks
 */
class InitHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	public function testOnBeforeInitializeRegistersCurrentWiki() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$context = new RequestContext();
		$title = Title::newFromText( 'Foo' );
		$user = $this->getTestUser()->getUser();

		( new InitHooks() )->onBeforeInitialize(
			$title, null, $context->getOutput(), $user, $context->getRequest(), null
		);

		$this->assertSame( WikiMap::getCurrentWikiId(), LanguageStore::getWikiForLanguage( 'de' ) );
	}
}
