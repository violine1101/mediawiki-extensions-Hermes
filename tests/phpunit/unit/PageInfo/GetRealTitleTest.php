<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getRealTitle
 */
class GetRealTitleTest extends MediaWikiUnitTestCase {

	private static function makePageInfo(
		string $namespaceText,
		?string $translationProject,
		string $title
	): PageInfo {
		$page = new PageInfo();
		$page->namespaceText = $namespaceText;
		$page->translationProject = $translationProject;
		$page->title = $title;
		return $page;
	}

	public function testOrdinaryPage() {
		$page = self::makePageInfo( '', null, 'Foo' );

		$this->assertSame( 'Foo', $page->getRealTitle() );
	}

	public function testMainNamespaceProjectPage() {
		$page = self::makePageInfo( '', 'eo', 'Foo' );

		$this->assertSame( '!eo:Foo', $page->getRealTitle() );
	}

	public function testNamespacedProjectPage() {
		// The "!xx:" prefix goes after the namespace, not before it.
		$page = self::makePageInfo( 'Category', 'eo', 'Foo' );

		$this->assertSame( 'Category:!eo:Foo', $page->getRealTitle() );
	}

	public function testWithSection() {
		$page = self::makePageInfo( 'Category', 'eo', 'Foo' );
		$page->section = 'Bar';

		$this->assertSame( 'Category:!eo:Foo#Bar', $page->getRealTitle() );
	}

	public function testSpacesBecomeUnderscores() {
		// Fed straight to wfUrlencode() by Decorators\ProjectPageInterwiki with no
		// Title::newFromText() normalization in between, so spaces must already be "_" here,
		// not left for wfUrlencode() to turn into "+".
		$page = self::makePageInfo( 'User talk', 'eo', 'Foo Bar' );

		$this->assertSame( 'User_talk:!eo:Foo_Bar', $page->getRealTitle() );
	}

	public function testSpacesBecomeUnderscoresWithoutTranslationProject() {
		$page = self::makePageInfo( 'User talk', null, 'Foo Bar' );

		$this->assertSame( 'User_talk:Foo_Bar', $page->getRealTitle() );
	}
}
