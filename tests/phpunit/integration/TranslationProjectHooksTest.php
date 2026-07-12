<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks
 */
class TranslationProjectHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function checkResult( string $titleText, string $action = 'create' ) {
		$title = Title::newFromText( $titleText );
		$user = $this->getTestUser()->getUser();
		$result = null;
		( new TranslationProjectHooks() )->onGetUserPermissionsErrors( $title, $user, $action, $result );

		return $result;
	}

	private function isAllowed( string $titleText, string $action = 'create' ): bool {
		$result = null;
		$title = Title::newFromText( $titleText );
		$user = $this->getTestUser()->getUser();

		$hooks = new TranslationProjectHooks();
		return $hooks->onGetUserPermissionsErrors( $title, $user, $action, $result ) !== false;
	}

	public function testAllowsRegisteredProjectTitleInMainNamespace() {
		$this->assertTrue( $this->isAllowed( '!eo:Foo' ) );
	}

	public function testAllowsRegisteredProjectTitleInOtherNamespace() {
		$this->assertTrue( $this->isAllowed( 'Category:!eo:Foo' ) );
	}

	public function testDeniesUnregisteredProjectLanguage() {
		$this->assertFalse( $this->isAllowed( '!zz:Foo' ) );
		$this->assertSame( 'hermes-invalid-project-title', $this->checkResult( '!zz:Foo' ) );
	}

	public function testDeniesMalformedBangTitle() {
		$this->assertFalse( $this->isAllowed( '!Foo' ) );
		$this->assertSame( 'hermes-invalid-project-title', $this->checkResult( '!Foo' ) );
	}

	public function testDoesNotInterfereWithOrdinaryTitle() {
		$this->assertTrue( $this->isAllowed( 'Foo' ) );
	}

	public function testDoesNotInterfereWithReadAction() {
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'read' ) );
	}

	public function testDoesNotInterfereWithDeleteAction() {
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'delete' ) );
	}

	public function testAppliesToMoveTargetAction() {
		$this->assertFalse( $this->isAllowed( '!zz:Foo', 'move-target' ) );
	}

	public function testDoesNotInterfereWithEditAction() {
		// Editing an existing page doesn't need re-checking here: MediaWiki already checks
		// 'create' (in addition to 'edit') whenever the target page doesn't exist yet, so
		// 'create' alone is sufficient to gate new bang-title pages from ever being made.
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'edit' ) );
	}

	public function testPageContentLanguageSetForRegisteredProjectTitle() {
		$title = Title::newFromText( 'Category:!eo:Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new TranslationProjectHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'eo', $pageLang->getCode() );
	}

	public function testPageContentLanguageUnchangedForOrdinaryTitle() {
		$title = Title::newFromText( 'Foo' );
		$pageLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
		( new TranslationProjectHooks() )->onPageContentLanguage( $title, $pageLang, null );

		$this->assertSame( 'en', $pageLang->getCode() );
	}

	public function testUserGetLanguageObjectSetForRegisteredProjectTitle() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$user = $this->getTestUser()->getUser();
		$code = 'en';
		( new TranslationProjectHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'eo', $code );
	}

	public function testUserGetLanguageObjectUnchangedForOrdinaryTitle() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( 'Foo' ) );
		$user = $this->getTestUser()->getUser();
		$code = 'en';
		( new TranslationProjectHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'en', $code );
	}

	public function testUserGetLanguageObjectRespectsExplicitUselang() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$context->setRequest( new FauxRequest( [ 'uselang' => 'de' ] ) );
		$user = $this->getTestUser()->getUser();
		$code = 'de';
		( new TranslationProjectHooks() )->onUserGetLanguageObject( $user, $code, $context );

		$this->assertSame( 'de', $code );
	}

	public function testParserSetsLocalizedDisplayTitleForProjectPage() {
		$title = Title::newFromText( 'Category:!eo:Foo' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( 'content', $title, ParserOptions::newFromAnon() );
		$displayTitle = $output->getDisplayTitle();

		// Keep the same span-wrapped structure/classes core's own title formatting uses.
		$this->assertStringContainsString( '<span class="mw-page-title-namespace">Kategorio</span>', $displayTitle );
		$this->assertStringContainsString( '<span class="mw-page-title-separator">:</span>', $displayTitle );
		$this->assertStringContainsString( '<span class="mw-page-title-main">Foo</span>', $displayTitle );
	}

	public function testParserDoesNotSetDisplayTitleForOrdinaryPage() {
		$title = Title::newFromText( 'Foo' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( 'content', $title, ParserOptions::newFromAnon() );

		// Core's own title-variant conversion always populates a titletext wrapped in this
		// span; our override (a bare "Kategorio:Foo"-style string) would replace it entirely.
		$this->assertStringContainsString( 'mw-page-title-main', $output->getDisplayTitle() );
	}

	public function testOutputPageBeforeHTMLAddsHatnoteSubtitleForProjectPage() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( '!eo:Foo' ) );
		$text = '<p>content</p>';
		( new TranslationProjectHooks() )->onOutputPageBeforeHTML( $context->getOutput(), $text );

		// Reuses core's own "subpages" style/class rather than a custom hatnote, and goes
		// into the subtitle area, leaving the article body text itself untouched.
		$this->assertStringContainsString( 'class="subpages"', $context->getOutput()->getSubtitle() );
		$this->assertStringContainsString( 'Esperanto', $context->getOutput()->getSubtitle() );
		$this->assertSame( '<p>content</p>', $text );
	}

	public function testOutputPageBeforeHTMLLeavesOrdinaryPageUnchanged() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( 'Foo' ) );
		$text = '<p>content</p>';
		( new TranslationProjectHooks() )->onOutputPageBeforeHTML( $context->getOutput(), $text );

		$this->assertSame( '', $context->getOutput()->getSubtitle() );
		$this->assertSame( '<p>content</p>', $text );
	}
}
