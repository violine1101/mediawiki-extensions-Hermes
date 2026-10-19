<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getError
 */
class GetErrorTest extends HermesIntegrationTestCase {

	public function testValidTitle() {
		$this->assertNull( ProjectTitle::makeTitle( NS_MAIN, 'Foo', 'eo' )->getError() );
		$this->assertNull( ProjectTitle::makeTitle( NS_MAIN, 'Foo' )->getError() );
	}

	public function testInvalidProjectTitle() {
		$this->assertSame( 'hermes-invalid-project-title', ProjectTitle::makeTitle( NS_MAIN, '!zz:Foo' )->getError() );
	}

	public function testCapitalizationError() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$title = ProjectTitle::makeTitle( NS_MAIN, 'foo', 'eo' );

		$this->assertSame( 'hermes-title-not-capitalized', $title->getError() );
	}
}
