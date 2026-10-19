<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::unescape
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::isReserved
 */
class UnescapeTest extends MediaWikiUnitTestCase {

	public function testUnescapeTitle() {
		$this->assertSame( 'Foo', ProjectTitle::unescape( '!:Foo' ) );
		$this->assertNull( ProjectTitle::unescape( 'Foo' ) );
		$this->assertNull( ProjectTitle::unescape( '!eo:Foo' ) );
		$this->assertNull( ProjectTitle::unescape( '!Foo' ) );
	}

	public function testIsReservedTitle() {
		$this->assertTrue( ProjectTitle::isReserved( '!:Foo' ) );
		$this->assertTrue( ProjectTitle::isReserved( '!eo:Foo' ) );
		$this->assertTrue( ProjectTitle::isReserved( '!zz:Foo' ) );
		$this->assertFalse( ProjectTitle::isReserved( '!Foo' ) );
		$this->assertFalse( ProjectTitle::isReserved( 'Foo:Bar' ) );
	}
}
