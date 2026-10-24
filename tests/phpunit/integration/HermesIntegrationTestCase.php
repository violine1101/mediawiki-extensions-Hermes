<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\HermesLanguage;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
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
	 * don't need to invent their own unique page ids.
	 *
	 * $wiki must already be registered (via registerBaseLanguage() for a foreign-wiki partner
	 * page, or the current wiki id for a same-wiki page, possibly a project page after
	 * registerProjectLanguage()) - a page's language is derived from its wiki's registrations, so
	 * its wiki isn't independently choosable from its language.
	 */
	protected function makePageInfo( string $wiki, ?string $title = null ): PageInfo {
		$id = $this->nextPageId++;
		[ $project, $baseTitle ] = ProjectTitle::parse( $title ?? "Page{$id}" );
		return new PageInfo( ProjectTitle::makeTitleOnWiki( $wiki, NS_MAIN, $baseTitle, $project, null, '' ), $id );
	}

	/**
	 * Directly inserts a base-language row, standing in for some other wiki having already
	 * registered itself (this doesn't exercise that self-registration, just consumes its
	 * result).
	 */
	protected function registerBaseLanguage(
		string $wiki, string $language, bool $active = true, ?string $url = null
	): void {
		$this->insertLanguage( new HermesLanguage( $language, $wiki, true, $active, $url ) );
	}

	/**
	 * Directly inserts a project-language row, standing in for some other wiki having already
	 * registered itself via LanguageStore::addProjectLanguage() (this doesn't exercise that
	 * registration, just consumes its result).
	 */
	protected function registerProjectLanguage(
		string $wiki, string $language, bool $active = true, ?string $url = null
	): void {
		$this->insertLanguage( new HermesLanguage( $language, $wiki, false, $active, $url ) );
	}

	/** Registers this wiki's base language and activates it, as a set-up wiki would have. */
	protected function activateLocalBaseLanguage(): void {
		LanguageStore::init();
		LanguageStore::activate( LanguageStore::getLocalBaseLanguage(), WikiMap::getCurrentWikiId() );
	}

	/** Sets the URL template of a wiki whose languages are registered already. */
	protected function setWikiUrl( string $wiki, string $url ): void {
		Hermes::getDB( DB_PRIMARY )->newUpdateQueryBuilder()
			->update( 'hermes_languages' )
			->set( [ 'hl_url' => $url ] )
			->where( [ 'hl_wiki' => $wiki ] )
			->caller( __METHOD__ )
			->execute();
		LanguageStore::clearCacheForTesting();
	}

	private function insertLanguage( HermesLanguage $language ): void {
		Hermes::getDB( DB_PRIMARY )->newInsertQueryBuilder()
			->insertInto( 'hermes_languages' )
			->row( $language->toRow() )
			->caller( __METHOD__ )
			->execute();
		LanguageStore::clearCacheForTesting();
	}
}
