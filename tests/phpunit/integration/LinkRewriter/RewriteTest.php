<?php

namespace MediaWiki\Extension\Hermes\Tests\LinkRewriter;

use MediaWiki\Extension\Hermes\LinkRewriter;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LinkRewriter::rewrite
 */
class RewriteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
		// A second project, for references to other local projects.
		ProjectStore::addTranslationProject( 'de', WikiMap::getCurrentWikiId() );
	}

	private function eoPage(): ProjectTitle {
		return self::localTitle( NS_MAIN, 'Some_Page', 'eo' );
	}

	public function testNonProjectPageIsUntouched() {
		$page = self::localTitle( NS_MAIN, 'Some_Page' );
		$this->assertSame( '[[Foo]]', LinkRewriter::rewrite( '[[Foo]]', $page ) );
	}

	public function testEscapeIsStrippedOnNonProjectPage() {
		$page = self::localTitle( NS_MAIN, 'Some_Page' );
		$this->assertSame(
			'[[Foo|!:Foo]] [[Talk:Bar|x]] [[:Template:Baz|Template:!:Baz]] [[Qux]]',
			LinkRewriter::rewrite( '[[!:Foo]] [[Talk:!:Bar|x]] [[:Template:!:Baz]] [[Qux]]', $page )
		);
	}

	public function testUnprefixedLinkOnNonProjectPageIsUntouched() {
		$page = self::localTitle( NS_MAIN, 'Some_Page' );
		$this->assertSame( '[[Talk:foo!]]', LinkRewriter::rewrite( '[[Talk:foo!]]', $page ) );
	}

	public function testTextWithoutLinksIsUntouched() {
		$this->assertSame( 'plain text', LinkRewriter::rewrite( 'plain text', $this->eoPage() ) );
	}

	public function testPlainLinkGetsProjectPrefixAndDisplayText() {
		$this->assertSame( '[[!eo:Foo|Foo]]', LinkRewriter::rewrite( '[[Foo]]', $this->eoPage() ) );
	}

	public function testPlainLinkWithDisplayTextOnlyRewritesTarget() {
		$this->assertSame(
			'[[!eo:Foo|bar]]',
			LinkRewriter::rewrite( '[[Foo|bar]]', $this->eoPage() )
		);
	}

	public function testPlainLinkIsCapitalizedBeforePrefixing() {
		$this->assertSame(
			'[[!eo:Golemo|golemo]]',
			LinkRewriter::rewrite( '[[golemo]]', $this->eoPage() )
		);
	}

	public function testBareEscapeStripsToBasePage() {
		$this->assertSame( '[[Bar|!:Bar]]', LinkRewriter::rewrite( '[[!:Bar]]', $this->eoPage() ) );
	}

	public function testBareEscapeIsCapitalized() {
		$this->assertSame( '[[Golem|!:golem]]', LinkRewriter::rewrite( '[[!:golem]]', $this->eoPage() ) );
	}

	public function testExplicitSameProjectReferenceIsUnchanged() {
		$this->assertSame( '[[!eo:Foo]]', LinkRewriter::rewrite( '[[!eo:Foo]]', $this->eoPage() ) );
	}

	public function testExplicitProjectReferenceIsCapitalized() {
		$this->assertSame(
			'[[!de:Foo|!de:foo]]',
			LinkRewriter::rewrite( '[[!de:foo]]', self::localTitle( NS_MAIN, 'Some_Page' ) )
		);
	}

	public function testExplicitOtherLocalProjectReferenceIsUnchanged() {
		$this->assertSame( '[[!de:Foo]]', LinkRewriter::rewrite( '[[!de:Foo]]', $this->eoPage() ) );
	}

	public function testUnregisteredBangPrefixIsLeftLiteral() {
		// "fr" isn't a project language on this wiki.
		$this->assertSame( '[[!fr:Foo]]', LinkRewriter::rewrite( '[[!fr:Foo]]', $this->eoPage() ) );
	}

	public function testNamespacedLinkGetsProjectPrefixAfterNamespace() {
		$this->assertSame(
			'[[Talk:!eo:Foo|Talk:Foo]]',
			LinkRewriter::rewrite( '[[Talk:Foo]]', $this->eoPage() )
		);
	}

	public function testNamespacedEscapeStripsToBasePage() {
		$this->assertSame(
			'[[Talk:Foo|Talk:!:Foo]]',
			LinkRewriter::rewrite( '[[Talk:!:Foo]]', $this->eoPage() )
		);
	}

	public function testCategoryLinkGetsProjectPrefix() {
		$this->assertSame(
			'[[Category:!eo:Foo]]',
			LinkRewriter::rewrite( '[[Category:Foo]]', $this->eoPage() )
		);
	}

	public function testProjectLanguageNamespaceAliasResolvesLikeContentLanguageOne() {
		// "Kategorio" is Esperanto for "Category".
		$this->assertSame(
			'[[Category:!eo:Foo]]',
			LinkRewriter::rewrite( '[[Kategorio:Foo]]', $this->eoPage() )
		);
	}

	public function testFragmentIsPreserved() {
		$this->assertSame(
			'[[!eo:Foo#Section|Foo#Section]]',
			LinkRewriter::rewrite( '[[Foo#Section]]', $this->eoPage() )
		);
	}

	public function testLeadingColonEscapeIsPreserved() {
		// Unlike in a category assignment, "|" here means display text, not sort key.
		$this->assertSame(
			'[[:Category:!eo:Foo|Category:Foo]]',
			LinkRewriter::rewrite( '[[:Category:Foo]]', $this->eoPage() )
		);
	}

	public function testFileNamespaceIsUntouched() {
		$this->assertSame(
			'[[File:Foo.jpg]]',
			LinkRewriter::rewrite( '[[File:Foo.jpg]]', $this->eoPage() )
		);
	}

	public function testMediaNamespaceIsUntouched() {
		$this->assertSame(
			'[[Media:Foo.jpg]]',
			LinkRewriter::rewrite( '[[Media:Foo.jpg]]', $this->eoPage() )
		);
	}

	public function testTemplateNamespaceIsPrefixed() {
		$this->assertSame(
			'[[Template:!eo:Foo|Template:Foo]]',
			LinkRewriter::rewrite( '[[Template:Foo]]', $this->eoPage() )
		);
	}

	public function testTemplateNamespaceEscapeGoesToBasePage() {
		// How core renders a missing "{{!:foo}}" transclusion.
		$this->assertSame(
			'[[:Template:Foo|Template:!:foo]]',
			LinkRewriter::rewrite( '[[:Template:!:foo]]', $this->eoPage() )
		);
	}

	public function testTemplateNamespaceProjectLanguageAliasIsCanonicalizedInTarget() {
		// "Ŝablono" is Esperanto for "Template".
		$this->assertSame(
			'[[:Template:!eo:Foo|Ŝablono:Foo]]',
			LinkRewriter::rewrite( '[[:Ŝablono:Foo]]', $this->eoPage() )
		);
	}

	public function testFileNamespaceProjectLanguageAliasIsCanonicalized() {
		// "Dosiero" is Esperanto for "File".
		$this->assertSame(
			'[[File:Foo.jpg]]',
			LinkRewriter::rewrite( '[[Dosiero:Foo.jpg]]', $this->eoPage() )
		);
	}

	public function testMediaNamespaceProjectLanguageAliasIsCanonicalized() {
		// "Aŭdvidaĵo" is Esperanto for the NS_MEDIA pseudo-namespace.
		$this->assertSame(
			'[[Media:Foo.jpg]]',
			LinkRewriter::rewrite( '[[Aŭdvidaĵo:Foo.jpg]]', $this->eoPage() )
		);
	}

	public function testProjectLanguageNamespaceAliasForOrdinaryNamespaceGetsDisplayFixup() {
		// "Diskuto" is Esperanto for "Talk".
		$this->assertSame(
			'[[Talk:!eo:Baz|Diskuto:Baz]]',
			LinkRewriter::rewrite( '[[Diskuto:Baz]]', $this->eoPage() )
		);
	}

	public function testInterlanguageLinkIsUntouched() {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'interwiki' )
			->row( [
				'iw_prefix' => 'fr',
				'iw_url' => 'https://fr.example.org/wiki/$1',
				'iw_api' => '',
				'iw_wikiid' => '',
				'iw_local' => 0,
				'iw_trans' => 0,
			] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( '[[fr:Foo]]', LinkRewriter::rewrite( '[[fr:Foo]]', $this->eoPage() ) );
	}

	public function testSectionLinkToCurrentPageIsUntouched() {
		$this->assertSame( '[[#Section]]', LinkRewriter::rewrite( '[[#Section]]', $this->eoPage() ) );
	}

	public function testRelativeSubpageLinkIsUntouched() {
		$this->assertSame( '[[/Sub|x]]', LinkRewriter::rewrite( '[[/Sub|x]]', $this->eoPage() ) );
	}

	public function testSpecialNamespaceIsUntouched() {
		$this->assertSame(
			'[[Special:RecentChanges]]',
			LinkRewriter::rewrite( '[[Special:RecentChanges]]', $this->eoPage() )
		);
	}

	public function testUserNamespacesArePrefixed() {
		$this->assertSame(
			'[[User:!eo:Alice|Alice]] ([[User talk:!eo:Alice|talk]])',
			LinkRewriter::rewrite( '[[User:Alice|Alice]] ([[User talk:Alice|talk]])', $this->eoPage() )
		);
	}

	public function testUserNamespaceProjectLanguageAliasIsCanonicalizedInTarget() {
		// "Uzanto" is Esperanto for "User".
		$this->assertSame(
			'[[User:!eo:Alice|Alice]]',
			LinkRewriter::rewrite( '[[Uzanto:Alice|Alice]]', $this->eoPage() )
		);
	}

	public function testMultipleLinksAreAllRewritten() {
		$this->assertSame(
			'[[!eo:Foo|Foo]] and [[!eo:Bar|baz]]',
			LinkRewriter::rewrite( '[[Foo]] and [[Bar|baz]]', $this->eoPage() )
		);
	}
}
