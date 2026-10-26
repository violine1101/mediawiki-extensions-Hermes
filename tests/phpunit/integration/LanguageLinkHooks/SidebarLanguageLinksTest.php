<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageLinkHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Skin\Skin;
use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks
 */
class SidebarLanguageLinksTest extends HermesIntegrationTestCase {

	private Title $title;

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'de', 'dewiki', url: 'https://de.example.org/wiki/$1' );
		$this->registerTranslationProject( 'eo', 'trwiki', url: 'https://translate.example.org/wiki/$1' );

		$page = $this->getExistingTestPage( 'SidebarLanguageLinksTest' );
		$this->editPage( $page, '{{#hermes:stone}}' );
		$this->title = $page->getTitle();
	}

	private function addPartners(): void {
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'Stein' ), Tag::fromArgs( [ 'stone' ] ) );
		$eoTitle = ProjectTitle::makeTitle( self::getProject( 'eo', 'trwiki' ), NS_MAIN, 'Ŝtono', '' );
		$eo = new PageInfo( $eoTitle, 1000 );
		TagStore::setTagsForPage( $eo, Tag::fromArgs( [ 'stone' ] ) );
	}

	private function makeHooks(): LanguageLinkHooks {
		$services = $this->getServiceContainer();
		return new LanguageLinkHooks(
			$services->getMainConfig(), $services->getLanguageNameUtils(), $services->getLanguageFactory()
		);
	}

	private function makeSkin( bool $isArticle = true ): Skin {
		$context = new RequestContext();
		$context->setTitle( $this->title );
		$context->setLanguage( 'en' );
		$context->getOutput()->setArticleFlag( $isArticle );
		$skin = $this->getServiceContainer()->getSkinFactory()->makeSkin( 'fallback' );
		$skin->setContext( $context );
		return $skin;
	}

	private function runSidebar( array $languages, bool $isArticle = true ): array {
		$sidebar = [ 'LANGUAGES' => $languages ];
		$this->makeHooks()->onSidebarBeforeOutput( $this->makeSkin( $isArticle ), $sidebar );
		return $sidebar['LANGUAGES'];
	}

	public function testAddsLinksToLanguages() {
		$this->addPartners();

		[ $de, $eo ] = $this->runSidebar( [] );

		$this->assertSame( 'https://de.example.org/wiki/Stein', $de['href'] );
		$this->assertSame( 'Deutsch', $de['text'] );
		$this->assertSame( 'de', $de['lang'] );
		$this->assertSame( 'interlanguage-link interwiki-de', $de['class'] );

		$this->assertSame( 'https://translate.example.org/wiki/!eo:%C5%9Ctono', $eo['href'] );
		$this->assertSame( 'Esperanto', $eo['text'] );
		$this->assertSame( 'interlanguage-link interwiki-eo ext-hermes-translation-project-link', $eo['class'] );
	}

	public function testManualLinkWinsAndOrderIsByLanguage() {
		$this->addPartners();
		$manual = [ 'href' => 'https://manual.example.org/', 'lang' => 'de' ];
		$other = [ 'href' => 'https://fr.example.org/', 'lang' => 'fr' ];

		$languages = $this->runSidebar( [ $other, $manual ] );

		$this->assertSame( [ 'de', 'eo', 'fr' ], array_column( $languages, 'lang' ) );
		$this->assertSame( $manual, $languages[0] );
		$this->assertSame( $other, $languages[2] );
	}

	public function testNothingWithoutTaggedPartners() {
		$this->assertSame( [], $this->runSidebar( [] ) );
	}

	public function testNothingOutsideArticleViews() {
		$this->addPartners();

		$this->assertSame( [], $this->runSidebar( [], false ) );
	}

	public function testNothingWhenDisabled() {
		$this->addPartners();
		$this->overrideConfigValue( 'HermesLanguageLinksInSidebar', false );

		$this->assertSame( [], $this->runSidebar( [] ) );
	}
}
