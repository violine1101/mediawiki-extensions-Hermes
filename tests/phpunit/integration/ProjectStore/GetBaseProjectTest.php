<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getBaseProject
 */
class GetBaseProjectTest extends HermesIntegrationTestCase {

	public function testKnownWiki() {
		$this->registerBaseProject( 'de', 'dewiki' );

		$this->assertSame( 'de', ProjectStore::getBaseProject( 'dewiki' )?->code );
	}

	public function testUnknownWiki() {
		$this->assertNull( ProjectStore::getBaseProject( 'unregisteredwiki' ) );
	}
}
