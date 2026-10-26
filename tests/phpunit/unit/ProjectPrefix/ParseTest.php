<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectPrefix;

use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectPrefix::parse
 */
class ParseTest extends MediaWikiUnitTestCase {

	public function testOrdinaryText() {
		$this->assertSame( [ null, 'Foo' ], ProjectPrefix::parse( 'Foo' ) );
	}

	public function testEmptyText() {
		$this->assertSame( [ null, '' ], ProjectPrefix::parse( '' ) );
	}

	public function testWithoutColon() {
		$this->assertSame( [ null, '!eo' ], ProjectPrefix::parse( '!eo' ) );
	}

	public function testUppercaseLanguageCode() {
		$this->assertSame( [ null, '!EO:Foo' ], ProjectPrefix::parse( '!EO:Foo' ) );
	}

	public function testEmptyRemainder() {
		$this->assertSame( [ null, '!eo:' ], ProjectPrefix::parse( '!eo:' ) );
	}

	public function testBareBang() {
		$this->assertSame( [ null, '!' ], ProjectPrefix::parse( '!' ) );
	}
}
