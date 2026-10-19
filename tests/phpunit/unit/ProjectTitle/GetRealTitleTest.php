<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getRealTitle
 */
class GetRealTitleTest extends MediaWikiUnitTestCase {

	private static function makeTitle(
		string $namespaceText,
		?string $project,
		string $title,
		?string $section = null
	): ProjectTitle {
		// The namespace ID isn't used for the real title.
		return ProjectTitle::makeTitle( NS_MAIN, $title, $project, $section, $namespaceText );
	}

	public function testOrdinaryPage() {
		$page = self::makeTitle( '', null, 'Foo' );

		$this->assertSame( 'Foo', $page->getRealTitle() );
	}

	public function testMainNamespaceProjectPage() {
		$page = self::makeTitle( '', 'eo', 'Foo' );

		$this->assertSame( '!eo:Foo', $page->getRealTitle() );
	}

	public function testNamespacedProjectPage() {
		// The "!xx:" prefix goes after the namespace, not before it.
		$page = self::makeTitle( 'Category', 'eo', 'Foo' );

		$this->assertSame( 'Category:!eo:Foo', $page->getRealTitle() );
	}

	public function testWithSection() {
		$page = self::makeTitle( 'Category', 'eo', 'Foo', 'Bar' );

		$this->assertSame( 'Category:!eo:Foo#Bar', $page->getRealTitle() );
	}

	public function testSpacesBecomeUnderscores() {
		// Fed straight to wfUrlencode() by Decorators\ProjectPageInterwiki with no
		// Title::newFromText() normalization in between, so spaces must already be "_" here,
		// not left for wfUrlencode() to turn into "+".
		$page = self::makeTitle( 'User talk', 'eo', 'Foo Bar' );

		$this->assertSame( 'User_talk:!eo:Foo_Bar', $page->getRealTitle() );
	}

	public function testSpacesBecomeUnderscoresWithoutTranslationProject() {
		$page = self::makeTitle( 'User talk', null, 'Foo Bar' );

		$this->assertSame( 'User_talk:Foo_Bar', $page->getRealTitle() );
	}
}
