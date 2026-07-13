<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks
 */
class ParserFuncHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	private function parse( string $wikitext, string $titleText = 'ParserFuncHooksTest' ): ParserOutput {
		$title = Title::newFromText( $titleText );
		$parser = $this->getServiceContainer()->getParser();
		return $parser->parse( $wikitext, $title, ParserOptions::newFromAnon() );
	}

	public function testDuplicateTagNameShowsErrorBoxAndDoesNotPersistTags() {
		$output = $this->parse( '{{#hermes:shared_tag|shared_tag}}' );

		$this->assertStringContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDuplicateTagNameAcrossSectionsShowsErrorBox() {
		$output = $this->parse( '{{#hermes:shared_tag|shared_tag#Some Section}}' );

		$this->assertStringContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDistinctTagsDoNotShowErrorBox() {
		$output = $this->parse( '{{#hermes:tag_a|tag_b#Details}}' );

		$this->assertStringNotContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNotNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDuplicateCallAddsTrackingCategory() {
		$output = $this->parse( '{{#hermes:tag_a}}{{#hermes:tag_b}}' );

		$this->assertContains( 'Pages_with_duplicate_Hermes_calls', $output->getCategoryNames() );
	}

	public function testSingleCallDoesNotAddDuplicateCallCategory() {
		$output = $this->parse( '{{#hermes:tag_a}}' );

		$this->assertNotContains( 'Pages_with_duplicate_Hermes_calls', $output->getCategoryNames() );
	}

	public function testConflictingTagAddsTrackingCategory() {
		$existing = new PageInfo();
		$existing->wiki = WikiMap::getCurrentWikiId();
		$existing->id = 999101;
		$existing->fullTitle = 'ExistingConflictPage';
		$existing->language = LanguageStore::getLocalBaseLanguage();
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'conflict_tag' ] ) );

		$output = $this->parse( '{{#hermes:conflict_tag}}', 'ParserFuncHooksConflictTest' );

		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testNonConflictingTagDoesNotAddConflictCategory() {
		$output = $this->parse( '{{#hermes:no_conflict_tag}}' );

		$this->assertNotContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testAmbiguousLinkAddsTrackingCategory() {
		$deA = new PageInfo();
		$deA->wiki = WikiMap::getCurrentWikiId();
		$deA->id = 999102;
		$deA->fullTitle = 'AmbiguousDeA';
		$deA->language = 'de';
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'ambiguous_tag' ] ) );

		$deB = new PageInfo();
		$deB->wiki = WikiMap::getCurrentWikiId();
		$deB->id = 999103;
		$deB->fullTitle = 'AmbiguousDeB';
		$deB->language = 'de';
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'ambiguous_tag' ] ) );

		// This page's own language (the test wiki's content language) is unrelated to 'de',
		// so it's purely a bystander whose outgoing link via this tag would be ambiguous.
		$output = $this->parse( '{{#hermes:ambiguous_tag}}', 'ParserFuncHooksAmbiguousTest' );

		$this->assertContains( 'Pages_with_ambiguous_Hermes_links', $output->getCategoryNames() );
	}

	public function testNonAmbiguousTagDoesNotAddAmbiguousLinksCategory() {
		$output = $this->parse( '{{#hermes:no_ambiguous_tag}}' );

		$this->assertNotContains( 'Pages_with_ambiguous_Hermes_links', $output->getCategoryNames() );
	}

	public function testConflictingTagShowsWarningBoxWithDetails() {
		$existing = new PageInfo();
		$existing->wiki = WikiMap::getCurrentWikiId();
		$existing->id = 999106;
		$existing->fullTitle = 'ExistingConflictPageDetails';
		$existing->language = LanguageStore::getLocalBaseLanguage();
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

	public function testAmbiguousLinkShowsWarningBoxWithDetails() {
		$deA = new PageInfo();
		$deA->wiki = WikiMap::getCurrentWikiId();
		$deA->id = 999107;
		$deA->fullTitle = 'AmbiguousDetailsA';
		$deA->language = 'de';
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'ambiguous_details_tag' ] ) );

		$deB = new PageInfo();
		$deB->wiki = WikiMap::getCurrentWikiId();
		$deB->id = 999108;
		$deB->fullTitle = 'AmbiguousDetailsB';
		$deB->language = 'de';
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

	public function testWarningsSuppressedWhenConfigDisabledButCategoryStillApplies() {
		$this->overrideConfigValue( 'HermesWarnings', false );

		$existing = new PageInfo();
		$existing->wiki = WikiMap::getCurrentWikiId();
		$existing->id = 999109;
		$existing->fullTitle = 'ExistingConflictPageNoWarning';
		$existing->language = LanguageStore::getLocalBaseLanguage();
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'conflict_no_warning_tag' ] ) );

		$output = $this->parse( '{{#hermes:conflict_no_warning_tag}}', 'ParserFuncHooksNoWarningTest' );

		$this->assertStringNotContainsString( 'cdx-message--warning', $output->getContentHolderText() );
		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}

	public function testOnlyLastCallsTagsAreCheckedForConflicts() {
		$existing = new PageInfo();
		$existing->wiki = WikiMap::getCurrentWikiId();
		$existing->id = 999110;
		$existing->fullTitle = 'ExistingConflictPageForFirstCall';
		$existing->language = LanguageStore::getLocalBaseLanguage();
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

	public function testLastCallsConflictIsStillDetected() {
		$existing = new PageInfo();
		$existing->wiki = WikiMap::getCurrentWikiId();
		$existing->id = 999111;
		$existing->fullTitle = 'ExistingConflictPageForLastCall';
		$existing->language = LanguageStore::getLocalBaseLanguage();
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'last_call_conflict_tag' ] ) );

		$output = $this->parse(
			'{{#hermes:harmless_first_tag}}{{#hermes:last_call_conflict_tag}}',
			'ParserFuncHooksLastCallConflictTest'
		);

		$this->assertContains( 'Pages_with_conflicting_Hermes_tags', $output->getCategoryNames() );
	}
}
