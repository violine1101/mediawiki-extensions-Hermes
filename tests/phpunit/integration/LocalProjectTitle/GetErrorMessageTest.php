<?php

namespace MediaWiki\Extension\Hermes\Tests\LocalProjectTitle;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LocalProjectTitle::getErrorMessage
 */
class GetErrorMessageTest extends HermesIntegrationTestCase {

	public function testValidTitle() {
		$this->assertNull( self::localTitle( NS_MAIN, 'Foo', 'eo' )->getErrorMessage() );
		$this->assertNull( self::localTitle( NS_MAIN, 'Foo' )->getErrorMessage() );
	}

	public function testInvalidProjectTitle() {
		$this->assertSame( 'hermes-invalid-project-title', self::localTitle( NS_MAIN, '!zz:Foo' )->getErrorMessage() );
	}

	public function testCapitalizationError() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$title = self::localTitle( NS_MAIN, 'foo', 'eo' );

		$this->assertSame( 'hermes-title-not-capitalized', $title->getErrorMessage() );
	}
}
