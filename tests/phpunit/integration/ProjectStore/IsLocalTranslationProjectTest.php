<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::isLocalTranslationProject
 */
class IsLocalTranslationProjectTest extends HermesIntegrationTestCase {

	public function testTrueForLocalProject() {
		$wiki = WikiMap::getCurrentWikiId();
		ProjectStore::addTranslationProject( $wiki, 'eo' );

		$this->assertTrue( ProjectStore::isLocalTranslationProject( 'eo' ) );
	}

	public function testTrueForInactiveProject() {
		$this->registerTranslationProject( WikiMap::getCurrentWikiId(), 'eo', false );

		$this->assertTrue( ProjectStore::isLocalTranslationProject( 'eo' ) );
	}

	public function testFalseForBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertFalse( ProjectStore::isLocalTranslationProject( 'en' ) );
	}

	public function testFalseForUnregistered() {
		$this->assertFalse( ProjectStore::isLocalTranslationProject( 'zz' ) );
	}

	public function testFalseForProjectElsewhere() {
		ProjectStore::addTranslationProject( 'otherwiki', 'eo' );

		$this->assertFalse( ProjectStore::isLocalTranslationProject( 'eo' ) );
	}
}
