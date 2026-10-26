<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\LinkHooks;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\LinkHooks::onHtmlPageLinkRendererEnd
 */
class OnHtmlPageLinkRendererEndTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	private function makeLink( Title $target, array $attribs = [] ): string {
		return $this->getServiceContainer()->getLinkRenderer()->makeLink( $target, null, $attribs );
	}

	public function testRedLinkTitleUsesProjectLanguageAndDisplayTitle() {
		$link = $this->makeLink( Title::makeTitle( NS_TALK, '!eo:Foo_bar' ) );

		$expected = wfMessage( 'red-link-title', 'Diskuto:Foo bar' )->inLanguage( 'eo' )->text();
		$this->assertStringContainsString( 'title="' . htmlspecialchars( $expected ) . '"', $link );
	}

	public function testKnownLinkTitleUsesDisplayTitle() {
		$this->insertPage( Title::makeTitle( NS_TALK, '!eo:Foo_bar' ) );

		$link = $this->makeLink( Title::makeTitle( NS_TALK, '!eo:Foo_bar' ) );

		$this->assertStringContainsString( 'title="Diskuto:Foo bar"', $link );
	}

	public function testExplicitTitleIsKept() {
		$link = $this->makeLink( Title::makeTitle( NS_MAIN, '!eo:Foo' ), [ 'title' => 'Custom' ] );

		$this->assertStringContainsString( 'title="Custom"', $link );
	}

	public function testNonProjectLinkIsUntouched() {
		$link = $this->makeLink( Title::makeTitle( NS_MAIN, 'Foo' ) );

		$expected = wfMessage( 'red-link-title', 'Foo' )->inContentLanguage()->text();
		$this->assertStringContainsString( 'title="' . htmlspecialchars( $expected ) . '"', $link );
	}
}
