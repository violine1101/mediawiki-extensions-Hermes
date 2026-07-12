<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore
 */
class TagStoreTest extends MediaWikiIntegrationTestCase {

	/**
	 * Builds a synthetic PageInfo without going through Title/PageReference, so tests
	 * can freely control the language (which normally follows the wiki's content
	 * language and can't easily be varied for real pages in a single test wiki).
	 */
	private static function makePageInfo( int $id, string $language, ?string $title = null ): PageInfo {
		$page = new PageInfo();
		$page->wiki = 'testwiki';
		$page->id = $id;
		$page->fullTitle = $title ?? "Page$id";
		$page->language = $language;
		return $page;
	}

	public function testGetLinksForPageFindsMatchingTagInOtherLanguage() {
		$en = self::makePageInfo( 1, 'en' );
		$de = self::makePageInfo( 2, 'de' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertArrayHasKey( 'de', $links );
		$this->assertSame( 2, $links[ 'de' ]->page->id );
	}

	public function testGetLinksForPageExcludesOwnLanguage() {
		$enA = self::makePageInfo( 1, 'en' );
		$enB = self::makePageInfo( 2, 'en' );

		TagStore::setTagsForPage( $enA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $enB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$this->assertSame( [], TagStore::getLinksForPage( $enA ) );
	}

	public function testGetLinksForPageReturnsEmptyWithoutTags() {
		$page = self::makePageInfo( 1, 'en' );

		$this->assertSame( [], TagStore::getLinksForPage( $page ) );
	}

	public function testLowestOrderWinsWithinSameTag() {
		$en = self::makePageInfo( 1, 'en' );
		// shared_tag is this page's first (order 0) tag.
		$dePrimary = self::makePageInfo( 2, 'de' );
		// shared_tag is this page's second (order 1) tag.
		$deSecondary = self::makePageInfo( 3, 'de' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $dePrimary, Tag::fromArgs( [ 'shared_tag', 'other_tag' ] ) );
		TagStore::setTagsForPage( $deSecondary, Tag::fromArgs( [ 'other_tag_2', 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( 2, $links[ 'de' ]->page->id );
	}

	public function testEarlierTagInFallbackChainTakesPriorityOverOrder() {
		// primaryTarget's tag has a *higher* ht_order than fallbackTarget's, but
		// primary_tag comes first in en's fallback chain and must still win.
		$primaryTarget = self::makePageInfo( 1, 'de' );
		$fallbackTarget = self::makePageInfo( 2, 'de' );
		$en = self::makePageInfo( 3, 'en' );

		TagStore::setTagsForPage( $primaryTarget, Tag::fromArgs( [ 'unrelated', 'primary_tag' ] ) );
		TagStore::setTagsForPage( $fallbackTarget, Tag::fromArgs( [ 'fallback_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'primary_tag', 'fallback_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( 1, $links[ 'de' ]->page->id );
	}

	public function testSetTagsForPageOverwritesPreviousTags() {
		$en = self::makePageInfo( 1, 'en' );
		$de = self::makePageInfo( 2, 'de' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'old_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'old_tag' ] ) );
		$this->assertArrayHasKey( 'de', TagStore::getLinksForPage( $en ) );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'new_tag' ] ) );

		$this->assertArrayNotHasKey( 'de', TagStore::getLinksForPage( $en ) );
	}

	public function testTagSectionIsPersisted() {
		$en = self::makePageInfo( 1, 'en' );
		$de = self::makePageInfo( 2, 'de' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag#Some_Section' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( 'Some Section', $links[ 'de' ]->tag->section );
	}

	public function testSetTagsForPageScopesOrderPerSection() {
		$page = self::makePageInfo( 1, 'en' );

		TagStore::setTagsForPage( $page, Tag::fromArgs( [
			'page_level_a',
			'detail_a#Details',
			'page_level_b',
			'detail_b#Details',
		] ) );

		$rows = Hermes::getDB()->select(
			'hermes_tags',
			[ 'ht_tag', 'ht_section', 'ht_order' ],
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__
		);

		$orders = [];
		foreach ( $rows as $row ) {
			$orders[ $row->ht_tag ] = (int)$row->ht_order;
		}

		// Page-level (no-section) tags share one 0-based sequence.
		$this->assertSame( 0, $orders[ 'page level a' ] );
		$this->assertSame( 1, $orders[ 'page level b' ] );

		// Tags under the "Details" section have their own, independent 0-based sequence.
		$this->assertSame( 0, $orders[ 'detail a' ] );
		$this->assertSame( 1, $orders[ 'detail b' ] );
	}

	public function testGetLinksForPageResolvesConflictTieByPageTitle() {
		$en = self::makePageInfo( 1, 'en' );
		$deA = self::makePageInfo( 2, 'de', 'ZTitle' );
		$deB = self::makePageInfo( 3, 'de', 'ATitle' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		// deA and deB tie on tag + order; the alphabetically-first title wins
		// deterministically, rather than depending on DB scan order.
		$this->assertSame( 'ATitle', $links[ 'de' ]->page->fullTitle );
	}

	public function testFindConflictsDetectsSameTagOrderLanguageOnDifferentPage() {
		$existing = self::makePageInfo( 1, 'eo', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = self::makePageInfo( 2, 'eo', 'NewPage' );
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [ 'ExistingPage' ], TagStore::findConflicts( $newPage, $tag ) );
	}

	public function testFindConflictsReturnsAllConflictingPages() {
		$existingA = self::makePageInfo( 1, 'eo', 'ExistingPageA' );
		$existingB = self::makePageInfo( 2, 'eo', 'ExistingPageB' );
		TagStore::setTagsForPage( $existingA, Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $existingB, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = self::makePageInfo( 3, 'eo', 'NewPage' );
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame(
			[ 'ExistingPageA', 'ExistingPageB' ],
			TagStore::findConflicts( $newPage, $tag )
		);
	}

	public function testFindConflictsDetectsConflictAcrossDifferentWikis() {
		// Two different wikis can both serve the same translation-project language, so a
		// conflict isn't scoped to a single wiki.
		$existing = new PageInfo();
		$existing->wiki = 'otherwiki';
		$existing->id = 1;
		$existing->fullTitle = 'ExistingPage';
		$existing->language = 'eo';
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = self::makePageInfo( 2, 'eo', 'NewPage' );
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [ 'ExistingPage' ], TagStore::findConflicts( $newPage, $tag ) );
	}

	public function testFindConflictsReturnsEmptyWithoutAnyConflict() {
		$page = self::makePageInfo( 1, 'eo', 'SomePage' );
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [], TagStore::findConflicts( $page, $tag ) );
	}

	public function testFindConflictsIgnoresPagesOwnPriorTags() {
		$page = self::makePageInfo( 1, 'eo', 'SomePage' );
		TagStore::setTagsForPage( $page, Tag::fromArgs( [ 'golem' ] ) );

		// Re-saving the same page with the same tag must not conflict with itself.
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [], TagStore::findConflicts( $page, $tag ) );
	}

	public function testFindConflictsIgnoresDifferentOrder() {
		$existing = self::makePageInfo( 1, 'eo', 'ExistingPage' );
		// 'golem' is existing's *second* tag (order 1).
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'unrelated', 'golem' ] ) );

		$newPage = self::makePageInfo( 2, 'eo', 'NewPage' );
		// 'golem' is newPage's *first* (and only) tag (order 0) - different order.
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tag ) );
	}

	public function testFindConflictsIgnoresDifferentLanguage() {
		$existing = self::makePageInfo( 1, 'eo', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = self::makePageInfo( 2, 'de', 'NewPage' );
		$tag = Tag::fromArgs( [ 'golem' ] )[ 0 ];

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tag ) );
	}

	public function testDeleteTagsForPage() {
		$en = self::makePageInfo( 1, 'en' );
		$de = self::makePageInfo( 2, 'de' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		$this->assertNotEmpty( TagStore::getLinksForPage( $en ) );

		TagStore::deleteTagsForPage( $de );

		$this->assertSame( [], TagStore::getLinksForPage( $en ) );
	}
}
