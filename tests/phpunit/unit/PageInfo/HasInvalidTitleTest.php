<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::hasInvalidTitle
 */
class HasInvalidTitleTest extends MediaWikiUnitTestCase {

	private static function makePageInfo( ?string $translationProject, ?string $title ): PageInfo {
		$page = new PageInfo();
		$page->translationProject = $translationProject;
		$page->title = $title;
		return $page;
	}

	public function testUnregisteredBangPrefix() {
		$page = self::makePageInfo( null, '!zz:Foo' );

		$this->assertTrue( $page->hasInvalidTitle() );
	}

	public function testRegisteredProjectTitle() {
		// A recognized "!xx:" prefix is stripped into $translationProject by parseTitle(),
		// leaving $title without its leading "!" - so this isn't just the inverse of the
		// unregistered-prefix case above.
		$page = self::makePageInfo( 'eo', 'Foo' );

		$this->assertFalse( $page->hasInvalidTitle() );
	}

	public function testOrdinaryTitle() {
		$page = self::makePageInfo( null, 'Foo' );

		$this->assertFalse( $page->hasInvalidTitle() );
	}

	public function testTitleUnavailable() {
		// $title is unavailable (null) for PageInfo objects loaded from the database.
		$page = self::makePageInfo( null, null );

		$this->assertFalse( $page->hasInvalidTitle() );
	}
}
