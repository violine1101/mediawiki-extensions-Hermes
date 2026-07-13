<?php

namespace MediaWiki\Extension\Hermes\Tests\WikiStore;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Extension\Hermes\WikiStore;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\WikiStore::init
 */
class InitTest extends HermesIntegrationTestCase {

	public function testRegistersCurrentWikisUrl() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		WikiStore::init();

		$this->assertSame(
			'https://example.org/wiki/$1',
			WikiStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}

	public function testIsIdempotent() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );

		WikiStore::init();
		WikiStore::init();

		$this->assertSame(
			'https://example.org/wiki/$1',
			WikiStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}

	public function testUpdatesUrlWhenConfigChanges() {
		$this->overrideConfigValues( [
			'Server' => 'https://example.org',
			'ArticlePath' => '/wiki/$1',
		] );
		WikiStore::init();

		$this->overrideConfigValues( [
			'Server' => 'https://new.example.org',
			'ArticlePath' => '/wiki/$1',
		] );
		WikiStore::init();

		$this->assertSame(
			'https://new.example.org/wiki/$1',
			WikiStore::getUrlTemplate( WikiMap::getCurrentWikiId() )
		);
	}
}
