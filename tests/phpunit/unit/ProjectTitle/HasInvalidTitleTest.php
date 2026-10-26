<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::hasInvalidTitle
 */
class HasInvalidTitleTest extends MediaWikiUnitTestCase {

	private static function makeTitle( ?string $language, string $title ): ProjectTitle {
		$project = new Project( $language ?? 'en', 'testwiki', $language === null, true, null );
		return ProjectTitle::makeTitle( $project, NS_MAIN, $title );
	}

	public function testUnregisteredBangPrefix() {
		$page = self::makeTitle( null, '!zz:Foo' );

		$this->assertTrue( $page->hasInvalidTitle() );
	}

	public function testBangPrefixWithoutColonIsAllowed() {
		$page = self::makeTitle( null, '!Foo' );

		$this->assertFalse( $page->hasInvalidTitle() );
	}

	public function testEscapePrefix() {
		$page = self::makeTitle( null, '!:Foo' );

		$this->assertTrue( $page->hasInvalidTitle() );
	}

	public function testRegisteredProjectTitle() {
		// A recognized "!xx:" prefix is stripped into $project by ProjectTitle::parse(),
		// leaving $baseTitle without its leading "!" - so this isn't just the inverse of the
		// unregistered-prefix case above.
		$page = self::makeTitle( 'eo', 'Foo' );

		$this->assertFalse( $page->hasInvalidTitle() );
	}

	public function testOrdinaryTitle() {
		$page = self::makeTitle( null, 'Foo' );

		$this->assertFalse( $page->hasInvalidTitle() );
	}
}
