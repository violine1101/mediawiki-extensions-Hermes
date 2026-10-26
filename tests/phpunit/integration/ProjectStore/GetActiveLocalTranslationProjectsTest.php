<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getActiveLocalTranslationProjects
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getLocalTranslationProjects
 */
class GetActiveLocalTranslationProjectsTest extends HermesIntegrationTestCase {

	public function testSkipsInactiveProjects() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'fr', $wiki );
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->registerTranslationProject( 'eo', $wiki );
		$this->registerTranslationProject( 'es', 'otherwiki' );

		$this->assertSame( [ 'eo', 'fr' ], array_keys( ProjectStore::getActiveLocalTranslationProjects() ) );
		$this->assertSame( [ 'de', 'eo', 'fr' ], array_keys( ProjectStore::getLocalTranslationProjects() ) );
	}
}
