<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks
 */
class LanguageLinkHooksTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	public function testAddsLanguageLinkForMatchingTag() {
		$page = $this->getExistingTestPage( 'LanguageLinkHooksTest' );
		$this->editPage( $page, '{{#hermes:shared_tag}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );
		$partner = new PageInfo();
		$partner->wiki = $pageInfo->wiki;
		$partner->id = 999002;
		$partner->fullTitle = 'LanguageLinkHooksTest/de';
		$partner->language = 'de';
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:LanguageLinkHooksTest/de', $links );
	}

	public function testAddsLanguageLinkWithSectionForMatchingTag() {
		$page = $this->getExistingTestPage( 'LanguageLinkHooksSectionTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_section}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );
		$partner = new PageInfo();
		$partner->wiki = $pageInfo->wiki;
		$partner->id = 999004;
		$partner->fullTitle = 'LanguageLinkHooksSectionTest/de';
		$partner->language = 'de';
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_section#Some Section' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:LanguageLinkHooksSectionTest/de#Some Section', $links );
	}

	public function testDoesNotClobberExistingLanguageLink() {
		$page = $this->getExistingTestPage( 'LanguageLinkHooksClobberTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_2}}' );

		$pageInfo = PageInfo::fromLocalPage( $page->getTitle() );
		$partner = new PageInfo();
		$partner->wiki = $pageInfo->wiki;
		$partner->id = 999003;
		$partner->fullTitle = 'LanguageLinkHooksClobberTest/de';
		$partner->language = 'de';
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_2' ] ) );

		$links = [ 'de:Manually_Authored_Link' ];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [ 'de:Manually_Authored_Link' ], $links );
	}

	public function testNoTagsLeavesLinksUnchanged() {
		$page = $this->getExistingTestPage( 'LanguageLinkHooksNoTagsTest' );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [], $links );
	}
}
