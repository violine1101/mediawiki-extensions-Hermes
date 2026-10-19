<?php

namespace MediaWiki\Extension\Hermes\Tests\ParserFuncHooks;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks::renderHermes
 */
class RenderHermesTest extends HermesIntegrationTestCase {

	private function parse( string $wikitext, string $titleText = 'RenderHermesTest' ): ParserOutput {
		$title = Title::newFromText( $titleText );
		$parser = $this->getServiceContainer()->getParser();
		return $parser->parse( $wikitext, $title, ParserOptions::newFromAnon() );
	}

	public function testDuplicateTagNameShowsError() {
		$output = $this->parse( '{{#hermes:shared_tag|shared_tag}}' );

		$this->assertStringContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDuplicateAcrossSectionsShowsError() {
		$output = $this->parse( '{{#hermes:shared_tag|#Some Section=shared_tag}}' );

		$this->assertStringContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testEmptySectionShowsError() {
		$output = $this->parse( '{{#hermes:tag_a|#=tag_b}}' );

		$this->assertStringContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertStringContainsString( '<code>#=tag_b</code>', $output->getContentHolderText() );
		$this->assertNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDistinctTagsNoError() {
		$output = $this->parse( '{{#hermes:tag_a|#Details=tag_b}}' );

		$this->assertStringNotContainsString( 'cdx-message--error', $output->getContentHolderText() );
		$this->assertNotNull( $output->getPageProperty( 'hermes_tags' ) );
	}

	public function testDuplicateCallAddsCategory() {
		$output = $this->parse( '{{#hermes:tag_a}}{{#hermes:tag_b}}' );

		$this->assertContains( 'Pages_with_duplicate_Hermes_calls', $output->getCategoryNames() );
	}

	public function testSingleCallNoCategory() {
		$output = $this->parse( '{{#hermes:tag_a}}' );

		$this->assertNotContains( 'Pages_with_duplicate_Hermes_calls', $output->getCategoryNames() );
	}
}
