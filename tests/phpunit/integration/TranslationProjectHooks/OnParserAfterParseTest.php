<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProjectHooks;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks::onParserAfterParse
 */
class OnParserAfterParseTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testLocalizedDisplayTitleForProjectPage() {
		$title = Title::newFromText( 'Category:!eo:Foo' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( 'content', $title, ParserOptions::newFromAnon() );
		$displayTitle = $output->getDisplayTitle();

		// Keep the same span-wrapped structure/classes core's own title formatting uses.
		$this->assertStringContainsString( '<span class="mw-page-title-namespace">Kategorio</span>', $displayTitle );
		$this->assertStringContainsString( '<span class="mw-page-title-separator">:</span>', $displayTitle );
		$this->assertStringContainsString( '<span class="mw-page-title-main">Foo</span>', $displayTitle );
	}

	public function testDisplayTitleUnchangedForOrdinaryPage() {
		$title = Title::newFromText( 'Foo' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( 'content', $title, ParserOptions::newFromAnon() );

		// Core's own title-variant conversion always populates a titletext wrapped in this
		// span; our override (a bare "Kategorio:Foo"-style string) would replace it entirely.
		$this->assertStringContainsString( 'mw-page-title-main', $output->getDisplayTitle() );
	}
}
