<?php

namespace MediaWiki\Extension\Hermes\Tests\Project;

use MediaWiki\Extension\Hermes\Project;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Project::toId
 */
class ToIdTest extends MediaWikiUnitTestCase {

	public function testActive() {
		$this->assertSame( 'de', ( new Project( 'de', 'dewiki', true, true, null ) )->toId() );
	}

	public function testInactive() {
		$this->assertSame( 'de@dewiki', ( new Project( 'de', 'dewiki', false, false, null ) )->toId() );
	}

	public function testWikiInTitleForm() {
		$this->assertSame( 'de@db-mw', ( new Project( 'de', 'db-mw_', false, false, null ) )->toId() );
		$this->assertSame( 'de@db_mw', ( new Project( 'de', 'db__mw', false, false, null ) )->toId() );
	}
}
