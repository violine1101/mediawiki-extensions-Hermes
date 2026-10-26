<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::getLinksForPage
 */
class GetLinksForPageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->activateLocalBaseProject();
		$this->registerBaseProject( 'de', 'dewiki' );
	}

	public function testFindsOtherLanguage() {
		$en = $this->makePage( WikiMap::getCurrentWikiId() );
		$de = $this->makePage( 'dewiki' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertArrayHasKey( 'de', $links );
		$this->assertSame( $de->getId(), $links[ 'de' ]->title->getId() );
	}

	public function testExcludesOwnLanguage() {
		$enA = $this->makePage( WikiMap::getCurrentWikiId() );
		$enB = $this->makePage( WikiMap::getCurrentWikiId() );

		TagStore::setTagsForPage( $enA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $enB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$this->assertSame( [], TagStore::getLinksForPage( $enA ) );
	}

	public function testEmptyWithoutTags() {
		$page = $this->makePage( WikiMap::getCurrentWikiId() );

		$this->assertSame( [], TagStore::getLinksForPage( $page ) );
	}

	public function testHighestPriorityWins() {
		$en = $this->makePage( WikiMap::getCurrentWikiId() );
		// shared_tag is this page's first (priority 0) tag.
		$dePrimary = $this->makePage( 'dewiki' );
		// shared_tag is this page's second (priority 1) tag.
		$deSecondary = $this->makePage( 'dewiki' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $dePrimary, Tag::fromArgs( [ 'shared_tag', 'other_tag' ] ) );
		TagStore::setTagsForPage( $deSecondary, Tag::fromArgs( [ 'other_tag_2', 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( $dePrimary->getId(), $links[ 'de' ]->title->getId() );
	}

	public function testEarlierTagWins() {
		// primaryTarget's tag has a *lower* priority than fallbackTarget's, but
		// primary_tag comes first in en's fallback chain and must still win.
		$primaryTarget = $this->makePage( 'dewiki' );
		$fallbackTarget = $this->makePage( 'dewiki' );
		$en = $this->makePage( WikiMap::getCurrentWikiId() );

		TagStore::setTagsForPage( $primaryTarget, Tag::fromArgs( [ 'unrelated', 'primary_tag' ] ) );
		TagStore::setTagsForPage( $fallbackTarget, Tag::fromArgs( [ 'fallback_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'primary_tag', 'fallback_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( $primaryTarget->getId(), $links[ 'de' ]->title->getId() );
	}

	public function testTiebreakByTitle() {
		$en = $this->makePage( WikiMap::getCurrentWikiId() );
		$deA = $this->makePage( 'dewiki', 'ZTitle' );
		$deB = $this->makePage( 'dewiki', 'ATitle' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deA, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $deB, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		// deA and deB tie on tag + priority; the alphabetically-first title wins
		// deterministically, rather than depending on DB scan order.
		$this->assertSame( 'ATitle', $links[ 'de' ]->title->baseTitle );
	}

	public function testSkipsInactivePages() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'eo', $wiki, false );
		$this->registerTranslationProject( 'de', $wiki, false );
		$en = $this->makePage( $wiki );
		$de = $this->makePage( 'dewiki' );

		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $this->makePage( $wiki, '!eo:Foo' ), Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $this->makePage( $wiki, '!de:Foo' ), Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'other_tag', 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( [ 'de' ], array_keys( $links ) );
		$this->assertSame( $de->getId(), $links[ 'de' ]->title->getId() );
	}

	public function testInactivePageLinksToActivePages() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$inactive = $this->makePage( $wiki, '!de:Foo' );

		TagStore::setTagsForPage( $inactive, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $this->makePage( 'dewiki' ), Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $this->makePage( $wiki ), Tag::fromArgs( [ 'shared_tag' ] ) );

		$this->assertSame( [ 'en' ], array_keys( TagStore::getLinksForPage( $inactive ) ) );
	}
}
