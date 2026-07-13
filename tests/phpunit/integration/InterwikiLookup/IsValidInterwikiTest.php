<?php

namespace MediaWiki\Extension\Hermes\Tests\InterwikiLookup;

use MediaWiki\Extension\Hermes\Decorators\InterwikiLookup;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Interwiki\InterwikiLookup as IInterwikiLookup;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Decorators\InterwikiLookup::isValidInterwiki
 */
class IsValidInterwikiTest extends HermesIntegrationTestCase {

	public function testTrueForRegisteredLanguage() {
		$this->registerBaseLanguage( 'frwiki', 'fr' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = new InterwikiLookup( $inner, [ 'frwiki' => 'https://fr.example.org/wiki/$1' ] );

		$this->assertTrue( $lookup->isValidInterwiki( 'fr' ) );
	}
}
