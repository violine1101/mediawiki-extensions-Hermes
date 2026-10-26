<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::findConflicts
 */
class FindConflictsTest extends HermesIntegrationTestCase {

	/**
	 * @param TagLink[] $links
	 * @return string[]
	 */
	private static function titlesOf( array $links ): array {
		return array_map( static fn ( TagLink $link ) => $link->title->baseTitle, $links );
	}

	public function testDetectsConflict() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$existing = $this->makePage( 'frwiki', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		// newPage is brand new - it has no row of its own in hermes_tags yet - but still
		// conflicts with an already-saved page claiming the same tag+priority+language.
		$newPage = $this->makePage( 'frwiki', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		// $newPage itself is included as a claimant too, alongside the page it conflicts with.
		$conflicts = TagStore::findConflicts( $newPage, $tags );
		$this->assertSame( [ 'NewPage', 'ExistingPage' ], self::titlesOf( $conflicts[ 'fr' ][ 'golem' ] ) );
	}

	public function testAllConflictingPages() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$existingA = $this->makePage( 'frwiki', 'ExistingPageA' );
		$existingB = $this->makePage( 'frwiki', 'ExistingPageB' );
		TagStore::setTagsForPage( $existingA, Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $existingB, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = $this->makePage( 'frwiki', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$conflicts = TagStore::findConflicts( $newPage, $tags );
		$this->assertSame(
			[ 'NewPage', 'ExistingPageA', 'ExistingPageB' ],
			self::titlesOf( $conflicts[ 'fr' ][ 'golem' ] )
		);
	}

	public function testEmptyWithoutConflict() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$page = $this->makePage( 'frwiki', 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}

	public function testIgnoresOwnPriorTags() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$page = $this->makePage( 'frwiki', 'SomePage' );
		TagStore::setTagsForPage( $page, Tag::fromArgs( [ 'golem' ] ) );

		// Re-saving the same page with the same tag must not conflict with itself.
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}

	public function testIgnoresDifferentPriority() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$existing = $this->makePage( 'frwiki', 'ExistingPage' );
		// 'golem' is existing's *second* tag (priority 1).
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'unrelated', 'golem' ] ) );

		$newPage = $this->makePage( 'frwiki', 'NewPage' );
		// 'golem' is newPage's *first* (and only) tag (priority 0) - different priority.
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tags ) );
	}

	public function testIgnoresDifferentLanguage() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		$this->registerBaseProject( 'de', 'dewiki' );
		$existing = $this->makePage( 'frwiki', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		// A single other-language claimant isn't a direct conflict for newPage (wrong
		// language) and isn't yet an ambiguity either (needs 2+ *other* claimants).
		$newPage = $this->makePage( 'dewiki', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tags ) );
	}

	public function testDetectsAmbiguity() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$dePrimary = $this->makePage( 'dewiki', 'DePrimary' );
		$deSecondary = $this->makePage( 'dewiki', 'DeSecondary' );
		TagStore::setTagsForPage( $dePrimary, Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $deSecondary, Tag::fromArgs( [ 'golem' ] ) );

		// page's own language (the current wiki's base language) isn't involved in the
		// 'de' tie at all.
		$page = $this->makePage( WikiMap::getCurrentWikiId(), 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$conflicts = TagStore::findConflicts( $page, $tags );
		$this->assertSame(
			[ 'DePrimary', 'DeSecondary' ],
			self::titlesOf( $conflicts[ 'de' ][ 'golem' ] )
		);
		$this->assertArrayNotHasKey( $page->project->code, $conflicts );
	}

	public function testIgnoresTieBehindLowerPriority() {
		$this->registerBaseProject( 'fr', 'frwiki' );
		TagStore::setTagsForPage( $this->makePage( 'frwiki', 'Main' ), Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $this->makePage( 'frwiki', 'Other' ), Tag::fromArgs( [ 'other', 'golem' ] ) );

		$newPage = $this->makePage( 'frwiki', 'NewPage' );

		$this->assertSame( [], TagStore::findConflicts( $newPage, Tag::fromArgs( [ 'new', 'golem' ] ) ) );
	}

	public function testAmbiguityAtLowestPriority() {
		$this->registerBaseProject( 'de', 'dewiki' );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'DeA' ), Tag::fromArgs( [ 'a', 'golem' ] ) );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'DeB' ), Tag::fromArgs( [ 'b', 'golem' ] ) );

		$page = $this->makePage( WikiMap::getCurrentWikiId(), 'SomePage' );
		$conflicts = TagStore::findConflicts( $page, Tag::fromArgs( [ 'golem' ] ) );

		$this->assertSame( [ 'DeA', 'DeB' ], self::titlesOf( $conflicts[ 'de' ][ 'golem' ] ) );
	}

	public function testNoAmbiguityBehindEarlierTag() {
		$this->registerBaseProject( 'de', 'dewiki' );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'DeA' ), Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'DeB' ), Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'DeC' ), Tag::fromArgs( [ 'iron_golem' ] ) );

		// Links to German resolve by 'iron_golem', unambiguously.
		$page = $this->makePage( WikiMap::getCurrentWikiId(), 'SomePage' );

		$this->assertSame( [], TagStore::findConflicts( $page, Tag::fromArgs( [ 'iron_golem', 'golem' ] ) ) );
	}

	public function testEmptyWithoutClaimant() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$page = $this->makePage( 'dewiki', 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}

	public function testIgnoresInactivePages() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerTranslationProject( 'de', WikiMap::getCurrentWikiId(), false );
		TagStore::setTagsForPage(
			$this->makePage( WikiMap::getCurrentWikiId(), '!de:Golem' ), Tag::fromArgs( [ 'golem' ] )
		);

		$page = $this->makePage( 'dewiki', 'Golem' );

		$this->assertSame( [], TagStore::findConflicts( $page, Tag::fromArgs( [ 'golem' ] ) ) );
	}

	public function testNoOwnLanguageConflictForInactivePage() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerTranslationProject( 'de', WikiMap::getCurrentWikiId(), false );
		TagStore::setTagsForPage( $this->makePage( 'dewiki', 'Golem' ), Tag::fromArgs( [ 'golem' ] ) );

		$page = $this->makePage( WikiMap::getCurrentWikiId(), '!de:Golem' );

		$this->assertSame( [], TagStore::findConflicts( $page, Tag::fromArgs( [ 'golem' ] ) ) );
	}
}
