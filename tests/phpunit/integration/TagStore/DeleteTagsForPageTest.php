<?php

namespace MediaWiki\Extension\Hermes\Tests\TagStore;

use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\TagStore::deleteTagsForPage
 */
class DeleteTagsForPageTest extends HermesIntegrationTestCase {

	public function testRemovesTags() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki' );

		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'shared_tag' ] ) );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'shared_tag' ] ) );
		$this->assertNotEmpty( TagStore::getLinksForPage( $en ) );

		TagStore::deleteTagsForPage( $de );

		$this->assertSame( [], TagStore::getLinksForPage( $en ) );
	}
}
