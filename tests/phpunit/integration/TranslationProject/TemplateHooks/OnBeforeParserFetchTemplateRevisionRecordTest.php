<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\TemplateHooks;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\TemplateHooks::onBeforeParserFetchTemplateRevisionRecord
 */
class OnBeforeParserFetchTemplateRevisionRecordTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function parseOnProjectPage( string $wikitext ): string {
		$title = Title::makeTitle( NS_MAIN, '!eo:TestPage' );
		$parser = $this->getServiceContainer()->getParser();
		return $parser->parse( $wikitext, $title, ParserOptions::newFromAnon() )->getContentHolderText();
	}

	public function testUsesProjectTemplateWhenItExists() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:Foo' ), 'project version' );

		$html = $this->parseOnProjectPage( '{{foo}}' );

		$this->assertStringContainsString( 'project version', $html );
		$this->assertStringNotContainsString( 'base version', $html );
	}

	public function testFallsBackToBaseTemplateWhenProjectTemplateMissing() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );

		$html = $this->parseOnProjectPage( '{{foo}}' );

		$this->assertStringContainsString( 'base version', $html );
	}

	public function testRedlinksBaseTemplateWhenNeitherExists() {
		$html = $this->parseOnProjectPage( '{{foo}}' );

		$this->assertStringContainsString( 'action=edit', $html );
		$this->assertStringContainsString( 'Template:Foo', $html );
		$this->assertStringNotContainsString( '!eo:Foo', $html );
	}

	public function testCapitalizesInsertedBareTitleBeforePrefixing() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:Golemo' ), 'project version' );

		$html = $this->parseOnProjectPage( '{{golemo}}' );

		$this->assertStringContainsString( 'project version', $html );
	}

	public function testRegistersExistenceDependencyOnMissingProjectTemplate() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );

		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse(
			'{{foo}}', Title::makeTitle( NS_MAIN, '!eo:TestPage' ), ParserOptions::newFromAnon()
		);

		$deps = array_map(
			static fn ( $entry ) => $entry['link']->getNamespace() . ':' . $entry['link']->getDBkey(),
			$output->getLinkList( ParserOutputLinkTypes::EXISTENCE )
		);
		$this->assertContains( NS_TEMPLATE . ':!eo:Foo', $deps );
	}

	public function testEscapeForcesBaseTemplateEvenIfProjectTemplateExists() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:Foo' ), 'project version' );

		$html = $this->parseOnProjectPage( '{{!:foo}}' );

		$this->assertStringContainsString( 'base version', $html );
		$this->assertStringNotContainsString( 'project version', $html );
	}

	public function testEscapeRedlinksBaseTemplateWhenMissing() {
		$html = $this->parseOnProjectPage( '{{!:foo}}' );

		$this->assertStringContainsString( 'title=Template:Foo&amp;action=edit', $html );
		$this->assertStringContainsString( '>Template:!:foo</a>', $html );
	}

	public function testEscapeRegistersNoBogusProjectDependency() {
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse(
			'{{!:foo}}', Title::makeTitle( NS_MAIN, '!eo:TestPage' ), ParserOptions::newFromAnon()
		);

		$deps = array_map(
			static fn ( $entry ) => $entry['link']->getDBkey(),
			$output->getLinkList( ParserOutputLinkTypes::EXISTENCE )
		);
		$this->assertSame( [], array_filter( $deps, static fn ( $dbkey ) => str_contains( $dbkey, '!eo:' ) ) );
	}

	public function testEscapeWorksOnNonProjectPage() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );

		$parser = $this->getServiceContainer()->getParser();
		$html = $parser->parse(
			'{{!:foo}}', Title::makeTitle( NS_TEMPLATE, 'Bar' ), ParserOptions::newFromAnon()
		)->getContentHolderText();

		$this->assertStringContainsString( 'base version', $html );
	}

	public function testEscapeRedlinksBaseTemplateOnNonProjectPage() {
		$parser = $this->getServiceContainer()->getParser();
		$html = $parser->parse(
			'{{!:foo}}', Title::makeTitle( NS_TEMPLATE, 'Bar' ), ParserOptions::newFromAnon()
		)->getContentHolderText();

		$this->assertStringContainsString( 'title=Template:Foo&amp;action=edit', $html );
		$this->assertStringContainsString( '>Template:!:foo</a>', $html );
	}

	public function testLeavesNonProjectPageUntouched() {
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, 'Foo' ), 'base version' );
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:Foo' ), 'project version' );

		$title = Title::makeTitle( NS_MAIN, 'TestPage' );
		$parser = $this->getServiceContainer()->getParser();
		$html = $parser->parse( '{{foo}}', $title, ParserOptions::newFromAnon() )->getContentHolderText();

		$this->assertStringContainsString( 'base version', $html );
		$this->assertStringNotContainsString( 'project version', $html );
	}

	public function testLeavesExplicitProjectReferenceUntouched() {
		// Explicit "!eo:" references aren't capitalized after the prefix.
		$this->insertPage( Title::makeTitle( NS_TEMPLATE, '!eo:foo' ), 'project version' );

		$html = $this->parseOnProjectPage( '{{!eo:foo}}' );

		$this->assertStringContainsString( 'project version', $html );
	}
}
