<?php

namespace MediaWiki\Extension\Hermes\Tests\LocalProjectTitle;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LocalProjectTitle
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

	public function testWithProjectOfTemplateTarget() {
		$title = LocalProjectTitle::fromTitle( Title::makeTitle( NS_TEMPLATE, 'Foo_bar' ) )
			->withProject( self::getProject( 'eo', WikiMap::getCurrentWikiId() ) )
			->toTitle();

		$this->assertSame( 'Template:!eo:Foo bar', $title->getPrefixedText() );
	}

	public function testFromTitleParsesProject() {
		$projectTitle = LocalProjectTitle::fromTitle( Title::makeTitle( NS_CATEGORY, '!eo:Foo_bar' ) );

		$this->assertSame( NS_CATEGORY, $projectTitle->namespace );
		$this->assertSame( 'eo', $projectTitle->project->code );
		$this->assertSame( 'Foo_bar', $projectTitle->baseTitle );
	}

	public function testFromTitleIgnoresPrefixInNonProjectNamespace() {
		$projectTitle = LocalProjectTitle::fromTitle( Title::makeTitle( NS_FILE, '!eo:Foo.jpg' ) );

		$this->assertTrue( $projectTitle->project->isBase );
		$this->assertSame( '!eo:Foo.jpg', $projectTitle->baseTitle );
		$this->assertTrue( $projectTitle->isInvalid() );
	}

	public function testToBasePage() {
		$title = self::localTitle( NS_MAIN, 'Foo', 'eo' )->toBasePage()->toTitle();

		$this->assertSame( 'Foo', $title->getPrefixedText() );
	}

	public function testFromTitleRejectsInterwikiTitle() {
		$this->expectException( InvalidArgumentException::class );
		LocalProjectTitle::fromTitle( new TitleValue( NS_MAIN, '!eo:Foo', '', 'de' ) );
	}

	public function testRejectsForeignProject() {
		$this->expectException( InvalidArgumentException::class );
		new LocalProjectTitle( new Project( 'eo', 'otherwiki', false, true, null ), NS_MAIN, 'Foo' );
	}
}
