<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::setTagsForPage
 */
class SetTagsForPageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseLanguage( 'dewiki', 'de' );
	}

	public function testOverwritesPreviousTags() {
		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'old_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'old_tag' ] ) );
		$this->assertArrayHasKey( 'de', TagStore::getLinksForPage( $en ) );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'new_tag' ] ) );

		$this->assertArrayNotHasKey( 'de', TagStore::getLinksForPage( $en ) );
	}

	public function testPersistsSection() {
		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag#Some_Section' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( 'Some Section', $links[ 'de' ]->tag->section );
	}

	public function testOrderScopedPerSection() {
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId() );

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
}
