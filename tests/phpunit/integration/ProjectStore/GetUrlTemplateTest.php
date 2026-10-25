<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getUrlTemplate
 */
class GetUrlTemplateTest extends HermesIntegrationTestCase {

	public function testReturnsKnownWiki() {
		$this->registerBaseProject( 'dewiki', 'de', url: 'https://de.example.org/wiki/$1' );

		$this->assertSame( 'https://de.example.org/wiki/$1', ProjectStore::getUrlTemplate( 'dewiki' ) );
	}

	public function testReturnsNullForUnregistered() {
		$this->assertNull( ProjectStore::getUrlTemplate( 'unknownwiki' ) );
	}

	public function testSyncsCurrentWikiIntoDatabase() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		$this->assertSame(
			'https://example.org/wiki/$1',
			ProjectStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}
}
