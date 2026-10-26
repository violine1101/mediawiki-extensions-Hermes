<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use InvalidArgumentException;
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
		$isNew = ProjectStore::addTranslationProject( 'eo', $wiki );

		$this->assertTrue( $isNew );
		$this->assertSame( 'en', ProjectStore::getLocalBaseLanguage() );
		$this->assertSame( $wiki, ProjectStore::getActiveProject( 'eo' )?->wiki );
	}

	public function testAllowsAddingTwice() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'eo', $wiki );

		$isNew = ProjectStore::addTranslationProject( 'eo', $wiki );

		$this->assertFalse( $isNew );
		$this->assertSame( $wiki, ProjectStore::getActiveProject( 'eo' )?->wiki );
	}

	public function testInactiveWhenActiveElsewhere() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerTranslationProject( 'eo', 'frwiki' );
		$wiki = WikiMap::getCurrentWikiId();

		$this->assertTrue( ProjectStore::addTranslationProject( 'de', $wiki ) );
		$this->assertTrue( ProjectStore::addTranslationProject( 'eo', $wiki ) );

		$this->assertArrayHasKey( 'de', ProjectStore::getLocalTranslationProjects() );
		$this->assertFalse( ProjectStore::getProject( 'de', $wiki )->isActive );
		$this->assertFalse( ProjectStore::getProject( 'eo', $wiki )->isActive );
		$this->assertSame( 'dewiki', ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testActiveWhenOnlyInactiveElsewhere() {
		$this->registerBaseProject( 'de', 'dewiki', false );

		ProjectStore::addTranslationProject( 'de', WikiMap::getCurrentWikiId() );

		$this->assertSame( WikiMap::getCurrentWikiId(), ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testThrowsOnConflictWithOwnBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		// Sync this wiki's own base project into the table.
		ProjectStore::init();

		$this->expectException( InvalidArgumentException::class );
		ProjectStore::addTranslationProject( 'en', $wiki );
	}
}
