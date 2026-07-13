<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getLanguage
 */
class GetLanguageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testBaseLanguageForOrdinaryPage() {
		$page = $this->getExistingTestPage( 'PageInfoGetLanguageOrdinary' );
		$info = PageInfo::fromLocalPage( $page->getTitle() );

		$this->assertSame( 'en', $info->getLanguage()->getCode() );
	}

	public function testProjectLanguageForProjectPage() {
		$title = Title::newFromText( '!eo:PageInfoGetLanguageProject' );
		$info = PageInfo::fromLocalPage( $title );

		$this->assertSame( 'eo', $info->getLanguage()->getCode() );
	}
}
