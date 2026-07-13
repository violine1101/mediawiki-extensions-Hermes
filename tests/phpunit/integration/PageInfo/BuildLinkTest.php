<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::buildLink
 */
class BuildLinkTest extends HermesIntegrationTestCase {

	public function testValidTarget() {
		$page = new PageInfo();
		$page->translationProject = 'de';
		$page->title = 'BuildLinkValidTarget';

		$this->assertMatchesRegularExpression(
			'#^<a href="[^"]*">BuildLinkValidTarget</a>$#',
			$page->buildLink()
		);
	}

	public function testInvalidTarget() {
		$page = new PageInfo();
		$page->translationProject = 'de';
		// "|" is not a legal title character, so Title::newFromText() returns null for
		// this; buildLink() must fall back to plain (escaped) text rather than erroring.
		$page->title = 'Invalid|Title';

		$this->assertSame( 'Invalid|Title', $page->buildLink() );
	}

	public function testProjectPageTarget() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$page = new PageInfo();
		$page->translationProject = 'eo';
		$page->title = 'BuildLinkProjectTarget';

		$this->assertSame(
			'<a href="https://translate.example.org/wiki/!eo:BuildLinkProjectTarget">BuildLinkProjectTarget</a>',
			$page->buildLink()
		);
	}

	public function testNamespacedProjectPageTarget() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$page = new PageInfo();
		$page->translationProject = 'eo';
		$page->namespaceText = 'Category';
		$page->title = 'BuildLinkNamespacedProjectTarget';

		// The "!xx:" prefix must land after the namespace, not before it.
		$this->assertSame(
			'<a href="https://translate.example.org/wiki/Category:!eo:BuildLinkNamespacedProjectTarget">'
				. 'Category:BuildLinkNamespacedProjectTarget</a>',
			$page->buildLink()
		);
	}
}
