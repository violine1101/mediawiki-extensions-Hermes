<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks
 */
class PageDeleteHookTest extends MediaWikiIntegrationTestCase {

	public function testTagsRemovedOnPageDelete() {
		$page = $this->getExistingTestPage( 'DeleteHookTest' );
		$this->editPage( $page, '{{#hermes:some_tag}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );

		// sanity check it was written by LinksUpdateComplete
		$this->assertNotEmpty( TagStore::getLinksForPage( $pageInfo ) );

		$this->deletePage( $page, 'test deletion', $this->getTestSysop()->getUser() );

		$this->assertSame( [], TagStore::getLinksForPage( $pageInfo ) );
	}
}
