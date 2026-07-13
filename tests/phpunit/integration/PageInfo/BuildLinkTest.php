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
		$page->language = 'de';
		$page->fullTitle = 'BuildLinkValidTarget';
		$page->title = 'BuildLinkValidTarget';

		$this->assertMatchesRegularExpression(
			'#^<a href="[^"]*">BuildLinkValidTarget</a>$#',
			$page->buildLink()
		);
	}

	public function testInvalidTarget() {
		$page = new PageInfo();
		$page->language = 'de';
		// "|" is not a legal title character, so Title::newFromText() returns null for
		// this; buildLink() must fall back to plain (escaped) text rather than erroring.
		$page->fullTitle = 'Invalid|Title';
		$page->title = 'Invalid|Title';

		$this->assertSame( 'Invalid|Title', $page->buildLink() );
	}
}
