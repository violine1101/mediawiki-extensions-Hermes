<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore
 */
class LanguageStoreTest extends MediaWikiIntegrationTestCase {

	public function testGetWikiForLanguageReturnsNullWhenUnregistered() {
		$this->assertNull( LanguageStore::getWikiForLanguage( 'xx' ) );
	}

	public function testGetLanguageForWikiReturnsNullWhenUnregistered() {
		$this->assertNull( LanguageStore::getLanguageForWiki( 'xxwiki' ) );
	}

	public function testSetLanguageThenGetWikiForLanguage() {
		LanguageStore::setLanguage( 'dewiki', 'de' );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testSetLanguageThenGetLanguageForWiki() {
		LanguageStore::setLanguage( 'dewiki', 'de' );

		$this->assertSame( 'de', LanguageStore::getLanguageForWiki( 'dewiki' ) );
	}

	public function testSetLanguageOverwritesPreviousWikiForSameLanguage() {
		LanguageStore::setLanguage( 'olddewiki', 'de' );
		LanguageStore::setLanguage( 'newdewiki', 'de' );

		$this->assertSame( 'newdewiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}
}
