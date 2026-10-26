<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\CategoryHooks;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks::onCategoryViewer__generateLink
 */
class OnCategoryViewer__generateLinkTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
		// A second project, for the cross-project annotation tests.
		ProjectStore::addTranslationProject( 'de', WikiMap::getCurrentWikiId() );
		// The hook only applies while viewing a category.
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, 'Bar' ) );
	}

	public function testStripsProjectPrefixForPageType() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( '>Foo<', $link );
	}

	public function testStripsProjectPrefixAndTranslatesNamespaceForPageType() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_TALK, '!eo:Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		// "Diskuto" is Esperanto for "Talk".
		$this->assertStringContainsString( '>Diskuto:Foo<', $link );
	}

	public function testStripsProjectPrefixForSubcatType() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_CATEGORY, '!eo:Bar' );

		// Core passes subcategory names without namespace.
		$link = null;
		$hooks->onCategoryViewer__generateLink( 'subcat', $title, '!eo:Bar', $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( '>Bar<', $link );
	}

	public function testDisplayTextUsesSpaces() {
		$hooks = new CategoryHooks();

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', Title::makeTitle( NS_TALK, '!eo:Foo_Bar' ), null, $link );
		$this->assertStringContainsString( '>Diskuto:Foo Bar<', $link );

		$link = null;
		$subcat = Title::makeTitle( NS_CATEGORY, '!eo:Baz_Qux' );
		$hooks->onCategoryViewer__generateLink( 'subcat', $subcat, '!eo:Baz Qux', $link );
		$this->assertStringContainsString( '>Baz Qux<', $link );
	}

	public function testLeavesNonProjectPageUntouched() {
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, 'Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNull( $link );
	}

	public function testAnnotatesMemberFromDifferentProjectThanCategory() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, '!eo:Bar' ) );
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!de:Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( '>Foo<', $link );
		$this->assertStringContainsString( '<sup class="hermes-category-language">(de)</sup>', $link );
	}

	public function testAnnotatesBaseLanguageMemberOfProjectCategory() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, '!eo:Bar' ) );
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, 'Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( '<sup class="hermes-category-language">(en)</sup>', $link );
	}

	public function testAnnotatesProjectLanguageMemberOfBaseCategory() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, 'Bar' ) );
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( '>Foo<', $link );
		$this->assertStringContainsString( '<sup class="hermes-category-language">(eo)</sup>', $link );
	}

	public function testNoAnnotationWhenMemberMatchesCategoryProject() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, '!eo:Bar' ) );
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNotNull( $link );
		$this->assertStringNotContainsString( 'hermes-category-language', $link );
	}

	public function testLeavesLinkUntouchedOutsideCategoryView() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_MAIN, 'Bar' ) );
		$hooks = new CategoryHooks();

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', Title::makeTitle( NS_MAIN, '!eo:Foo' ), null, $link );

		$this->assertNull( $link );
	}

	public function testNoAnnotationWhenMemberMatchesBaseCategory() {
		RequestContext::getMain()->setTitle( Title::makeTitle( NS_CATEGORY, 'Bar' ) );
		$hooks = new CategoryHooks();
		$title = Title::makeTitle( NS_MAIN, 'Foo' );

		$link = null;
		$hooks->onCategoryViewer__generateLink( 'page', $title, null, $link );

		$this->assertNull( $link );
	}
}
