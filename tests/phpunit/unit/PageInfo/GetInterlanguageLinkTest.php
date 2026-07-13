<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getInterlanguageLink
 */
class GetInterlanguageLinkTest extends MediaWikiUnitTestCase {

	private static function makePageInfo(): PageInfo {
		$page = new PageInfo();
		$page->translationProject = 'de';
		$page->title = 'Foo';
		return $page;
	}

	public function testWithoutSection() {
		$page = self::makePageInfo();

		$this->assertSame( 'de:Foo', $page->getInterlanguageLink() );
	}

	public function testWithSection() {
		$page = self::makePageInfo();
		$page->section = 'Some_Section';

		$this->assertSame( 'de:Foo#Some_Section', $page->getInterlanguageLink() );
	}

	public function testNeverIncludesTranslationProjectPrefix() {
		// getInterlanguageLink() is built from getVirtualTitle(), which deliberately never adds
		// "!xx:" back - that's solely Decorators\ProjectPageInterwiki's job.
		$page = new PageInfo();
		$page->translationProject = 'eo';
		$page->title = 'Foo';

		$this->assertSame( 'eo:Foo', $page->getInterlanguageLink() );
	}
}
