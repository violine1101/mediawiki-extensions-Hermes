<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProjectHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks::onOutputPageBeforeHTML
 */
class OnOutputPageBeforeHTMLTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testAddsHatnoteForProjectPage() {
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

	public function testUnchangedForOrdinaryPage() {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( 'Foo' ) );
		$text = '<p>content</p>';
		( new TranslationProjectHooks() )->onOutputPageBeforeHTML( $context->getOutput(), $text );

		$this->assertSame( '', $context->getOutput()->getSubtitle() );
		$this->assertSame( '<p>content</p>', $text );
	}
}
