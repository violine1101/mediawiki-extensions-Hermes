<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\PageInfo::unescapeTitle
 * @covers \MediaWiki\Extension\Hermes\PageInfo::isReservedTitle
 */
class UnescapeTitleTest extends MediaWikiUnitTestCase {

	public function testUnescapeTitle() {
		$this->assertSame( 'Foo', PageInfo::unescapeTitle( '!:Foo' ) );
		$this->assertNull( PageInfo::unescapeTitle( 'Foo' ) );
		$this->assertNull( PageInfo::unescapeTitle( '!eo:Foo' ) );
		$this->assertNull( PageInfo::unescapeTitle( '!Foo' ) );
	}

	public function testIsReservedTitle() {
		$this->assertTrue( PageInfo::isReservedTitle( '!:Foo' ) );
		$this->assertTrue( PageInfo::isReservedTitle( '!eo:Foo' ) );
		$this->assertTrue( PageInfo::isReservedTitle( '!zz:Foo' ) );
		$this->assertFalse( PageInfo::isReservedTitle( '!Foo' ) );
		$this->assertFalse( PageInfo::isReservedTitle( 'Foo:Bar' ) );
	}
}
