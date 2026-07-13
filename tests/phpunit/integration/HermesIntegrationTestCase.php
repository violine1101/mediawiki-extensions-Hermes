<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\WikiStore;
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
		WikiStore::clearCacheForTesting();
	}

	/**
	 * Builds a synthetic PageInfo without going through Title/PageReference, so tests
	 * don't need to invent their own unique page ids.
	 *
	 * $wiki must already be registered (via registerBaseLanguage() for a foreign-wiki partner
	 * page, or the current wiki id for a same-wiki page, possibly a project page after
	 * registerProjectLanguage()) - a language is only ever served by one wiki in the family
	 * (see LanguageStore::validateLanguages()), so a page's wiki isn't independently
	 * choosable from its language.
	 */
	protected function makePageInfo( string $wiki, ?string $title = null ): PageInfo {
		$page = new PageInfo();
		$page->wiki = $wiki;
		$page->id = $this->nextPageId++;
		$title ??= "Page{$page->id}";
		[ $page->translationProject, $page->title ] = PageInfo::parseTitle( $title );
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

	/**
	 * Directly inserts a project-language row, standing in for some other wiki having already
	 * registered itself via LanguageStore::addProjectLanguage() (this doesn't exercise that
	 * registration, just consumes its result).
	 */
	protected function registerProjectLanguage( string $wiki, string $language ): void {
		Hermes::getDB( DB_PRIMARY )->insert(
			'hermes_languages',
			[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => 0 ],
			__METHOD__
		);
		LanguageStore::clearCacheForTesting();
	}

	/**
	 * Directly inserts a wiki's URL template, standing in for that wiki having already
	 * registered itself via WikiStore::init() (this doesn't exercise that registration, just
	 * consumes its result).
	 */
	protected function registerWiki( string $wiki, string $url ): void {
		Hermes::getDB( DB_PRIMARY )->insert(
			'hermes_wikis',
			[ 'hw_wiki' => $wiki, 'hw_url' => $url ],
			__METHOD__
		);
		WikiStore::clearCacheForTesting();
	}
}
