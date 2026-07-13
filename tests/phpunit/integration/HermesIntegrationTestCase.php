<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * Common setup and fixture helpers shared by Hermes's @group Database tests.
 */
abstract class HermesIntegrationTestCase extends MediaWikiIntegrationTestCase {

	/** Hands out unique synthetic PageInfo ids; a plain per-test instance counter. */
	private int $nextPageId = 1;

	protected function setUp(): void {
		parent::setUp();
		LanguageStore::clearCacheForTesting();
	}

	/**
	 * Builds a synthetic PageInfo without going through Title/PageReference, so tests
	 * can freely control the language (which normally follows the current request/wiki and
	 * can't easily be varied for real pages in a single test wiki), and don't need to invent
	 * their own unique page ids.
	 *
	 * Always on the current wiki: a language is only ever served by one wiki in the family
	 * (see LanguageStore::validateLanguages()), so a page's wiki isn't independently
	 * choosable from its language - tests needing a specific other wiki should register it
	 * via registerBaseLanguage()/LanguageStore::addProjectLanguage() instead.
	 */
	protected function makePageInfo( string $language, ?string $title = null ): PageInfo {
		$page = new PageInfo();
		$page->wiki = WikiMap::getCurrentWikiId();
		$page->id = $this->nextPageId++;
		$page->fullTitle = $title ?? "Page{$page->id}";
		$page->language = $language;
		return $page;
	}

	/**
	 * Directly inserts a base-language row, standing in for some other wiki having already
	 * registered itself (this doesn't exercise that self-registration, just consumes its
	 * result).
	 */
	protected function registerBaseLanguage( string $wiki, string $language ): void {
		Hermes::getDB( DB_PRIMARY )->insert(
			'hermes_languages',
			[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => 1 ],
			__METHOD__
		);
		LanguageStore::clearCacheForTesting();
	}
}
