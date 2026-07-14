<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::hasCapitalizationError
 */
class HasCapitalizationErrorTest extends HermesIntegrationTestCase {

	private function makeProjectPage( string $title ): PageInfo {
		$page = new PageInfo();
		$page->namespace = NS_MAIN;
		$page->translationProject = 'eo';
		$page->title = $title;
		return $page;
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

		$page = new PageInfo();
		$page->namespace = NS_MAIN;
		$page->translationProject = null;
		$page->title = 'foo';

		// Not a Hermes concern: core's own capitalization already handles this, since a
		// non-project title's first character is at the very start of the DB key.
		$this->assertFalse( $page->hasCapitalizationError() );
	}
}
