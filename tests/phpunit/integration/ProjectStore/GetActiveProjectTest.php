<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getActiveProject
 */
class GetActiveProjectTest extends HermesIntegrationTestCase {

	public function testUnknownLanguage() {
		$this->assertNull( ProjectStore::getActiveProject( 'xx' ) );
	}

	public function testReturnsWiki() {
		$this->registerBaseProject( 'de', 'dewiki' );

		$this->assertSame( 'dewiki', ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testReturnsActiveWiki() {
		$this->registerBaseProject( 'de', 'dewiki', false );
		$this->registerTranslationProject( 'de', 'otherwiki' );

		$this->assertSame( 'otherwiki', ProjectStore::getActiveProject( 'de' )?->wiki );
	}

	public function testNullWhenOnlyInactive() {
		$this->registerBaseProject( 'de', 'dewiki', false );

		$this->assertNull( ProjectStore::getActiveProject( 'de' ) );
	}
}
