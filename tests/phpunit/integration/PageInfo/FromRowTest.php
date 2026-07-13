<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
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
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function fetchRow( PageInfo $page ) {
		return Hermes::getDB()->selectRow(
			'hermes_tags',
			'*',
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__
		);
	}

	public function testOrdinaryPage() {
		$title = Title::newFromText( 'PageInfoFromRowOrdinary' );
		$written = PageInfo::fromLocalPage( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_ordinary_tag' ] ) );

		$read = PageInfo::fromRow( $this->fetchRow( $written ) );

		$this->assertNull( $read->translationProject );
		$this->assertSame( NS_MAIN, $read->namespace );
		$this->assertSame( '', $read->namespaceText );
		$this->assertSame( 'PageInfoFromRowOrdinary', $read->title );
	}

	/**
	 * Regression test for the FIXME this redesign removes: previously, fromRow() re-derived
	 * translationProject by regexing the full prefixed title string, whose leading "!" never
	 * matched once a namespace prefix was in front of it - so a namespaced project page's
	 * translationProject silently came back null when loaded from the database. Storing the
	 * already-split ht_translation_project column at write time avoids that entirely.
	 */
	public function testNamespacedProjectPage() {
		$title = Title::newFromText( 'Category:!eo:PageInfoFromRowProject' );
		$written = PageInfo::fromLocalPage( $title );
		TagStore::setTagsForPage( $written, Tag::fromArgs( [ 'from_row_project_tag' ] ) );

		$read = PageInfo::fromRow( $this->fetchRow( $written ) );

		$this->assertSame( 'eo', $read->translationProject );
		$this->assertSame( NS_CATEGORY, $read->namespace );
		$this->assertSame( 'Category', $read->namespaceText );
		$this->assertSame( 'PageInfoFromRowProject', $read->title );
	}
}
