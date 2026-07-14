<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageLinkHooks;

use MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks::onLanguageLinks
 */
class OnLanguageLinksTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseLanguage( 'dewiki', 'de' );
	}

	public function testAddsLink() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksTest' );
		$this->editPage( $page, '{{#hermes:shared_tag}}' );

		$partner = $this->makePageInfo( 'dewiki', 'OnLanguageLinksTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:OnLanguageLinksTest/de', $links );
	}

	public function testAddsLinkWithSection() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksSectionTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_section}}' );

		$partner = $this->makePageInfo( 'dewiki', 'OnLanguageLinksSectionTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_section#Some Section' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:OnLanguageLinksSectionTest/de#Some_Section', $links );
	}

	public function testDoesNotClobberExisting() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksClobberTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_2}}' );

		$partner = $this->makePageInfo( 'dewiki', 'OnLanguageLinksClobberTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_2' ] ) );

		$links = [ 'de:Manually_Authored_Link' ];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [ 'de:Manually_Authored_Link' ], $links );
	}

	public function testAddsLinkToProjectPage() {
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );

		$page = $this->getExistingTestPage( 'OnLanguageLinksProjectTest' );
		$this->editPage( $page, '{{#hermes:project_shared_tag}}' );

		$partner = $this->makePageInfo( WikiMap::getCurrentWikiId(), '!eo:OnLanguageLinksProjectTest' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'project_shared_tag' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		// The link target must not double up the "!xx:" prefix (e.g. "eo:!eo:...").
		$this->assertContains( 'eo:OnLanguageLinksProjectTest', $links );
	}

	public function testUnchangedWithoutTags() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksNoTagsTest' );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [], $links );
	}
}
