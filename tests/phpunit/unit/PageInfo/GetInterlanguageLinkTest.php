<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getInterlanguageLink
 */
class GetInterlanguageLinkTest extends MediaWikiUnitTestCase {

	private static function makePageInfo( string $project, ?string $section = null ): PageInfo {
		return new PageInfo( ProjectTitle::makeTitleOnWiki( 'somewiki', NS_MAIN, 'Foo', $project, $section, '' ) );
	}

	public function testWithoutSection() {
		$page = self::makePageInfo( 'de' );

		$this->assertSame( 'de:Foo', $page->getInterlanguageLink() );
	}

	public function testWithSection() {
		$page = self::makePageInfo( 'de', 'Some_Section' );

		$this->assertSame( 'de:Foo#Some_Section', $page->getInterlanguageLink() );
	}

	public function testNeverIncludesTranslationProjectPrefix() {
		// getInterlanguageLink() is built from getVirtualTitle(), which deliberately never adds
		// "!xx:" back - that's solely Decorators\ProjectPageInterwiki's job.
		$page = self::makePageInfo( 'eo' );

		$this->assertSame( 'eo:Foo', $page->getInterlanguageLink() );
	}
}
