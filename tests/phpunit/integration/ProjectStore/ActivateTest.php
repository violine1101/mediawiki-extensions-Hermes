<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::activate
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::isActive
 */
class ActivateTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerTranslationProject( 'enwiki', 'de' );
		$this->registerBaseProject( 'dewiki', 'de', false );
	}

	public function testMovesActiveProject() {
		$this->assertTrue( ProjectStore::activate( 'de', 'dewiki' ) );

		$this->assertTrue( ProjectStore::isActive( 'de', 'dewiki' ) );
		$this->assertFalse( ProjectStore::isActive( 'de', 'enwiki' ) );
		ProjectStore::clearCacheForTesting();
		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
		$this->assertFalse( ProjectStore::isActive( 'de', 'enwiki' ) );
	}

	public function testAlreadyActive() {
		$this->assertFalse( ProjectStore::activate( 'de', 'enwiki' ) );

		$this->assertSame( 'enwiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testThrowsWithoutProject() {
		$this->expectException( InvalidArgumentException::class );
		ProjectStore::activate( 'de', 'frwiki' );
	}
}
