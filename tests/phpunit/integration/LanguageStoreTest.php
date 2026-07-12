<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore
 */
class LanguageStoreTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	/**
	 * Directly inserts a base-language row, standing in for some other wiki having already
	 * registered itself (this test isn't exercising that self-registration, just consuming it).
	 */
	private function registerBaseLanguage( string $wiki, string $language ): void {
		Hermes::getDB( DB_PRIMARY )->insert(
			'hermes_languages',
			[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => 1 ],
			__METHOD__
		);
		LanguageStore::clearCacheForTesting();
	}

	public function testGetWikiForLanguageReturnsNullWhenUnregistered() {
		$this->assertNull( LanguageStore::getWikiForLanguage( 'xx' ) );
	}

	public function testRegisteredBaseLanguageThenGetWikiForLanguage() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testGetLocalBaseLanguageReturnsCurrentWikisLanguageCode() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->assertSame( 'de', LanguageStore::getLocalBaseLanguage() );
	}

	public function testGetWikiForLanguageSyncsCurrentWikiIntoDatabase() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->assertSame( WikiMap::getCurrentWikiId(), LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testGetWikiForLanguageThrowsWhenWikisLanguageCodeChangedSinceRegistering() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );
		LanguageStore::getWikiForLanguage( 'de' );

		// Wiki IDs and language codes are expected to stay fixed once registered, so this isn't
		// silently re-synced - it needs a by-hand database fix, same as a genuine conflict.
		$this->overrideConfigValue( 'LanguageCode', 'fr' );
		LanguageStore::clearCacheForTesting();

		$this->expectException( \RuntimeException::class );
		LanguageStore::getWikiForLanguage( 'fr' );
	}

	public function testGetWikiForLanguageThrowsWhenLanguageAlreadyRegisteredToAnotherWiki() {
		$this->registerBaseLanguage( 'otherwiki', 'de' );
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->expectException( \RuntimeException::class );
		LanguageStore::getWikiForLanguage( 'de' );
	}

	public function testAddProjectLanguageCoexistsWithBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertSame( 'en', LanguageStore::getLocalBaseLanguage() );
		$this->assertSame( $wiki, LanguageStore::getWikiForLanguage( 'eo' ) );
	}

	public function testIsProjectLanguageTrueForRegisteredProjectOnCurrentWiki() {
		$wiki = WikiMap::getCurrentWikiId();
		LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertTrue( LanguageStore::isProjectLanguage( 'eo' ) );
	}

	public function testIsProjectLanguageFalseForBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertFalse( LanguageStore::isProjectLanguage( 'en' ) );
	}

	public function testIsProjectLanguageFalseForUnregisteredLanguage() {
		$this->assertFalse( LanguageStore::isProjectLanguage( 'zz' ) );
	}

	public function testIsProjectLanguageFalseWhenRegisteredToAnotherWiki() {
		LanguageStore::addProjectLanguage( 'otherwiki', 'eo' );

		$this->assertFalse( LanguageStore::isProjectLanguage( 'eo' ) );
	}
}
