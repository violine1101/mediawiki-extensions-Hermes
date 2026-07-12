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
}
