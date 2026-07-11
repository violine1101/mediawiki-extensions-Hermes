<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore
 */
class TagStoreTest extends MediaWikiIntegrationTestCase {

	public function testSetAndGetTags() {
		$page = $this->getExistingTestPage( 'TestPage' );
		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );

		$store = new TagStore();
		TagStore::setTagsForPage( $pageInfo, [ 'tag_a', 'tag_b' ] );

		$interwikis = $store->getLinksForPage( $pageInfo );
		// TODO: assert that the return value is correct
		$this->assertNotEmpty( $interwikis );
	}

	public function testDeleteTagsForPage() {
		$page = $this->getExistingTestPage( 'DeleteTestPage' );
		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );

		TagStore::setTagsForPage( $pageInfo, [ 'tag_c' ] );
		TagStore::deleteTagsForPage( $pageInfo );

		$interwikis = TagStore::getLinksForPage( $pageInfo );
		$this->assertSame( [], $interwikis );
	}
}
