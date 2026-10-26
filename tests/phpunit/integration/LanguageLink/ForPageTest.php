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
		$this->registerBaseProject( 'de', 'dewiki', url: 'https://de.example.org/wiki/$1' );
		$this->registerTranslationProject( 'eo', 'trwiki', url: 'https://translate.example.org/wiki/$1' );

		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki', 'Stein' );
		$eoTitle = ProjectTitle::makeTitle( self::getProject( 'eo', 'trwiki' ), NS_MAIN, 'Ŝtono', '' );
		$eo = new PageInfo( $eoTitle, 1000 );
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

	public function testLinksToTaggedSection() {
		$this->registerBaseProject( 'de', 'dewiki', url: 'https://de.example.org/wiki/$1' );

		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki', 'Golem' );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'iron_golem' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'iron_golem=#Eisengolem' ] ) );

		$links = LanguageLink::forPage( $en );

		$this->assertSame( 'https://de.example.org/wiki/Golem#Eisengolem', $links['de']->url );
		$this->assertSame( 'Golem#Eisengolem', $links['de']->getDisplayTitle() );
	}

	public function testSkipsWikisWithoutUrl() {
		$this->registerBaseProject( 'de', 'dewiki' );

		$en = $this->makePageInfo( WikiMap::getCurrentWikiId() );
		$de = $this->makePageInfo( 'dewiki' );
		TagStore::setTagsForPage( $en, Tag::fromArgs( [ 'stone' ] ) );
		TagStore::setTagsForPage( $de, Tag::fromArgs( [ 'stone' ] ) );

		$this->assertSame( [], LanguageLink::forPage( $en ) );
	}
}
