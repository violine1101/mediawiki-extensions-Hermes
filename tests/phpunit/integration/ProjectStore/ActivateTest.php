<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::activate
 */
class ActivateTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerTranslationProject( 'de', 'enwiki' );
		$this->registerBaseProject( 'de', 'dewiki', false );
	}

	public function testMovesActiveProject() {
		$this->assertTrue( ProjectStore::activate( 'de', 'dewiki' ) );

		$this->assertTrue( ProjectStore::getProject( 'de', 'dewiki' )->isActive );
		$this->assertFalse( ProjectStore::getProject( 'de', 'enwiki' )->isActive );
		ProjectStore::clearCacheForTesting();
		$this->assertSame( 'dewiki', ProjectStore::getActiveProject( 'de' )?->wiki );
		$this->assertFalse( ProjectStore::getProject( 'de', 'enwiki' )->isActive );
	}

	public function testAlreadyActive() {
		$this->assertFalse( ProjectStore::activate( 'de', 'enwiki' ) );

		$this->assertSame( 'enwiki', ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testThrowsWithoutProject() {
		$this->expectException( InvalidArgumentException::class );
		ProjectStore::activate( 'de', 'frwiki' );
	}
}
