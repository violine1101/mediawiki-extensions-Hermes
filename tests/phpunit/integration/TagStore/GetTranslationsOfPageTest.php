<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::getTranslationsOfPage
 */
class GetTranslationsOfPageTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerBaseProject( 'fr', 'frwiki' );
		$this->registerBaseProject( 'es', 'eswiki' );
	}

	public function testOnlyMainTagAtPriorityZero() {
		$en = $this->makePage( WikiMap::getCurrentWikiId() );
		$de = $this->makePage( 'dewiki' );
		$fr = $this->makePage( 'frwiki' );
		$es = $this->makePage( 'eswiki' );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'foo', 'bar' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'foo' ] ) );
		// A fallback of the English page, and one of the French page.
		TagStore::setTagsForPage( $fr, Tag::fromArgs( [ 'bar' ] ) );
		TagStore::setTagsForPage( $es, Tag::fromArgs( [ 'baz', 'foo' ] ) );

		$translations = TagStore::getTranslationsOfPage( $en );

		$this->assertSame( [ 'de' ], array_keys( $translations ) );
		$this->assertSame( $de->getId(), $translations[ 'de' ]->title->getId() );
		$this->assertCount( 3, TagStore::getLinksForPage( $en ) );
	}

	public function testNoTags() {
		$this->assertSame( [], TagStore::getTranslationsOfPage( $this->makePage( WikiMap::getCurrentWikiId() ) ) );
	}
}
