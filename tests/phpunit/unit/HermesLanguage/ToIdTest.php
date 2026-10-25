<?php

namespace MediaWiki\Extension\Hermes\Tests\HermesLanguage;

use MediaWiki\Extension\Hermes\HermesLanguage;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\HermesLanguage::toId
 */
class ToIdTest extends MediaWikiUnitTestCase {

	public function testActive() {
		$this->assertSame( 'de', ( new HermesLanguage( 'de', 'dewiki', true, true, null ) )->toId() );
	}

	public function testInactive() {
		$this->assertSame( 'de@dewiki', ( new HermesLanguage( 'de', 'dewiki', false, false, null ) )->toId() );
	}

	public function testWikiInTitleForm() {
		$this->assertSame( 'de@db-mw', ( new HermesLanguage( 'de', 'db-mw_', false, false, null ) )->toId() );
		$this->assertSame( 'de@db_mw', ( new HermesLanguage( 'de', 'db__mw', false, false, null ) )->toId() );
	}
}
