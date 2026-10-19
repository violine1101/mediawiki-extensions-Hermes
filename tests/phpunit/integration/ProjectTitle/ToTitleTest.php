<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use LogicException;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle
 */
class ToTitleTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );
	}

	public function testMainNamespace() {
		$this->assertSame( '!eo:Foo', ProjectTitle::makeTitle( NS_MAIN, 'foo', 'eo' )->toTitle()->getPrefixedText() );
	}

	public function testOtherNamespace() {
		$title = ProjectTitle::makeTitle( NS_CATEGORY, 'foo', 'eo' )->toTitle();

		$this->assertSame( NS_CATEGORY, $title->getNamespace() );
		$this->assertSame( '!eo:Foo', $title->getText() );
	}

	public function testStoredTitleKeepsCapitalization() {
		$this->assertSame( '!eo:foo', ProjectTitle::makeTitle( NS_MAIN, 'foo', 'eo' )->toStoredTitle()->getPrefixedText() );
	}

	public function testWithProjectOfTemplateTarget() {
		$title = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_TEMPLATE, 'Foo_bar' ) )
			->withProject( 'eo' )
			->toTitle();

		$this->assertSame( 'Template:!eo:Foo bar', $title->getPrefixedText() );
	}

	public function testNewFromLinkTargetParsesProject() {
		$projectTitle = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_CATEGORY, '!eo:Foo_bar' ) );

		$this->assertSame( NS_CATEGORY, $projectTitle->namespace );
		$this->assertSame( 'eo', $projectTitle->project );
		$this->assertSame( 'Foo_bar', $projectTitle->baseTitle );
	}

	public function testNewFromLinkTargetIgnoresPrefixInNonProjectNamespace() {
		$projectTitle = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_FILE, '!eo:Foo.jpg' ) );

		$this->assertNull( $projectTitle->project );
		$this->assertSame( '!eo:Foo.jpg', $projectTitle->baseTitle );
		$this->assertTrue( $projectTitle->hasInvalidTitle() );
	}

	public function testWithProjectNullIsBasePage() {
		$title = ProjectTitle::makeTitle( NS_MAIN, 'Foo', 'eo' )->withProject( null )->toTitle();

		$this->assertSame( 'Foo', $title->getPrefixedText() );
	}

	public function testNewFromLinkTargetIgnoresPrefixOnInterwikiTitle() {
		$this->assertNull( ProjectTitle::newFromLinkTarget( new TitleValue( NS_MAIN, '!eo:Foo', '', 'de' ) )->project );
	}

	public function testForeignTitleCannotBecomeLocalTitle() {
		$title = ProjectTitle::makeTitleOnWiki( 'otherwiki', NS_MAIN, 'Foo', 'eo', null, '' );

		$this->expectException( LogicException::class );
		$title->toTitle();
	}
}
