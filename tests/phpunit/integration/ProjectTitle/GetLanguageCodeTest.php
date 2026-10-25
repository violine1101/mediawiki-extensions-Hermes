<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getLanguageCode
 */
class GetLanguageCodeTest extends HermesIntegrationTestCase {

	public function testFallsBackToWikiBaseLanguage() {
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId(), 'SomePage' );

		$this->assertSame( 'en', $page->title->getLanguageCode() );
	}

	public function testFallsBackToContentLanguageForUnregisteredWiki() {
		$page = $this->makePageInfo( 'unregistered_wiki', 'SomePage' );

		$this->assertSame( 'en', $page->title->getLanguageCode() );
	}

	public function testUsesTranslationProjectWhenSet() {
		$this->registerTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId(), '!eo:SomePage' );

		$this->assertSame( 'eo', $page->title->getLanguageCode() );
	}
}
