<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::findConflicts
 */
class FindConflictsTest extends HermesIntegrationTestCase {

	/**
	 * @param PageInfo[] $pages
	 * @return string[]
	 */
	private static function titlesOf( array $pages ): array {
		return array_map( static fn ( PageInfo $page ) => $page->fullTitle, $pages );
	}

	public function testDetectsConflict() {
		$existing = $this->makePageInfo( 'eo', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		// newPage is brand new - it has no row of its own in hermes_tags yet - but still
		// conflicts with an already-saved page claiming the same tag+order+language.
		$newPage = $this->makePageInfo( 'eo', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		// $newPage itself is included as a claimant too, alongside the page it conflicts with.
		$conflicts = TagStore::findConflicts( $newPage, $tags );
		$this->assertSame( [ 'NewPage', 'ExistingPage' ], self::titlesOf( $conflicts[ 'eo' ][ 'golem' ] ) );
	}

	public function testAllConflictingPages() {
		$existingA = $this->makePageInfo( 'eo', 'ExistingPageA' );
		$existingB = $this->makePageInfo( 'eo', 'ExistingPageB' );
		TagStore::setTagsForPage( $existingA, Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $existingB, Tag::fromArgs( [ 'golem' ] ) );

		$newPage = $this->makePageInfo( 'eo', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$conflicts = TagStore::findConflicts( $newPage, $tags );
		$this->assertSame(
			[ 'NewPage', 'ExistingPageA', 'ExistingPageB' ],
			self::titlesOf( $conflicts[ 'eo' ][ 'golem' ] )
		);
	}

	public function testEmptyWithoutConflict() {
		$page = $this->makePageInfo( 'eo', 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}

	public function testIgnoresOwnPriorTags() {
		$page = $this->makePageInfo( 'eo', 'SomePage' );
		TagStore::setTagsForPage( $page, Tag::fromArgs( [ 'golem' ] ) );

		// Re-saving the same page with the same tag must not conflict with itself.
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}

	public function testIgnoresDifferentOrder() {
		$existing = $this->makePageInfo( 'eo', 'ExistingPage' );
		// 'golem' is existing's *second* tag (order 1).
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'unrelated', 'golem' ] ) );

		$newPage = $this->makePageInfo( 'eo', 'NewPage' );
		// 'golem' is newPage's *first* (and only) tag (order 0) - different order.
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tags ) );
	}

	public function testIgnoresDifferentLanguage() {
		$existing = $this->makePageInfo( 'eo', 'ExistingPage' );
		TagStore::setTagsForPage( $existing, Tag::fromArgs( [ 'golem' ] ) );

		// A single other-language claimant isn't a direct conflict for newPage (wrong
		// language) and isn't yet an ambiguity either (needs 2+ *other* claimants).
		$newPage = $this->makePageInfo( 'de', 'NewPage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $newPage, $tags ) );
	}

	public function testDetectsAmbiguity() {
		$dePrimary = $this->makePageInfo( 'de', 'DePrimary' );
		$deSecondary = $this->makePageInfo( 'de', 'DeSecondary' );
		TagStore::setTagsForPage( $dePrimary, Tag::fromArgs( [ 'golem' ] ) );
		TagStore::setTagsForPage( $deSecondary, Tag::fromArgs( [ 'golem' ] ) );

		// page's own language ('en') isn't involved in the 'de' tie at all.
		$page = $this->makePageInfo( 'en', 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$conflicts = TagStore::findConflicts( $page, $tags );
		$this->assertSame(
			[ 'DePrimary', 'DeSecondary' ],
			self::titlesOf( $conflicts[ 'de' ][ 'golem' ] )
		);
		$this->assertArrayNotHasKey( 'en', $conflicts );
	}

	public function testEmptyWithoutClaimant() {
		$page = $this->makePageInfo( 'de', 'SomePage' );
		$tags = Tag::fromArgs( [ 'golem' ] );

		$this->assertSame( [], TagStore::findConflicts( $page, $tags ) );
	}
}
