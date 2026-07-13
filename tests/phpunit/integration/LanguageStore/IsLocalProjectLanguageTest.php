<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::isLocalProjectLanguage
 */
class IsLocalProjectLanguageTest extends HermesIntegrationTestCase {

	public function testTrueForRegisteredProject() {
		$wiki = WikiMap::getCurrentWikiId();
		LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertTrue( LanguageStore::isLocalProjectLanguage( 'eo' ) );
	}

	public function testFalseForBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertFalse( LanguageStore::isLocalProjectLanguage( 'en' ) );
	}

	public function testFalseForUnregistered() {
		$this->assertFalse( LanguageStore::isLocalProjectLanguage( 'zz' ) );
	}

	public function testFalseWhenRegisteredElsewhere() {
		LanguageStore::addProjectLanguage( 'otherwiki', 'eo' );

		$this->assertFalse( LanguageStore::isLocalProjectLanguage( 'eo' ) );
	}
}
