<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\LinkHooks;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\LinkHooks::onInternalParseBeforeLinks
 */
class OnInternalParseBeforeLinksTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testRewritesLinksOnProjectPage() {
		$title = Title::newFromText( '!eo:Some Page' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( '[[Foo]] [[Category:Bar]]', $title, ParserOptions::newFromAnon() );

		$localLinks = $output->getLinkList( 'local' );
		$this->assertCount( 1, $localLinks );
		$this->assertSame( '!eo:Foo', $localLinks[0][ 'link' ]->getDBkey() );

		$categoryLinks = $output->getLinkList( 'category' );
		$this->assertCount( 1, $categoryLinks );
		$this->assertSame( '!eo:Bar', $categoryLinks[0][ 'link' ]->getDBkey() );
	}

	public function testLeavesLinksUntouchedOnNonProjectPage() {
		$title = Title::newFromText( 'Some Page' );
		$parser = $this->getServiceContainer()->getParser();
		$output = $parser->parse( '[[Foo]] [[Category:Bar]]', $title, ParserOptions::newFromAnon() );

		$localLinks = $output->getLinkList( 'local' );
		$this->assertCount( 1, $localLinks );
		$this->assertSame( 'Foo', $localLinks[0][ 'link' ]->getDBkey() );

		$categoryLinks = $output->getLinkList( 'category' );
		$this->assertCount( 1, $categoryLinks );
		$this->assertSame( 'Bar', $categoryLinks[0][ 'link' ]->getDBkey() );
	}
}
