<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getProjectById
 * @covers \MediaWiki\Extension\Hermes\Project::hasId
 */
class GetProjectByIdTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerTranslationProject( 'de', 'db-mw_', false );
	}

	public function testActiveByCode() {
		$this->assertSame( 'dewiki', ProjectStore::getProjectById( 'de' )->wiki );
	}

	public function testByCodeAndWiki() {
		$this->assertSame( 'dewiki', ProjectStore::getProjectById( 'de@dewiki' )->wiki );
		$this->assertNull( ProjectStore::getProjectById( 'de@frwiki' ) );
		$this->assertNull( ProjectStore::getProjectById( 'fr' ) );
	}

	public function testWikiInTitleForm() {
		$this->assertSame( 'db-mw_', ProjectStore::getProjectById( 'de@db-mw' )->wiki );
	}
}
