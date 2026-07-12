<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks
 */
class ParserFuncHooksTest extends MediaWikiIntegrationTestCase {

	private function parse( string $wikitext ): ParserOutput {
		$title = Title::newFromText( 'ParserFuncHooksTest' );
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
}
