<?php

namespace MediaWiki\Extension\Hermes\Tests\ParserFuncHooks;

use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Page\WikiPage;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\ParserFuncHooks::onLinksUpdateComplete
 */
class OnLinksUpdateCompleteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// getLinksForPage() leaves out the page's own language, so a partner page shows what's stored.
		$this->registerBaseProject( 'de', 'dewiki' );
		foreach ( [ 'first', 'second' ] as $tag ) {
			TagStore::setTagsForPage( $this->makePage( 'dewiki' ), Tag::fromArgs( [ $tag ] ) );
		}
	}

	/** @return string[] The tags the page's language links resolve by, keyed by language */
	private static function linkedTags( WikiPage $page ): array {
		$links = TagStore::getLinksForPage( LocalProjectTitle::fromTitle( $page->getTitle() ) );
		return array_map( static fn ( TagLink $link ) => $link->tag->name, $links );
	}

	public function testStoresTags() {
		$page = $this->getExistingTestPage( 'OnLinksUpdateCompleteStores' );
		$this->editPage( $page, '{{#hermes:first}}' );

		$this->assertSame( [ 'de' => 'first' ], self::linkedTags( $page ) );
	}

	public function testReplacesTags() {
		$page = $this->getExistingTestPage( 'OnLinksUpdateCompleteReplaces' );
		$this->editPage( $page, '{{#hermes:first}}' );
		$this->editPage( $page, '{{#hermes:second}}' );

		$this->assertSame( [ 'de' => 'second' ], self::linkedTags( $page ) );
	}

	public function testRemovingLastTagsClearsThem() {
		$page = $this->getExistingTestPage( 'OnLinksUpdateCompleteClears' );
		$this->editPage( $page, '{{#hermes:first}}' );
		$this->editPage( $page, 'No tags anymore' );

		$this->assertSame( [], self::linkedTags( $page ) );
	}
}
