<?php

namespace MediaWiki\Extension\Hermes\Tests\HermesLanguage;

use MediaWiki\Extension\Hermes\HermesLanguage;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\HermesLanguage::parseId
 */
class ParseIdTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerProjectLanguage( 'db-mw_', 'de', false );
	}

	public function testActiveByCode() {
		$this->assertSame( 'dewiki', HermesLanguage::parseId( 'de' )->wiki );
	}

	public function testByCodeAndWiki() {
		$this->assertSame( 'dewiki', HermesLanguage::parseId( 'de@dewiki' )->wiki );
		$this->assertNull( HermesLanguage::parseId( 'de@frwiki' ) );
		$this->assertNull( HermesLanguage::parseId( 'fr' ) );
	}

	public function testWikiInTitleForm() {
		$this->assertSame( 'db-mw_', HermesLanguage::parseId( 'de@db-mw' )->wiki );
	}
}
