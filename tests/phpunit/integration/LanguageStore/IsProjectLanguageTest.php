<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::isProjectLanguage
 */
class IsProjectLanguageTest extends HermesIntegrationTestCase {

	public function testTrueForRegisteredProject() {
		$wiki = WikiMap::getCurrentWikiId();
		LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertTrue( LanguageStore::isProjectLanguage( 'eo' ) );
	}

	public function testFalseForBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertFalse( LanguageStore::isProjectLanguage( 'en' ) );
	}

	public function testFalseForUnregistered() {
		$this->assertFalse( LanguageStore::isProjectLanguage( 'zz' ) );
	}

	public function testFalseWhenRegisteredElsewhere() {
		LanguageStore::addProjectLanguage( 'otherwiki', 'eo' );

		$this->assertFalse( LanguageStore::isProjectLanguage( 'eo' ) );
	}
}
