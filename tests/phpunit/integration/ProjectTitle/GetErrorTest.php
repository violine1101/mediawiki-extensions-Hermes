<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getError
 */
class GetErrorTest extends HermesIntegrationTestCase {

	public function testValidTitle() {
		$this->assertNull( self::localTitle( NS_MAIN, 'Foo', 'eo' )->getError() );
		$this->assertNull( self::localTitle( NS_MAIN, 'Foo' )->getError() );
	}

	public function testInvalidProjectTitle() {
		$this->assertSame( 'hermes-invalid-project-title', self::localTitle( NS_MAIN, '!zz:Foo' )->getError() );
	}

	public function testCapitalizationError() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$title = self::localTitle( NS_MAIN, 'foo', 'eo' );

		$this->assertSame( 'hermes-title-not-capitalized', $title->getError() );
	}
}
