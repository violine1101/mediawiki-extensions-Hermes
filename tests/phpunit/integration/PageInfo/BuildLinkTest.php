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

	public function testUnregisteredWikiGivesPlainText() {
		$page = new PageInfo( ProjectTitle::makeTitleOnWiki( 'dewiki', NS_MAIN, 'Foo_<b>', 'de', null, '' ) );

		$this->assertSame( 'Foo &lt;b&gt;', $page->buildLink() );
	}

	public function testProjectPageTarget() {
		$this->registerTranslationProject( 'trwiki', 'eo', url: 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::makeTitleOnWiki( 'trwiki', NS_MAIN, 'BuildLinkProjectTarget', 'eo', null, '' );
		$page = new PageInfo( $title );

		$this->assertSame(
			'<a href="https://translate.example.org/wiki/!eo:BuildLinkProjectTarget" class="extiw" '
				. 'title="BuildLinkProjectTarget – Esperanto">BuildLinkProjectTarget</a>',
			$page->buildLink()
		);
	}

	public function testNamespacedProjectPageTarget() {
		$this->registerTranslationProject( 'trwiki', 'eo', url: 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::makeTitleOnWiki(
			'trwiki', NS_CATEGORY, 'BuildLinkNamespacedProjectTarget', 'eo', null, 'Category'
		);
		$page = new PageInfo( $title );

		// The "!xx:" prefix must land after the namespace, not before it. The link text uses the
		// Esperanto namespace name, the URL the wiki's own.
		$this->assertSame(
			'<a href="https://translate.example.org/wiki/Category:!eo:BuildLinkNamespacedProjectTarget" '
				. 'class="extiw" title="Kategorio:BuildLinkNamespacedProjectTarget – Esperanto">'
				. 'Kategorio:BuildLinkNamespacedProjectTarget</a>',
			$page->buildLink()
		);
	}
}
