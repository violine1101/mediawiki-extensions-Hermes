<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::parse
 */
class ParseTest extends MediaWikiUnitTestCase {

	public function testOrdinaryText() {
		$this->assertSame( [ null, 'Foo' ], ProjectTitle::parse( 'Foo' ) );
	}

	public function testEmptyText() {
		$this->assertSame( [ null, '' ], ProjectTitle::parse( '' ) );
	}

	public function testWithoutColon() {
		$this->assertSame( [ null, '!eo' ], ProjectTitle::parse( '!eo' ) );
	}

	public function testUppercaseLanguageCode() {
		$this->assertSame( [ null, '!EO:Foo' ], ProjectTitle::parse( '!EO:Foo' ) );
	}

	public function testEmptyRemainder() {
		$this->assertSame( [ null, '!eo:' ], ProjectTitle::parse( '!eo:' ) );
	}

	public function testBareBang() {
		$this->assertSame( [ null, '!' ], ProjectTitle::parse( '!' ) );
	}
}
