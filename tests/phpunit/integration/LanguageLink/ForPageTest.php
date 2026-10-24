<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageLink;

use MediaWiki\Extension\Hermes\LanguageLink;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\LanguageLink
 */
class ForPageTest extends HermesIntegrationTestCase {

	public function testBuildsLinksForRegisteredWikis() {
		$this->registerBaseLanguage( 'dewiki', 'de', url: 'https://de.example.org/wiki/$1' );
		$this->registerProjectLanguage( 'trwiki', 'eo', url: 'https://translate.example.org/wiki/$1' );

		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki', 'Stein' );
		$eo = new PageInfo( ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'Ŝtono', 'eo', null, '' ), 1000 );
		foreach ( [ $en, $de, $eo ] as $page ) {
			TagStore::setTagsForPage( $page, Tag::fromArgs( [ 'stone' ] ) );
		}

		$links = LanguageLink::forPage( $en );

		$this->assertSame( [ 'de', 'eo' ], array_keys( $links ) );

		$this->assertSame( 'https://de.example.org/wiki/Stein', $links['de']->url );
		$this->assertSame( 'de', $links['de']->getLanguageCode() );
		$this->assertSame( 'Stein', $links['de']->getDisplayTitle() );
		$this->assertFalse( $links['de']->isProject() );

		$this->assertSame( 'https://translate.example.org/wiki/!eo:%C5%9Ctono', $links['eo']->url );
		$this->assertSame( 'Ŝtono', $links['eo']->getDisplayTitle() );
		$this->assertTrue( $links['eo']->isProject() );
	}

	public function testSkipsWikisWithoutUrl() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki' );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'stone' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'stone' ] ) );

		$this->assertSame( [], LanguageLink::forPage( $en ) );
	}
}
