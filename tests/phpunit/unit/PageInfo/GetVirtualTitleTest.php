<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getVirtualTitle
 */
class GetVirtualTitleTest extends MediaWikiUnitTestCase {

	private static function makePageInfo( string $namespaceText, string $title ): PageInfo {
		$page = new PageInfo();
		$page->namespaceText = $namespaceText;
		$page->title = $title;
		return $page;
	}

	public function testMainNamespace() {
		$page = self::makePageInfo( '', 'Foo' );

		$this->assertSame( 'Foo', $page->getVirtualTitle() );
	}

	public function testNonMainNamespace() {
		$page = self::makePageInfo( 'Category', 'Foo' );

		$this->assertSame( 'Category:Foo', $page->getVirtualTitle() );
	}

	public function testWithSection() {
		$page = self::makePageInfo( '', 'Foo' );
		$page->section = 'Bar';

		$this->assertSame( 'Foo#Bar', $page->getVirtualTitle() );
	}

	public function testNeverIncludesTranslationProjectPrefix() {
		// getVirtualTitle() deliberately never adds "!xx:" back - that's solely
		// Decorators\ProjectPageInterwiki's job on the interwiki side.
		$page = self::makePageInfo( '', 'Foo' );
		$page->translationProject = 'eo';

		$this->assertSame( 'Foo', $page->getVirtualTitle() );
	}
}
