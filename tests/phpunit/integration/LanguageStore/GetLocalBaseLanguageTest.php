<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getLocalBaseLanguage
 */
class GetLocalBaseLanguageTest extends HermesIntegrationTestCase {

	public function testReturnsCurrentWikisLanguageCode() {
		$this->overrideConfigValue( 'LanguageCode', 'de' );

		$this->assertSame( 'de', LanguageStore::getLocalBaseLanguage() );
	}
}
