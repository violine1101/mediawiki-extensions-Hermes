<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::buildLink
 */
class BuildLinkTest extends HermesIntegrationTestCase {

	public function testInvalidTarget() {
		// "|" is not a legal title character, so Title::newFromText() returns null for
		// this; buildLink() must fall back to plain (escaped) text rather than erroring.
		$page = new PageInfo( ProjectTitle::makeTitleOnWiki( 'dewiki', NS_MAIN, 'Invalid|Title', 'de', null, '' ) );

		$this->assertSame( 'Invalid|Title', $page->buildLink() );
	}

	public function testProjectPageTarget() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'BuildLinkProjectTarget', 'eo', null, '' );
		$page = new PageInfo( $title );

		$this->assertSame(
			'<a href="https://translate.example.org/wiki/!eo:BuildLinkProjectTarget" class="extiw" '
				. 'title="eo:BuildLinkProjectTarget">BuildLinkProjectTarget</a>',
			$page->buildLink()
		);
	}

	public function testNamespacedProjectPageTarget() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::makeTitleOnWiki(
			'trwiki', NS_CATEGORY, 'BuildLinkNamespacedProjectTarget', 'eo', null, 'Category'
		);
		$page = new PageInfo( $title );

		// The "!xx:" prefix must land after the namespace, not before it. The link text uses the
		// Esperanto namespace name, the URL the wiki's own.
		$this->assertSame(
			'<a href="https://translate.example.org/wiki/Category:!eo:BuildLinkNamespacedProjectTarget" '
				. 'class="extiw" title="eo:Category:BuildLinkNamespacedProjectTarget">'
				. 'Kategorio:BuildLinkNamespacedProjectTarget</a>',
			$page->buildLink()
		);
	}
}
