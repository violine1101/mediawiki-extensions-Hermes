<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::getLinksForPage
 */
class GetLinksForPageTest extends HermesIntegrationTestCase {

	public function testFindsOtherLanguage() {
		$en = $this->makePageInfo( 'en' );
		$de = $this->makePageInfo( 'de' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertArrayHasKey( 'de', $links );
		$this->assertSame( $de->id, $links[ 'de' ]->page->id );
	}

	public function testExcludesOwnLanguage() {
		$enA = $this->makePageInfo( 'en' );
		$enB = $this->makePageInfo( 'en' );

		TagStore::setTagsForPage( $enA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $enB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$this->assertSame( [], TagStore::getLinksForPage( $enA ) );
	}

	public function testEmptyWithoutTags() {
		$page = $this->makePageInfo( 'en' );

		$this->assertSame( [], TagStore::getLinksForPage( $page ) );
	}

	public function testLowestOrderWins() {
		$en = $this->makePageInfo( 'en' );
		// shared_tag is this page's first (order 0) tag.
		$dePrimary = $this->makePageInfo( 'de' );
		// shared_tag is this page's second (order 1) tag.
		$deSecondary = $this->makePageInfo( 'de' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $dePrimary, Tag::fromArgs( [ 'shared_tag', 'other_tag' ] ) );
		TagStore::setTagsForPage( $deSecondary, Tag::fromArgs( [ 'other_tag_2', 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( $dePrimary->id, $links[ 'de' ]->page->id );
	}

	public function testEarlierTagWins() {
		// primaryTarget's tag has a *higher* ht_order than fallbackTarget's, but
		// primary_tag comes first in en's fallback chain and must still win.
		$primaryTarget = $this->makePageInfo( 'de' );
		$fallbackTarget = $this->makePageInfo( 'de' );
		$en = $this->makePageInfo( 'en' );

		TagStore::setTagsForPage( $primaryTarget, Tag::fromArgs( [ 'unrelated', 'primary_tag' ] ) );
		TagStore::setTagsForPage( $fallbackTarget, Tag::fromArgs( [ 'fallback_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'primary_tag', 'fallback_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( $primaryTarget->id, $links[ 'de' ]->page->id );
	}

	public function testTiebreakByTitle() {
		$en = $this->makePageInfo( 'en' );
		$deA = $this->makePageInfo( 'de', 'ZTitle' );
		$deB = $this->makePageInfo( 'de', 'ATitle' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		// deA and deB tie on tag + order; the alphabetically-first title wins
		// deterministically, rather than depending on DB scan order.
		$this->assertSame( 'ATitle', $links[ 'de' ]->page->fullTitle );
	}
}
