<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
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
		ProjectStore::clearCacheForTesting();
	}

	/**
	 * Builds a synthetic PageInfo without going through Title/PageReference, so tests
	 * don't need to invent their own unique page ids.
	 *
	 * $wiki must already have projects (via registerBaseProject() for a foreign-wiki partner
	 * page, or the current wiki id for a same-wiki page, possibly a project page after
	 * registerTranslationProject()) - a page's language is derived from its wiki's projects, so
	 * its wiki isn't independently choosable from its language.
	 */
	protected function makePageInfo( string $wiki, ?string $title = null ): PageInfo {
		$id = $this->nextPageId++;
		[ $project, $baseTitle ] = ProjectTitle::parse( $title ?? "Page{$id}" );
		return new PageInfo( ProjectTitle::makeTitleOnWiki( $wiki, NS_MAIN, $baseTitle, $project, null, '' ), $id );
	}

	/**
	 * Directly inserts a base project row, standing in for some other wiki having already
	 * added it itself (this doesn't exercise that, just consumes its result).
	 */
	protected function registerBaseProject(
		string $wiki, string $language, bool $active = true, ?string $url = null
	): void {
		$this->insertProject( new Project( $language, $wiki, true, $active, $url ) );
	}

	/**
	 * Directly inserts a translation project row, standing in for some other wiki having already
	 * added it via ProjectStore::addTranslationProject() (this doesn't exercise that, just
	 * consumes its result).
	 */
	protected function registerTranslationProject(
		string $wiki, string $language, bool $active = true, ?string $url = null
	): void {
		$this->insertProject( new Project( $language, $wiki, false, $active, $url ) );
	}

	/** Adds this wiki's base project and activates it, as a set-up wiki would have. */
	protected function activateLocalBaseProject(): void {
		ProjectStore::init();
		ProjectStore::activate( ProjectStore::getLocalBaseLanguage(), WikiMap::getCurrentWikiId() );
	}

	/** Sets the URL template of a wiki that has projects already. */
	protected function setWikiUrl( string $wiki, string $url ): void {
		Hermes::getDB( DB_PRIMARY )->newUpdateQueryBuilder()
			->update( 'hermes_projects' )
			->set( [ 'hp_url' => $url ] )
			->where( [ 'hp_wiki' => $wiki ] )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();
	}

	private function insertProject( Project $project ): void {
		Hermes::getDB( DB_PRIMARY )->newInsertQueryBuilder()
			->insertInto( 'hermes_projects' )
			->row( $project->toRow() )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();
	}
}
