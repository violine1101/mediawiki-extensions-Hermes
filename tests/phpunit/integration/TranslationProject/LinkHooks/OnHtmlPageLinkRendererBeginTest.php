<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\LinkHooks;

use MediaWiki\Extension\Hermes\Hooks\TranslationProject\LinkHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\LinkHooks::onHtmlPageLinkRendererBegin
 */
class OnHtmlPageLinkRendererBeginTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	private function callHook( Title $target, &$text = null, array $query = [] ) {
		$hooks = new LinkHooks();
		$linkRenderer = $this->getServiceContainer()->getLinkRenderer();
		$customAttribs = [];
		$ret = null;

		$continue = $hooks->onHtmlPageLinkRendererBegin(
			$linkRenderer, $target, $text, $customAttribs, $query, $ret
		);

		return [ $continue, $ret ];
	}

	public function testAddsFallbackWhenBasePageExists() {
		$this->insertPage( 'Foo' );
		$target = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertFalse( $continue );
		$this->assertMatchesRegularExpression(
			'/^<a href="[^"]*Special:HermesTranslate\/Foo&amp;language=eo" class="new" title="[^"]*">!eo:Foo<\/a>/',
			$ret
		);
		$this->assertMatchesRegularExpression(
			'/<sup[^>]*>\(<a[^>]*href="[^"]*\/Foo"[^>]*>en<\/a>\)<\/sup>/',
			$ret
		);
	}

	public function testTranslationUnderOtherTitle() {
		$this->insertPage( 'Foo' );
		$this->insertPage( '!eo:Fooo' );
		$wiki = WikiMap::getCurrentWikiId();
		TagStore::setTagsForPage( $this->makePage( $wiki, 'Foo' ), Tag::fromArgs( [ 'foo' ] ) );
		TagStore::setTagsForPage( $this->makePage( $wiki, '!eo:Fooo' ), Tag::fromArgs( [ 'foo' ] ) );

		[ , $ret ] = $this->callHook( Title::makeTitle( NS_MAIN, '!eo:Foo' ) );

		$this->assertMatchesRegularExpression(
			'/^<a href="[^"]*=!eo:Foo&amp;hermes-redirect=%21eo%3AFooo&amp;action=edit&amp;redlink=1" class="new"/',
			$ret
		);
		$this->assertMatchesRegularExpression(
			'/<sup[^>]*>\(<a[^>]*href="[^"]*\/Foo"[^>]*>en<\/a>, <a[^>]*href="[^"]*\/!eo:Fooo"[^>]*>eo<\/a>\)<\/sup>$/',
			$ret
		);
	}

	public static function provideFallbackTagTranslation() {
		return [
			'fallback of the base page' => [ [ 'foo', 'bar' ], [ 'bar' ] ],
			'fallback of the translation' => [ [ 'foo' ], [ 'baz', 'foo' ] ],
		];
	}

	/** @dataProvider provideFallbackTagTranslation */
	public function testNoRedirectForFallbackTag( array $baseTags, array $translationTags ) {
		$this->insertPage( 'Foo' );
		$this->insertPage( '!eo:Fooo' );
		$wiki = WikiMap::getCurrentWikiId();
		TagStore::setTagsForPage( $this->makePage( $wiki, 'Foo' ), Tag::fromArgs( $baseTags ) );
		TagStore::setTagsForPage( $this->makePage( $wiki, '!eo:Fooo' ), Tag::fromArgs( $translationTags ) );

		[ , $ret ] = $this->callHook( Title::makeTitle( NS_MAIN, '!eo:Foo' ) );

		$this->assertMatchesRegularExpression(
			'/^<a href="[^"]*Special:HermesTranslate\/Foo&amp;language=eo"/', $ret
		);
		$this->assertStringContainsString( '>eo</a>)</sup>', $ret );
	}

	public function testNoFallbackWhenBasePageAlsoMissing() {
		$target = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertTrue( $continue );
		$this->assertNull( $ret );
	}

	public function testNoFallbackWhenProjectPageExists() {
		$this->insertPage( Title::makeTitle( NS_MAIN, '!eo:Foo' ) );
		$this->insertPage( 'Foo' );
		$target = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertTrue( $continue );
		$this->assertNull( $ret );
	}

	public function testNoFallbackForLinkWithQuery() {
		$this->insertPage( 'Foo' );
		$target = Title::makeTitle( NS_MAIN, '!eo:Foo' );

		$text = null;
		[ $continue, $ret ] = $this->callHook( $target, $text, [ 'action' => 'edit' ] );

		$this->assertTrue( $continue );
		$this->assertNull( $ret );
	}

	public function testRegistersExistenceDependencyOnBasePage() {
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse(
			'[[!eo:Foo_bar]] [[Baz]]', Title::makeTitle( NS_MAIN, 'TestPage' ), ParserOptions::newFromAnon()
		);

		$deps = array_map(
			static fn ( $entry ) => $entry['link']->getDBkey(),
			$output->getLinkList( ParserOutputLinkTypes::EXISTENCE )
		);
		$this->assertSame( [ 'Foo_bar' ], $deps );
	}

	public function testNoFallbackForNonProjectTarget() {
		$target = Title::makeTitle( NS_MAIN, 'Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertTrue( $continue );
		$this->assertNull( $ret );
	}

	/** @dataProvider provideLinkFallbackNamespaces */
	public function testLinksToBasePageInFallbackNamespaces( int $ns, string $nsText ) {
		$this->insertPage( Title::makeTitle( $ns, 'Foo' ) );
		$target = Title::makeTitle( $ns, '!eo:Foo' );

		$text = 'shown';
		[ $continue, $ret ] = $this->callHook( $target, $text );

		$this->assertFalse( $continue );
		$this->assertStringContainsString( "title=\"{$nsText}:Foo\"", $ret );
		$this->assertStringContainsString( '>shown</a>', $ret );
		$this->assertStringNotContainsString( '!eo:', $ret );
		$this->assertStringNotContainsString( '<sup', $ret );
	}

	public function testLinksToMissingBasePageInFallbackNamespaces() {
		$target = Title::makeTitle( NS_TEMPLATE, '!eo:Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertFalse( $continue );
		$this->assertStringContainsString( 'redlink=1', $ret );
		$this->assertStringNotContainsString( '!eo:', $ret );
	}

	public function testKeepsProjectPageInFallbackNamespaceWhenItExists() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:Foo' ) );
		$target = Title::makeTitle( NS_TEMPLATE, '!eo:Foo' );

		[ $continue, $ret ] = $this->callHook( $target );

		$this->assertTrue( $continue );
		$this->assertNull( $ret );
	}

	public static function provideLinkFallbackNamespaces(): array {
		return [
			'user' => [ NS_USER, 'User' ],
			'user talk' => [ NS_USER_TALK, 'User talk' ],
			'template' => [ NS_TEMPLATE, 'Template' ],
			'template talk' => [ NS_TEMPLATE_TALK, 'Template talk' ],
		];
	}
}
