<?php

namespace MediaWiki\Extension\Hermes\Tests;

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
