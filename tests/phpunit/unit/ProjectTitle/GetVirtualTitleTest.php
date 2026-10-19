<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getVirtualTitle
 */
class GetVirtualTitleTest extends MediaWikiUnitTestCase {

	private static function makeTitle(
		string $namespaceText,
		string $title,
		?string $section = null,
		?string $project = null
	): ProjectTitle {
		// The namespace ID isn't used for the virtual title.
		return ProjectTitle::makeTitle( NS_MAIN, $title, $project, $section, $namespaceText );
	}

	public function testMainNamespace() {
		$page = self::makeTitle( '', 'Foo' );

		$this->assertSame( 'Foo', $page->getVirtualTitle() );
	}

	public function testNonMainNamespace() {
		$page = self::makeTitle( 'Category', 'Foo' );

		$this->assertSame( 'Category:Foo', $page->getVirtualTitle() );
	}

	public function testWithSection() {
		$page = self::makeTitle( '', 'Foo', 'Bar' );

		$this->assertSame( 'Foo#Bar', $page->getVirtualTitle() );
	}

	public function testUsesUnderscores() {
		$page = self::makeTitle( 'User talk', 'Foo Bar', 'Some Section' );

		$this->assertSame( 'User_talk:Foo_Bar#Some_Section', $page->getVirtualTitle() );
	}

	public function testNeverIncludesTranslationProjectPrefix() {
		// getVirtualTitle() deliberately never adds "!xx:" back - that's solely
		// Decorators\ProjectPageInterwiki's job on the interwiki side.
		$page = self::makeTitle( '', 'Foo', null, 'eo' );

		$this->assertSame( 'Foo', $page->getVirtualTitle() );
	}
}
