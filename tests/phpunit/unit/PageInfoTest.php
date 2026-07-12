<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo
 */
class PageInfoTest extends MediaWikiUnitTestCase {

	public function testParseTitleReturnsUnchangedForOrdinaryText() {
		$this->assertSame( [ null, 'Foo' ], PageInfo::parseTitle( 'Foo' ) );
	}

	public function testParseTitleReturnsUnchangedForEmptyText() {
		$this->assertSame( [ null, '' ], PageInfo::parseTitle( '' ) );
	}

	public function testParseTitleReturnsUnchangedWithoutColon() {
		$this->assertSame( [ null, '!eo' ], PageInfo::parseTitle( '!eo' ) );
	}

	public function testParseTitleReturnsUnchangedForUppercaseLanguageCode() {
		$this->assertSame( [ null, '!EO:Foo' ], PageInfo::parseTitle( '!EO:Foo' ) );
	}

	public function testParseTitleReturnsUnchangedForEmptyRemainder() {
		$this->assertSame( [ null, '!eo:' ], PageInfo::parseTitle( '!eo:' ) );
	}

	public function testParseTitleReturnsUnchangedForBareBang() {
		$this->assertSame( [ null, '!' ], PageInfo::parseTitle( '!' ) );
	}
}
