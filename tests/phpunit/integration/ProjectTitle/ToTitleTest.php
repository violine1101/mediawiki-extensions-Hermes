<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use LogicException;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
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
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );
	}

	public function testMainNamespace() {
		$this->assertSame( '!eo:Foo', self::localTitle( NS_MAIN, 'foo', 'eo' )->toTitle()->getPrefixedText() );
	}

	public function testOtherNamespace() {
		$title = self::localTitle( NS_CATEGORY, 'foo', 'eo' )->toTitle();

		$this->assertSame( NS_CATEGORY, $title->getNamespace() );
		$this->assertSame( '!eo:Foo', $title->getText() );
	}

	public function testStoredTitleKeepsCapitalization() {
		$title = self::localTitle( NS_MAIN, 'foo', 'eo' );

		$this->assertSame( '!eo:foo', $title->toStoredTitle()->getPrefixedText() );
	}

	public function testWithProjectOfTemplateTarget() {
		$title = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_TEMPLATE, 'Foo_bar' ) )
			->withProject( self::getProject( 'eo', WikiMap::getCurrentWikiId() ) )
			->toTitle();

		$this->assertSame( 'Template:!eo:Foo bar', $title->getPrefixedText() );
	}

	public function testNewFromLinkTargetParsesProject() {
		$projectTitle = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_CATEGORY, '!eo:Foo_bar' ) );

		$this->assertSame( NS_CATEGORY, $projectTitle->namespace );
		$this->assertSame( 'eo', $projectTitle->getProject()->code );
		$this->assertSame( 'Foo_bar', $projectTitle->baseTitle );
	}

	public function testNewFromLinkTargetIgnoresPrefixInNonProjectNamespace() {
		$projectTitle = ProjectTitle::newFromLinkTarget( Title::makeTitle( NS_FILE, '!eo:Foo.jpg' ) );

		$this->assertTrue( $projectTitle->getProject()->isBase );
		$this->assertSame( '!eo:Foo.jpg', $projectTitle->baseTitle );
		$this->assertTrue( $projectTitle->hasInvalidTitle() );
	}

	public function testToBasePage() {
		$title = self::localTitle( NS_MAIN, 'Foo', 'eo' )->toBasePage()->toTitle();

		$this->assertSame( 'Foo', $title->getPrefixedText() );
	}

	public function testNewFromLinkTargetIgnoresPrefixOnInterwikiTitle() {
		$interwiki = new TitleValue( NS_MAIN, '!eo:Foo', '', 'de' );
		$this->assertTrue( ProjectTitle::newFromLinkTarget( $interwiki )->getProject()->isBase );
	}

	public function testForeignTitleCannotBecomeLocalTitle() {
		$project = new Project( 'eo', 'otherwiki', false, true, null );
		$title = ProjectTitle::makeTitle( $project, NS_MAIN, 'Foo', null, '' );

		$this->expectException( LogicException::class );
		$title->toTitle();
	}
}
