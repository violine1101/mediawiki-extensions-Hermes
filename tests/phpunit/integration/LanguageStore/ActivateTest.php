<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::activate
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::isActive
 */
class ActivateTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerProjectLanguage( 'enwiki', 'de' );
		$this->registerBaseLanguage( 'dewiki', 'de', false );
	}

	public function testMovesActiveLanguage() {
		$this->assertTrue( LanguageStore::activate( 'de', 'dewiki' ) );

		$this->assertTrue( LanguageStore::isActive( 'de', 'dewiki' ) );
		$this->assertFalse( LanguageStore::isActive( 'de', 'enwiki' ) );
		LanguageStore::clearCacheForTesting();
		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
		$this->assertFalse( LanguageStore::isActive( 'de', 'enwiki' ) );
	}

	public function testAlreadyActive() {
		$this->assertFalse( LanguageStore::activate( 'de', 'enwiki' ) );

		$this->assertSame( 'enwiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testThrowsWhenNotRegistered() {
		$this->expectException( InvalidArgumentException::class );
		LanguageStore::activate( 'de', 'frwiki' );
	}
}
