<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks
 */
class PageDeleteHookTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	public function testTagsRemovedOnPageDelete() {
		$page = $this->getExistingTestPage( 'DeleteHookTest' );
		$this->editPage( $page, '{{#hermes:some_tag}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );

		// getLinksForPage() always excludes the queried page's own language, so give it
		// a synthetic partner in another language sharing the same tag; that way a
		// non-empty result actually demonstrates the tag was written by
		// LinksUpdateComplete, rather than passing vacuously.
		$partner = new PageInfo();
		$partner->wiki = $pageInfo->wiki;
		$partner->id = 999001;
		$partner->fullTitle = 'DeleteHookTest/de';
		$partner->language = 'de';
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'some_tag' ] ) );

		// sanity check it was written by LinksUpdateComplete
		$this->assertNotEmpty( TagStore::getLinksForPage( $pageInfo ) );

		$this->deletePage( $page, 'test deletion', $this->getTestSysop()->getUser() );

		$this->assertSame( [], TagStore::getLinksForPage( $pageInfo ) );
	}
}
