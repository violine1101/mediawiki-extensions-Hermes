<?php

namespace MediaWiki\Extension\Hermes\Tests\ParserFuncHooks;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks::onParserAfterParse
 */
class OnParserAfterParseTest extends HermesIntegrationTestCase {

	private function parse( string $wikitext, string $titleText = 'OnParserAfterParseTest' ): ParserOutput {
		$title = Title::newFromText( $titleText );
		$parser = $this->getServiceContainer()->getParser();
		return $parser->parse( $wikitext, $title, ParserOptions::newFromAnon() );
	}

	public function testConflictAddsCategory() {
		$existing = $this->makePageInfo( LanguageStore::getLocalBaseLanguage(), 'ExistingConflictPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'conflict_tag' ] ) );

		$output = $this->parse( '{{#hermes:conflict_tag}}', 'ParserFuncHooksConflictTest' );

		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testNoConflictNoCategory() {
		$output = $this->parse( '{{#hermes:no_conflict_tag}}' );

		$this->assertNotContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testAmbiguityAddsCategory() {
		$deA = $this->makePageInfo( 'de', 'AmbiguousDeA' );
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'ambiguous_tag' ] ) );

		$deB = $this->makePageInfo( 'de', 'AmbiguousDeB' );
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'ambiguous_tag' ] ) );

		// This page's own language (the test wiki's content language) is unrelated to 'de',
		// so it's purely a bystander whose outgoing link via this tag would be ambiguous.
		$output = $this->parse( '{{#hermes:ambiguous_tag}}', 'ParserFuncHooksAmbiguousTest' );

		$this->assertContains( 'Pages_with_ambiguous_Hermes_links', $output->getCategoryNames() );
	}

	public function testNoAmbiguityNoCategory() {
		$output = $this->parse( '{{#hermes:no_ambiguous_tag}}' );

		$this->assertNotContains( 'Pages_with_ambiguous_Hermes_links', $output->getCategoryNames() );
	}

	public function testConflictWarningDetails() {
		$existing = $this->makePageInfo( LanguageStore::getLocalBaseLanguage(), 'ExistingConflictPageDetails' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'conflict_details_tag' ] ) );

		$text = $this->parse( '{{#hermes:conflict_details_tag}}', 'ParserFuncHooksConflictDetailsTest' )
			->getContentHolderText();

		$this->assertStringContainsString( 'cdx-message--warning', $text );
		$this->assertStringContainsString( '<code>conflict details tag</code>', $text );
		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*">ExistingConflictPageDetails<\/a>/',
			$text
		);
	}

	public function testAmbiguityWarningDetails() {
		$deA = $this->makePageInfo( 'de', 'AmbiguousDetailsA' );
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'ambiguous_details_tag' ] ) );

		$deB = $this->makePageInfo( 'de', 'AmbiguousDetailsB' );
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'ambiguous_details_tag' ] ) );

		$text = $this->parse( '{{#hermes:ambiguous_details_tag}}', 'ParserFuncHooksAmbiguousDetailsTest' )
			->getContentHolderText();

		$this->assertStringContainsString( 'cdx-message--warning', $text );
		$this->assertStringContainsString( '<code>ambiguous details tag</code>', $text );
		// Language codes are shown via their autonym (i.e. what {{#language:}} would produce),
		// not the raw code.
		$this->assertStringContainsString( 'Deutsch', $text );
		$this->assertMatchesRegularExpression( '/<a href="[^"]*">AmbiguousDetailsA<\/a>/', $text );
		$this->assertMatchesRegularExpression( '/<a href="[^"]*">AmbiguousDetailsB<\/a>/', $text );
	}

	public function testWarningsSuppressedByConfig() {
		$this->overrideConfigValue( 'HermesWarnings', false );

		$existing = $this->makePageInfo( LanguageStore::getLocalBaseLanguage(), 'ExistingConflictPageNoWarning' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'conflict_no_warning_tag' ] ) );

		$output = $this->parse( '{{#hermes:conflict_no_warning_tag}}', 'ParserFuncHooksNoWarningTest' );

		$this->assertStringNotContainsString( 'cdx-message--warning', $output->getContentHolderText() );
		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testOnlyLastCallChecked() {
		$existing = $this->makePageInfo( LanguageStore::getLocalBaseLanguage(), 'ExistingConflictPageForFirstCall' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'first_call_conflict_tag' ] ) );

		// The first call's tag conflicts, but the second call overwrites the persisted
		// "hermes_tags" page property - only the surviving (second) call's tags should
		// actually be checked, since only those get saved.
		$output = $this->parse(
			'{{#hermes:first_call_conflict_tag}}{{#hermes:second_call_tag}}',
			'ParserFuncHooksOnlyLastCallTest'
		);

		$this->assertNotContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
		// The duplicate-call warning, and a debug notice box naming the tag, are both
		// legitimately present (this wikitext calls {{#hermes:}} twice, and $wgHermesDebug
		// is on in this dev environment) - what must NOT appear is the *conflicting page's
		// title*, which only the conflict warning box would ever mention.
		$this->assertStringNotContainsString( 'ExistingConflictPageForFirstCall', $output->getContentHolderText() );
	}

	public function testLastCallConflictDetected() {
		$existing = $this->makePageInfo( LanguageStore::getLocalBaseLanguage(), 'ExistingConflictPageForLastCall' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'last_call_conflict_tag' ] ) );

		$output = $this->parse(
			'{{#hermes:harmless_first_tag}}{{#hermes:last_call_conflict_tag}}',
			'ParserFuncHooksLastCallConflictTest'
		);

		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}
}
