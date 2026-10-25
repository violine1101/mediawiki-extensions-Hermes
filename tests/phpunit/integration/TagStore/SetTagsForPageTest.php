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
		$this->registerBaseProject( 'dewiki', 'de' );
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

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag=#Some_Section' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = TagStore::getLinksForPage( $en );

		$this->assertSame( 'Some_Section', $links[ 'de' ]->tag->section );
	}

	public function testPriorityIgnoresSection() {
		$page = $this->makePageInfo( WikiMap::getCurrentWikiId() );

		TagStore::setTagsForPage( $page, Tag::fromArgs( [
			'page_level_a',
			'page_level_b',
			'detail_a=#Details',
			'detail_b=#Details',
		] ) );

		$rows = Hermes::getDB()->select(
			'hermes_tags',
			[ 'ht_tag', 'ht_section', 'ht_priority' ],
			[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__
		);

		$priorities = [];
		foreach ( $rows as $row ) {
			$priorities[ $row->ht_tag ] = (int)$row->ht_priority;
		}

		$this->assertSame( 0, $priorities[ 'page_level_a' ] );
		$this->assertSame( 1, $priorities[ 'page_level_b' ] );
		$this->assertSame( 2, $priorities[ 'detail_a' ] );
		$this->assertSame( 3, $priorities[ 'detail_b' ] );
	}
}
