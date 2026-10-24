<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getWikiForLanguage
 */
class GetWikiForLanguageTest extends HermesIntegrationTestCase {

	public function testUnregistered() {
		$this->assertNull( LanguageStore::getWikiForLanguage( 'xx' ) );
	}

	public function testReturnsRegisteredWiki() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testReturnsActiveWiki() {
		$this->registerBaseLanguage( 'dewiki', 'de', false );
		$this->registerProjectLanguage( 'otherwiki', 'de' );

		$this->assertSame( 'otherwiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testNullWhenOnlyInactive() {
		$this->registerBaseLanguage( 'dewiki', 'de', false );

		$this->assertNull( LanguageStore::getWikiForLanguage( 'de' ) );
	}
}
