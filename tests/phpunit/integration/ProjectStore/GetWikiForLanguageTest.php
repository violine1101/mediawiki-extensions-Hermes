<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getWikiForLanguage
 */
class GetWikiForLanguageTest extends HermesIntegrationTestCase {

	public function testUnregistered() {
		$this->assertNull( ProjectStore::getWikiForLanguage( 'xx' ) );
	}

	public function testReturnsWiki() {
		$this->registerBaseProject( 'dewiki', 'de' );

		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testReturnsActiveWiki() {
		$this->registerBaseProject( 'dewiki', 'de', false );
		$this->registerTranslationProject( 'otherwiki', 'de' );

		$this->assertSame( 'otherwiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testNullWhenOnlyInactive() {
		$this->registerBaseProject( 'dewiki', 'de', false );

		$this->assertNull( ProjectStore::getWikiForLanguage( 'de' ) );
	}
}
