<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::addProjectLanguage
 */
class AddProjectLanguageTest extends HermesIntegrationTestCase {

	public function testCoexistsWithBaseLanguage() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );
		$wiki = WikiMap::getCurrentWikiId();
		LanguageStore::addProjectLanguage( $wiki, 'eo' );

		$this->assertSame( 'en', LanguageStore::getLocalBaseLanguage() );
		$this->assertSame( $wiki, LanguageStore::getWikiForLanguage( 'eo' ) );
	}
}
