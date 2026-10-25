<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::addTranslationProject
 */
class AddTranslationProjectTest extends HermesIntegrationTestCase {

	public function testCoexistsWithBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		$isNew = ProjectStore::addTranslationProject( $wiki, 'eo' );

		$this->assertTrue( $isNew );
		$this->assertSame( 'en', ProjectStore::getLocalBaseLanguage() );
		$this->assertSame( $wiki, ProjectStore::getWikiForLanguage( 'eo' ) );
	}

	public function testAllowsAddingTwice() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( $wiki, 'eo' );

		$isNew = ProjectStore::addTranslationProject( $wiki, 'eo' );

		$this->assertFalse( $isNew );
		$this->assertSame( $wiki, ProjectStore::getWikiForLanguage( 'eo' ) );
	}

	public function testInactiveWhenActiveElsewhere() {
		$this->registerBaseProject( 'dewiki', 'de' );
		$this->registerTranslationProject( 'frwiki', 'eo' );
		$wiki = WikiMap::getCurrentWikiId();

		$this->assertTrue( ProjectStore::addTranslationProject( $wiki, 'de' ) );
		$this->assertTrue( ProjectStore::addTranslationProject( $wiki, 'eo' ) );

		$this->assertTrue( ProjectStore::isLocalTranslationProject( 'de' ) );
		$this->assertFalse( ProjectStore::isActive( 'de', $wiki ) );
		$this->assertFalse( ProjectStore::isActive( 'eo', $wiki ) );
		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testActiveWhenOnlyInactiveElsewhere() {
		$this->registerBaseProject( 'dewiki', 'de', false );

		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'de' );

		$this->assertSame( WikiMap::getCurrentWikiId(), ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testThrowsOnConflictWithOwnBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		// Sync this wiki's own base project into the table.
		ProjectStore::getWikiForLanguage( 'en' );

		try {
			ProjectStore::addTranslationProject( $wiki, 'en' );
			$this->fail( 'Expected LanguageConflictException to be thrown' );
		} catch ( LanguageConflictException $e ) {
			$this->assertSame( 'en', $e->language );
			$this->assertSame( $wiki, $e->existingWiki );
			$this->assertSame( $wiki, $e->requestedWiki );
		}
	}
}
