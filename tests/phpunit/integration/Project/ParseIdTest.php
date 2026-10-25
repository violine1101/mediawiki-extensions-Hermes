<?php

namespace MediaWiki\Extension\Hermes\Tests\Project;

use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Project::parseId
 */
class ParseIdTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'dewiki', 'de' );
		$this->registerTranslationProject( 'db-mw_', 'de', false );
	}

	public function testActiveByCode() {
		$this->assertSame( 'dewiki', Project::parseId( 'de' )->wiki );
	}

	public function testByCodeAndWiki() {
		$this->assertSame( 'dewiki', Project::parseId( 'de@dewiki' )->wiki );
		$this->assertNull( Project::parseId( 'de@frwiki' ) );
		$this->assertNull( Project::parseId( 'fr' ) );
	}

	public function testWikiInTitleForm() {
		$this->assertSame( 'db-mw_', Project::parseId( 'de@db-mw' )->wiki );
	}
}
