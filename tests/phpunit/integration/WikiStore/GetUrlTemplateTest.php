<?php

namespace MediaWiki\Extension\Hermes\Tests\WikiStore;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Extension\Hermes\WikiStore;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\WikiStore::getUrlTemplate
 */
class GetUrlTemplateTest extends HermesIntegrationTestCase {

	public function testReturnsRegisteredWiki() {
		$this->registerWiki( 'dewiki', 'https://de.example.org/wiki/$1' );

		$this->assertSame( 'https://de.example.org/wiki/$1', WikiStore::getUrlTemplate( 'dewiki' ) );
	}

	public function testReturnsNullForUnregistered() {
		$this->assertNull( WikiStore::getUrlTemplate( 'unknownwiki' ) );
	}

	public function testSyncsCurrentWikiIntoDatabase() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		$this->assertSame(
			'https://example.org/wiki/$1',
			WikiStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}
}
