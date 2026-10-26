<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\RemoteProjectTitle;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::newFromRow
 */
class NewFromRowTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	private function fetchRow( ProjectTitle $page ) {
		return Hermes::getDB()->selectRow(
			'hermes_tags',
			'*',
			[ 'ht_wiki' => $page->project->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__
		);
	}

	public function testOrdinaryPage() {
		$title = Title::newFromText( 'NewFromRowOrdinary' );
		$written = LocalProjectTitle::fromTitle( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_ordinary_tag' ] ) );

		$read = ProjectTitle::newFromRow( $this->fetchRow( $written ) );

		$this->assertTrue( $read->project->isBase );
		$this->assertSame( NS_MAIN, $read->namespace );
		$this->assertSame( '', $read->getNamespaceText() );
		$this->assertSame( 'NewFromRowOrdinary', $read->baseTitle );
	}

	public function testSubclassByWiki() {
		$this->registerBaseProject( 'de', 'dewiki' );
		$local = LocalProjectTitle::fromTitle( $this->getExistingTestPage( 'NewFromRowLocal' )->getTitle() );
		$remote = $this->makePage( 'dewiki', 'Golem' );
		TagStore::setTagsForPage( $local, Tag::fromArgs( [ 'from_row_subclass_tag' ] ) );
		TagStore::setTagsForPage( $remote, Tag::fromArgs( [ 'from_row_subclass_tag' ] ) );

		$this->assertInstanceOf( LocalProjectTitle::class, ProjectTitle::newFromRow( $this->fetchRow( $local ) ) );
		$this->assertInstanceOf( RemoteProjectTitle::class, ProjectTitle::newFromRow( $this->fetchRow( $remote ) ) );
		$this->assertSame( $remote->getId(), ProjectTitle::newFromRow( $this->fetchRow( $remote ) )->getId() );
	}

	/**
	 * Regression test for the FIXME this redesign removes: previously, fromRow() re-derived
	 * the translation project by regexing the full prefixed title string, whose leading "!" never
	 * matched once a namespace prefix was in front of it - so a namespaced project page's
	 * language silently came back null when loaded from the database. Storing the
	 * already-split ht_language column at write time avoids that entirely.
	 */
	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:NewFromRowProject' );
		$written = LocalProjectTitle::fromTitle( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_project_tag' ] ) );

		$read = ProjectTitle::newFromRow( $this->fetchRow( $written ) );

		$this->assertSame( 'eo', $read->project->code );
		$this->assertSame( NS_CATEGORY, $read->namespace );
		$this->assertSame( 'Category', $read->getNamespaceText() );
		$this->assertSame( 'NewFromRowProject', $read->baseTitle );
	}

	public function testPageOfRemovedProjectIsInvalidBasePage() {
		$written = LocalProjectTitle::fromTitle( Title::newFromText( '!eo:NewFromRowRemoved' ) );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_removed_tag' ] ) );
		Hermes::getDB( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'hermes_projects' )
			->where( [ 'hp_language' => 'eo' ] )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();

		$read = ProjectTitle::newFromRow( $this->fetchRow( $written ) );

		$this->assertTrue( $read->project->isBase );
		$this->assertSame( '!eo:NewFromRowRemoved', $read->baseTitle );
		$this->assertTrue( $read->isInvalid() );
	}
}
