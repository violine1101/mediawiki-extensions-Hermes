<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::hasCapitalizationError
 */
class HasCapitalizationErrorTest extends HermesIntegrationTestCase {

	private function makeProjectPage( string $title ): ProjectTitle {
		return self::localTitle( NS_MAIN, $title, 'eo' );
	}

	public function testLowercaseTitle() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$this->assertTrue( $this->makeProjectPage( 'foo' )->hasCapitalizationError() );
	}

	public function testCapitalizedTitle() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$this->assertFalse( $this->makeProjectPage( 'Foo' )->hasCapitalizationError() );
	}

	public function testCapitalLinksDisabled() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, false );

		$this->assertFalse( $this->makeProjectPage( 'foo' )->hasCapitalizationError() );
	}

	public function testNonProjectPage() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$page = self::localTitle( NS_MAIN, 'foo' );

		// Not a Hermes concern: core's own capitalization already handles this, since a
		// non-project title's first character is at the very start of the DB key.
		$this->assertFalse( $page->hasCapitalizationError() );
	}
}
