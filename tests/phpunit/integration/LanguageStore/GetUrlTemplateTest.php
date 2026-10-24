<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getUrlTemplate
 */
class GetUrlTemplateTest extends HermesIntegrationTestCase {

	public function testReturnsRegisteredWiki() {
		$this->registerBaseLanguage( 'dewiki', 'de', url: 'https://de.example.org/wiki/$1' );

		$this->assertSame( 'https://de.example.org/wiki/$1', LanguageStore::getUrlTemplate( 'dewiki' ) );
	}

	public function testReturnsNullForUnregistered() {
		$this->assertNull( LanguageStore::getUrlTemplate( 'unknownwiki' ) );
	}

	public function testSyncsCurrentWikiIntoDatabase() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		$this->assertSame(
			'https://example.org/wiki/$1',
			LanguageStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}
}
