<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getLanguageCode
 */
class GetLanguageCodeTest extends HermesIntegrationTestCase {

	public function testFallsBackToWikiBaseLanguage() {
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId(), 'SomePage' );

		$this->assertSame( 'en', $page->getLanguageCode() );
	}

	public function testUsesTranslationProjectWhenSet() {
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId(), '!eo:SomePage' );

		$this->assertSame( 'eo', $page->getLanguageCode() );
	}
}
