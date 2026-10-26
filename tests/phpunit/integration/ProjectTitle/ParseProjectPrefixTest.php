<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::parse
 */
class ParseProjectPrefixTest extends HermesIntegrationTestCase {

	public function testLocalProject() {
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );

		$project = self::getProject( 'eo', WikiMap::getCurrentWikiId() );
		$this->assertSame( [ $project, 'Foo' ], ProjectTitle::parse( '!eo:Foo' ) );
	}

	public function testInactiveProject() {
		$this->registerTranslationProject( 'eo', WikiMap::getCurrentWikiId(), false );

		$project = self::getProject( 'eo', WikiMap::getCurrentWikiId() );
		$this->assertSame( [ $project, 'Foo' ], ProjectTitle::parse( '!eo:Foo' ) );
	}

	public function testBaseProject() {
		$this->overrideConfigValue( 'LanguageCode', 'en' );

		$this->assertSame( [ null, '!en:Foo' ], ProjectTitle::parse( '!en:Foo' ) );
	}

	public function testUnknownLanguage() {
		$this->assertSame( [ null, '!zz:Foo' ], ProjectTitle::parse( '!zz:Foo' ) );
	}

	public function testProjectElsewhere() {
		ProjectStore::addTranslationProject( 'eo', 'otherwiki' );

		$this->assertSame( [ null, '!eo:Foo' ], ProjectTitle::parse( '!eo:Foo' ) );
	}
}
