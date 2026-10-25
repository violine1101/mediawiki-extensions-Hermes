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
		$this->registerTranslationProject( $wiki, 'fr' );
		$this->registerTranslationProject( $wiki, 'de', false );
		$this->registerTranslationProject( $wiki, 'eo' );
		$this->registerTranslationProject( 'otherwiki', 'es' );

		$this->assertSame( [ 'eo', 'fr' ], ProjectStore::getActiveLocalTranslationProjects() );
		$this->assertSame( [ 'de', 'eo', 'fr' ], ProjectStore::getLocalTranslationProjects() );
	}
}
