<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getLocalBaseLanguage
 */
class GetLocalBaseLanguageTest extends HermesIntegrationTestCase {

	public function testReturnsCurrentWikisLanguageCode() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->assertSame( 'de', ProjectStore::getLocalBaseLanguage() );
	}
}
