<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getUrl
 */
class GetUrlTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );
	}

	public function testBasePage() {
		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'Foo_bar', null, null, '' );

		$this->assertSame( 'https://translate.example.org/wiki/Foo_bar', $title->getUrl() );
	}

	public function testProjectPrefixGoesAfterNamespace() {
		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_CATEGORY, 'Foo', 'eo', null, 'Category' );

		$this->assertSame( 'https://translate.example.org/wiki/Category:!eo:Foo', $title->getUrl() );
	}

	public function testSectionBecomesEscapedFragment() {
		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'Foo', 'eo', 'Some_section?', '' );

		$this->assertSame( 'https://translate.example.org/wiki/!eo:Foo#Some_section?', $title->getUrl() );
	}

	public function testTitleIsUrlEncoded() {
		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'What?_Ŝtono', null, null, '' );

		$this->assertSame( 'https://translate.example.org/wiki/What%3F_%C5%9Ctono', $title->getUrl() );
	}

	public function testUnregisteredOrUnknownWiki() {
		$this->assertNull( ProjectTitle::makeTitleOnWiki( 'otherwiki', NS_MAIN, 'Foo', null, null, '' )->getUrl() );
		$this->assertNull( ProjectTitle::makeTitleOnWiki( null, NS_MAIN, 'Foo', null, null, '' )->getUrl() );
	}
}
