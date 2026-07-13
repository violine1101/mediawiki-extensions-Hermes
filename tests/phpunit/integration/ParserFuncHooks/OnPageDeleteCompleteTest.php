<?php

namespace MediaWiki\Extension\Hermes\Tests\ParserFuncHooks;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks::onPageDeleteComplete
 */
class OnPageDeleteCompleteTest extends HermesIntegrationTestCase {

	public function testRemovesTags() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$page = $this->getExistingTestPage( 'OnPageDeleteCompleteTest' );
		$this->editPage( $page, '{{#hermes:some_tag}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );

		// getLinksForPage() always excludes the queried page's own language, so give it
		// a synthetic partner in another language sharing the same tag; that way a
		// non-empty result actually demonstrates the tag was written by
		// LinksUpdateComplete, rather than passing vacuously.
		$partner = $this->makePageInfo( 'dewiki', 'OnPageDeleteCompleteTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'some_tag' ] ) );

		// sanity check it was written by LinksUpdateComplete
		$this->assertNotEmpty( TagStore::getLinksForPage( $pageInfo ) );

		$this->deletePage( $page, 'test deletion', $this->getTestSysop()->getUser() );

		$this->assertSame( [], TagStore::getLinksForPage( $pageInfo ) );
	}
}
