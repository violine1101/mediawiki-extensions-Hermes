<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectPrefix;

use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\ProjectPrefix::unescape
 * @covers \MediaWiki\Extension\Hermes\ProjectPrefix::isReserved
 */
class UnescapeTest extends MediaWikiUnitTestCase {

	public function testUnescapeTitle() {
		$this->assertSame( 'Foo', ProjectPrefix::unescape( '!:Foo' ) );
		$this->assertNull( ProjectPrefix::unescape( 'Foo' ) );
		$this->assertNull( ProjectPrefix::unescape( '!eo:Foo' ) );
		$this->assertNull( ProjectPrefix::unescape( '!Foo' ) );
	}

	public function testIsReservedTitle() {
		$this->assertTrue( ProjectPrefix::isReserved( '!:Foo' ) );
		$this->assertTrue( ProjectPrefix::isReserved( '!eo:Foo' ) );
		$this->assertTrue( ProjectPrefix::isReserved( '!zz:Foo' ) );
		$this->assertFalse( ProjectPrefix::isReserved( '!Foo' ) );
		$this->assertFalse( ProjectPrefix::isReserved( 'Foo:Bar' ) );
	}
}
