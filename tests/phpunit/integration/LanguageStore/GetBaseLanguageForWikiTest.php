<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getBaseLanguageForWiki
 */
class GetBaseLanguageForWikiTest extends HermesIntegrationTestCase {

	public function testRegisteredWiki() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$this->assertSame( 'de', LanguageStore::getBaseLanguageForWiki( 'dewiki' ) );
	}

	public function testUnregisteredWiki() {
		$this->assertNull( LanguageStore::getBaseLanguageForWiki( 'unregisteredwiki' ) );
	}
}
