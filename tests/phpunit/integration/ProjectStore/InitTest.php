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
		$this->assertSame( 'de', ProjectStore::getBaseProject( $this->wiki )?->code );
		$this->assertFalse( ProjectStore::getProject( 'de', $this->wiki )->isActive );
		$this->assertNull( ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testRegistersAlongsideOtherWikis() {
		$this->registerBaseProject( 'de', 'otherwiki' );

		ProjectStore::init();

		$this->assertSame( 'de', ProjectStore::getBaseProject( $this->wiki )?->code );
		$this->assertSame( 'otherwiki', ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testKeepsExistingBaseProject() {
		$this->registerBaseProject( 'de', $this->wiki );

		ProjectStore::init();

		$this->assertTrue( ProjectStore::getProject( 'de', $this->wiki )->isActive );
	}

	public function testKeepsBaseProjectWhenConfigChanges() {
		$this->registerBaseProject( 'fr', $this->wiki );

		ProjectStore::init();

		$this->assertSame( 'fr', ProjectStore::getBaseProject( $this->wiki )?->code );
		$this->assertArrayNotHasKey( 'de', ProjectStore::getProjects() );
	}

	public function testUpdatesUrlOfAllRows() {
		$this->registerBaseProject( 'de', $this->wiki, url: 'https://old.example.org/wiki/$1' );
		$this->registerTranslationProject( 'eo', $this->wiki, url: 'https://old.example.org/wiki/$1' );

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
		$this->assertSame( 'de', ProjectStore::getBaseProject( $this->wiki )?->code );
	}

	public function testUpdatesUrlWhenConfigChanges() {
		ProjectStore::init();

		$this->overrideConfigValue( 'Server', 'https://new.example.org' );
		ProjectStore::init();
		ProjectStore::clearCacheForTesting();

		$this->assertSame( 'https://new.example.org/wiki/$1', ProjectStore::getUrlTemplate( $this->wiki ) );
	}
}
