<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getUrl
 */
class GetUrlTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'en', 'trwiki', url: 'https://translate.example.org/wiki/$1' );
		$this->registerTranslationProject( 'eo', 'trwiki', url: 'https://translate.example.org/wiki/$1' );
	}

	public function testBasePage() {
		$title = ProjectTitle::makeTitle( self::getProject( null, 'trwiki' ), NS_MAIN, 'Foo_bar', '' );

		$this->assertSame( 'https://translate.example.org/wiki/Foo_bar', $title->getUrl() );
	}

	public function testProjectPrefixGoesAfterNamespace() {
		$title = ProjectTitle::makeTitle( self::getProject( 'eo', 'trwiki' ), NS_CATEGORY, 'Foo', 'Category' );

		$this->assertSame( 'https://translate.example.org/wiki/Category:!eo:Foo', $title->getUrl() );
	}

	public function testSectionBecomesEscapedFragment() {
		$title = ProjectTitle::makeTitle( self::getProject( 'eo', 'trwiki' ), NS_MAIN, 'Foo', '' );

		$this->assertSame(
			'https://translate.example.org/wiki/!eo:Foo#Some_section?', $title->getUrl( 'Some_section?' )
		);
	}

	public function testTitleIsUrlEncoded() {
		$title = ProjectTitle::makeTitle( self::getProject( null, 'trwiki' ), NS_MAIN, 'What?_Ŝtono', '' );

		$this->assertSame( 'https://translate.example.org/wiki/What%3F_%C5%9Ctono', $title->getUrl() );
	}

	public function testWithoutUrlTemplate() {
		$project = new Project( 'de', 'otherwiki', true, true, null );

		$this->assertNull( ProjectTitle::makeTitle( $project, NS_MAIN, 'Foo', '' )->getUrl() );
	}
}
