<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::init
 */
class InitTest extends HermesIntegrationTestCase {

	private string $wiki;

	protected function setUp(): void {
		parent::setUp();
		$this->wiki = WikiMap::getCurrentWikiId();
		$this->overrideConfigValues( [
			'LanguageCode' => 'de',
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );
	}

	public function testRegistersNewWiki() {
		LanguageStore::init();
		LanguageStore::clearCacheForTesting();

		$this->assertSame( 'https://example.org/wiki/$1', LanguageStore::getUrlTemplate( $this->wiki ) );
		$this->assertSame( 'de', LanguageStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertFalse( LanguageStore::isActive( 'de', $this->wiki ) );
		$this->assertNull( LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testRegistersAlongsideOtherWikis() {
		$this->registerBaseLanguage( 'otherwiki', 'de' );

		LanguageStore::init();

		$this->assertSame( 'de', LanguageStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertSame( 'otherwiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testKeepsExistingRegistration() {
		$this->registerBaseLanguage( $this->wiki, 'de' );

		LanguageStore::init();

		$this->assertTrue( LanguageStore::isActive( 'de', $this->wiki ) );
	}

	public function testKeepsBaseLanguageWhenConfigChanges() {
		$this->registerBaseLanguage( $this->wiki, 'fr' );

		LanguageStore::init();

		$this->assertSame( 'fr', LanguageStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertArrayNotHasKey( 'de', LanguageStore::getLanguages() );
	}

	public function testUpdatesUrlOfAllRows() {
		$this->registerBaseLanguage( $this->wiki, 'de', url: 'https://old.example.org/wiki/$1' );
		$this->registerProjectLanguage( $this->wiki, 'eo', url: 'https://old.example.org/wiki/$1' );

		LanguageStore::init();
		LanguageStore::clearCacheForTesting();

		foreach ( LanguageStore::getLanguages() as $byWiki ) {
			$this->assertSame( 'https://example.org/wiki/$1', $byWiki[ $this->wiki ]->urlTemplate );
		}
	}

	public function testIsIdempotent() {
		LanguageStore::init();
		LanguageStore::init();

		$this->assertSame( 'https://example.org/wiki/$1', LanguageStore::getUrlTemplate( $this->wiki ) );
		$this->assertSame( 'de', LanguageStore::getBaseLanguageForWiki( $this->wiki ) );
	}

	public function testUpdatesUrlWhenConfigChanges() {
		LanguageStore::init();

		$this->overrideConfigValue( 'Server', 'https://new.example.org' );
		LanguageStore::init();
		LanguageStore::clearCacheForTesting();

		$this->assertSame( 'https://new.example.org/wiki/$1', LanguageStore::getUrlTemplate( $this->wiki ) );
	}
}
