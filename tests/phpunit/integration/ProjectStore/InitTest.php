<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::init
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
		ProjectStore::init();
		ProjectStore::clearCacheForTesting();

		$this->assertSame( 'https://example.org/wiki/$1', ProjectStore::getUrlTemplate( $this->wiki ) );
		$this->assertSame( 'de', ProjectStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertFalse( ProjectStore::isActive( 'de', $this->wiki ) );
		$this->assertNull( ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testRegistersAlongsideOtherWikis() {
		$this->registerBaseProject( 'otherwiki', 'de' );

		ProjectStore::init();

		$this->assertSame( 'de', ProjectStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertSame( 'otherwiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testKeepsExistingBaseProject() {
		$this->registerBaseProject( $this->wiki, 'de' );

		ProjectStore::init();

		$this->assertTrue( ProjectStore::isActive( 'de', $this->wiki ) );
	}

	public function testKeepsBaseProjectWhenConfigChanges() {
		$this->registerBaseProject( $this->wiki, 'fr' );

		ProjectStore::init();

		$this->assertSame( 'fr', ProjectStore::getBaseLanguageForWiki( $this->wiki ) );
		$this->assertArrayNotHasKey( 'de', ProjectStore::getProjects() );
	}

	public function testUpdatesUrlOfAllRows() {
		$this->registerBaseProject( $this->wiki, 'de', url: 'https://old.example.org/wiki/$1' );
		$this->registerTranslationProject( $this->wiki, 'eo', url: 'https://old.example.org/wiki/$1' );

		ProjectStore::init();
		ProjectStore::clearCacheForTesting();

		foreach ( ProjectStore::getProjects() as $byWiki ) {
			$this->assertSame( 'https://example.org/wiki/$1', $byWiki[ $this->wiki ]->urlTemplate );
		}
	}

	public function testIsIdempotent() {
		ProjectStore::init();
		ProjectStore::init();

		$this->assertSame( 'https://example.org/wiki/$1', ProjectStore::getUrlTemplate( $this->wiki ) );
		$this->assertSame( 'de', ProjectStore::getBaseLanguageForWiki( $this->wiki ) );
	}

	public function testUpdatesUrlWhenConfigChanges() {
		ProjectStore::init();

		$this->overrideConfigValue( 'Server', 'https://new.example.org' );
		ProjectStore::init();
		ProjectStore::clearCacheForTesting();

		$this->assertSame( 'https://new.example.org/wiki/$1', ProjectStore::getUrlTemplate( $this->wiki ) );
	}
}
