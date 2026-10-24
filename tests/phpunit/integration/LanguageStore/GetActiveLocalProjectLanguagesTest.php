<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageStore;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getActiveLocalProjectLanguages
 * @covers \MediaWiki\Extension\Hermes\LanguageStore::getLocalProjectLanguages
 */
class GetActiveLocalProjectLanguagesTest extends HermesIntegrationTestCase {

	public function testSkipsInactiveProjects() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerProjectLanguage( $wiki, 'fr' );
		$this->registerProjectLanguage( $wiki, 'de', false );
		$this->registerProjectLanguage( $wiki, 'eo' );
		$this->registerProjectLanguage( 'otherwiki', 'es' );

		$this->assertSame( [ 'eo', 'fr' ], LanguageStore::getActiveLocalProjectLanguages() );
		$this->assertSame( [ 'de', 'eo', 'fr' ], LanguageStore::getLocalProjectLanguages() );
	}
}
