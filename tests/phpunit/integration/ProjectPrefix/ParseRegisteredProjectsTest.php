<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectPrefix;

use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectPrefix::parse
 */
class ParseRegisteredProjectsTest extends HermesIntegrationTestCase {

	public function testLocalProject() {
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );

		$project = self::getProject( 'eo', WikiMap::getCurrentWikiId() );
		$this->assertSame( [ $project, 'Foo' ], ProjectPrefix::parse( '!eo:Foo' ) );
	}

	public function testInactiveProject() {
		$this->registerTranslationProject( 'eo', WikiMap::getCurrentWikiId(), false );

		$project = self::getProject( 'eo', WikiMap::getCurrentWikiId() );
		$this->assertSame( [ $project, 'Foo' ], ProjectPrefix::parse( '!eo:Foo' ) );
	}

	public function testBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertSame( [ null, '!en:Foo' ], ProjectPrefix::parse( '!en:Foo' ) );
	}

	public function testUnknownLanguage() {
		$this->assertSame( [ null, '!zz:Foo' ], ProjectPrefix::parse( '!zz:Foo' ) );
	}

	public function testProjectElsewhere() {
		ProjectStore::addTranslationProject( 'eo', 'otherwiki' );

		$this->assertSame( [ null, '!eo:Foo' ], ProjectPrefix::parse( '!eo:Foo' ) );
	}
}
