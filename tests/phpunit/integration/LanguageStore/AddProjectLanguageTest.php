<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::addProjectLanguage
 */
class AddProjectLanguageTest extends HermesIntegrationTestCase {

	public function testCoexistsWithBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		$isNew = LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertTrue( $isNew );
		$this->assertSame( 'en', LanguageStore::getLocalBaseLanguage() );
		$this->assertSame( $wiki, LanguageStore::getWikiForLanguage( 'eo' ) );
	}

	public function testAllowsIdempotentReRegistration() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerProjectLanguage( $wiki, 'eo' );

		$isNew = LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertFalse( $isNew );
		$this->assertSame( $wiki, LanguageStore::getWikiForLanguage( 'eo' ) );
	}

	public function testThrowsOnConflictWithAnotherWikisBaseLanguage() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		try {
			LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'de' );
			$this->fail( 'Expected LanguageConflictException to be thrown' );
		} catch ( LanguageConflictException $e ) {
			$this->assertSame( 'de', $e->language );
			$this->assertSame( 'dewiki', $e->existingWiki );
			$this->assertSame( WikiMap::getCurrentWikiId(), $e->requestedWiki );
		}
	}

	public function testThrowsOnConflictWithAnotherWikisProjectLanguage() {
		$this->registerProjectLanguage( 'dewiki', 'eo' );

		try {
			LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
			$this->fail( 'Expected LanguageConflictException to be thrown' );
		} catch ( LanguageConflictException $e ) {
			$this->assertSame( 'eo', $e->language );
			$this->assertSame( 'dewiki', $e->existingWiki );
			$this->assertSame( WikiMap::getCurrentWikiId(), $e->requestedWiki );
		}
	}

	public function testThrowsOnConflictWithOwnBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		// Sync this wiki's own base language into the table.
		LanguageStore::getWikiForLanguage( 'en' );

		try {
			LanguageStore::addProjectLanguage( $wiki, 'en' );
			$this->fail( 'Expected LanguageConflictException to be thrown' );
		} catch ( LanguageConflictException $e ) {
			$this->assertSame( 'en', $e->language );
			$this->assertSame( $wiki, $e->existingWiki );
			$this->assertSame( $wiki, $e->requestedWiki );
		}
	}
}
