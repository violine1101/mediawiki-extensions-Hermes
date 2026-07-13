<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageLinkHooks;

use MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks::onLanguageLinks
 */
class OnLanguageLinksTest extends HermesIntegrationTestCase {

	public function testAddsLink() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksTest' );
		$this->editPage( $page, '{{#hermes:shared_tag}}' );

		$partner = $this->makePageInfo( 'de', 'OnLanguageLinksTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:OnLanguageLinksTest/de', $links );
	}

	public function testAddsLinkWithSection() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksSectionTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_section}}' );

		$partner = $this->makePageInfo( 'de', 'OnLanguageLinksSectionTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_section#Some Section' ] ) );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertContains( 'de:OnLanguageLinksSectionTest/de#Some Section', $links );
	}

	public function testDoesNotClobberExisting() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksClobberTest' );
		$this->editPage( $page, '{{#hermes:shared_tag_2}}' );

		$partner = $this->makePageInfo( 'de', 'OnLanguageLinksClobberTest/de' );
		TagStore::setTagsForPage( $partner, Tag::fromArgs( [ 'shared_tag_2' ] ) );

		$links = [ 'de:Manually_Authored_Link' ];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [ 'de:Manually_Authored_Link' ], $links );
	}

	public function testUnchangedWithoutTags() {
		$page = $this->getExistingTestPage( 'OnLanguageLinksNoTagsTest' );

		$links = [];
		$linkFlags = [];
		( new LanguageLinkHooks() )->onLanguageLinks( $page->getTitle(), $links, $linkFlags );

		$this->assertSame( [], $links );
	}
}
