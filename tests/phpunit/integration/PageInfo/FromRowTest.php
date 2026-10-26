<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::fromRow
 */
class FromRowTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	private function fetchRow( PageInfo $page ) {
		return Hermes::getDB()->selectRow(
			'hermes_tags',
			'*',
			[ 'ht_wiki' => $page->title->getProject()->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__
		);
	}

	public function testOrdinaryPage() {
		$title = Title::newFromText( 'PageInfoFromRowOrdinary' );
		$written = PageInfo::fromLocalPage( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_ordinary_tag' ] ) );

		$read = PageInfo::fromRow( $this->fetchRow( $written ) );

		$this->assertTrue( $read->title->getProject()->isBase );
		$this->assertSame( NS_MAIN, $read->title->namespace );
		$this->assertSame( '', $read->title->getNamespaceText() );
		$this->assertSame( 'PageInfoFromRowOrdinary', $read->title->baseTitle );
	}

	/**
	 * Regression test for the FIXME this redesign removes: previously, fromRow() re-derived
	 * the translation project by regexing the full prefixed title string, whose leading "!" never
	 * matched once a namespace prefix was in front of it - so a namespaced project page's
	 * language silently came back null when loaded from the database. Storing the
	 * already-split ht_language column at write time avoids that entirely.
	 */
	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoFromRowProject' );
		$written = PageInfo::fromLocalPage( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_project_tag' ] ) );

		$read = PageInfo::fromRow( $this->fetchRow( $written ) );

		$this->assertSame( 'eo', $read->title->getProject()->code );
		$this->assertSame( NS_CATEGORY, $read->title->namespace );
		$this->assertSame( 'Category', $read->title->getNamespaceText() );
		$this->assertSame( 'PageInfoFromRowProject', $read->title->baseTitle );
	}

	public function testPageOfRemovedProjectIsInvalidBasePage() {
		$written = PageInfo::fromLocalPage( Title::newFromText( '!eo:PageInfoFromRowRemoved' ) );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_removed_tag' ] ) );
		Hermes::getDB( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'hermes_projects' )
			->where( [ 'hp_language' => 'eo' ] )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();

		$read = PageInfo::fromRow( $this->fetchRow( $written ) );

		$this->assertTrue( $read->title->getProject()->isBase );
		$this->assertSame( '!eo:PageInfoFromRowRemoved', $read->title->baseTitle );
		$this->assertTrue( $read->title->hasInvalidTitle() );
	}
}
