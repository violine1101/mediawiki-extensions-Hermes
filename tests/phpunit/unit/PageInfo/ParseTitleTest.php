<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::parseTitle
 */
class ParseTitleTest extends MediaWikiUnitTestCase {

	public function testOrdinaryText() {
		$this->assertSame( [ null, 'Foo' ], PageInfo::parseTitle( 'Foo' ) );
	}

	public function testEmptyText() {
		$this->assertSame( [ null, '' ], PageInfo::parseTitle( '' ) );
	}

	public function testWithoutColon() {
		$this->assertSame( [ null, '!eo' ], PageInfo::parseTitle( '!eo' ) );
	}

	public function testUppercaseLanguageCode() {
		$this->assertSame( [ null, '!EO:Foo' ], PageInfo::parseTitle( '!EO:Foo' ) );
	}

	public function testEmptyRemainder() {
		$this->assertSame( [ null, '!eo:' ], PageInfo::parseTitle( '!eo:' ) );
	}

	public function testBareBang() {
		$this->assertSame( [ null, '!' ], PageInfo::parseTitle( '!' ) );
	}
}
