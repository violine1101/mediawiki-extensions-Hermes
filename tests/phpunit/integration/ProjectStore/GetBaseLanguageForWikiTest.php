<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getBaseLanguageForWiki
 */
class GetBaseLanguageForWikiTest extends HermesIntegrationTestCase {

	public function testKnownWiki() {
		$this->registerBaseProject( 'dewiki', 'de' );

		$this->assertSame( 'de', ProjectStore::getBaseLanguageForWiki( 'dewiki' ) );
	}

	public function testUnregisteredWiki() {
		$this->assertNull( ProjectStore::getBaseLanguageForWiki( 'unregisteredwiki' ) );
	}
}
