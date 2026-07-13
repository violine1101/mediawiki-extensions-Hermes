<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

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

	public function testSyncsCurrentWikiIntoDatabase() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->assertSame( WikiMap::getCurrentWikiId(), LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testThrowsWhenLanguageCodeChangedSinceRegistering() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );
		LanguageStore::getWikiForLanguage( 'de' );

		// Wiki IDs and language codes are expected to stay fixed once registered, so this isn't
		// silently re-synced - it needs a by-hand database fix, same as a genuine conflict.
		$this->overrideConfigValue( 'LanguageCode', 'fr' );
		LanguageStore::clearCacheForTesting();

		$this->expectException( \RuntimeException::class );
		LanguageStore::getWikiForLanguage( 'fr' );
	}

	public function testThrowsWhenAlreadyRegisteredElsewhere() {
		$this->registerBaseLanguage( 'otherwiki', 'de' );
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->expectException( \RuntimeException::class );
		LanguageStore::getWikiForLanguage( 'de' );
	}
}
