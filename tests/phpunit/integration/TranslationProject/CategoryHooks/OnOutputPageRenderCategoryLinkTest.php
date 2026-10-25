<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\CategoryHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\CategoryHooks::onOutputPageRenderCategoryLink
 */
class OnOutputPageRenderCategoryLinkTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
	}

	public function testStripsProjectPrefixFromDisplayText() {
		$hooks = new CategoryHooks();
		$outputPage = new OutputPage( RequestContext::getMain() );
		$categoryTitle = Title::makeTitle( NS_CATEGORY, '!eo:Bar' )->toPageIdentity();

		$link = null;
		$hooks->onOutputPageRenderCategoryLink( $outputPage, $categoryTitle, '!eo:Bar', $link );

		$this->assertNotNull( $link );
		$this->assertStringContainsString( 'title=Category:!eo:Bar', $link );
		$this->assertStringContainsString( '>Bar<', $link );
	}

	public function testDisplayTextUsesSpaces() {
		$hooks = new CategoryHooks();
		$outputPage = new OutputPage( RequestContext::getMain() );
		$categoryTitle = Title::makeTitle( NS_CATEGORY, '!eo:Bar_Baz' )->toPageIdentity();

		$link = null;
		$hooks->onOutputPageRenderCategoryLink( $outputPage, $categoryTitle, '!eo:Bar Baz', $link );

		$this->assertStringContainsString( '>Bar Baz<', $link );
	}

	public function testLeavesNonProjectCategoryUntouched() {
		$hooks = new CategoryHooks();
		$outputPage = new OutputPage( RequestContext::getMain() );
		$categoryTitle = Title::makeTitle( NS_CATEGORY, 'Bar' )->toPageIdentity();

		$link = null;
		$hooks->onOutputPageRenderCategoryLink( $outputPage, $categoryTitle, 'Bar', $link );

		$this->assertNull( $link );
	}
}
