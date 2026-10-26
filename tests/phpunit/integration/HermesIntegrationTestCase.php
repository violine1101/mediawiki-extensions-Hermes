<?php

namespace MediaWiki\Extension\Hermes\Tests;

use LogicException;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * Common setup and fixture helpers shared by Hermes's @group Database tests.
 */
abstract class HermesIntegrationTestCase extends MediaWikiIntegrationTestCase {

	/** Hands out unique synthetic page ids; a plain per-test instance counter. */
	private int $nextPageId = 1;

	protected function setUp(): void {
		parent::setUp();
		ProjectStore::clearCacheForTesting();
	}

	/**
	 * Builds a synthetic ProjectTitle without going through Title/PageReference, so tests
	 * don't need to invent their own unique page ids.
	 *
	 * $wiki must already have projects (via registerBaseProject() for a foreign-wiki partner
	 * page, or the current wiki id for a same-wiki page, possibly a project page after
	 * registerTranslationProject()) - a page's language is derived from its wiki's projects, so
	 * its wiki isn't independently choosable from its language.
	 */
	protected function makePage( string $wiki, ?string $title = null ): ProjectTitle {
		$id = $this->nextPageId++;
		$baseTitle = $title ?? "Page{$id}";
		$language = null;
		if ( preg_match( '/^!([a-z0-9-]+):(.+)$/', $baseTitle, $match ) ) {
			[ , $language, $baseTitle ] = $match;
		}
		return ProjectTitle::make( self::getProject( $language, $wiki ), NS_MAIN, $baseTitle, '', $id );
	}

	/** $wiki's translation project in $language, or its base project if null, which must exist. */
	protected static function getProject( ?string $language, string $wiki ): Project {
		$project = $language !== null
			? ProjectStore::getProject( $language, $wiki )
			: ProjectStore::getBaseProject( $wiki );
		return $project ?? throw new LogicException( "No such project on $wiki: " . ( $language ?? 'base' ) );
	}

	/** A title on this wiki: in a translation project in $language (which needn't exist), else the base project. */
	protected static function localTitle(
		int $namespace,
		string $baseTitle,
		?string $language = null,
		?string $namespaceText = null
	): LocalProjectTitle {
		$project = $language !== null
			? new Project( $language, WikiMap::getCurrentWikiId(), false, true, null )
			: ProjectStore::getLocalBaseProject();
		return new LocalProjectTitle( $project, $namespace, $baseTitle, $namespaceText );
	}

	/**
	 * Directly inserts a base project row, standing in for some other wiki having already
	 * added it itself (this doesn't exercise that, just consumes its result).
	 */
	protected function registerBaseProject(
		string $language, string $wiki, bool $active = true, ?string $url = null
	): void {
		$this->insertProject( new Project( $language, $wiki, true, $active, $url ) );
	}

	/**
	 * Directly inserts a translation project row, standing in for some other wiki having already
	 * added it via ProjectStore::addTranslationProject() (this doesn't exercise that, just
	 * consumes its result).
	 */
	protected function registerTranslationProject(
		string $language, string $wiki, bool $active = true, ?string $url = null
	): void {
		$this->insertProject( new Project( $language, $wiki, false, $active, $url ) );
	}

	/** Adds this wiki's base project and activates it, as a set-up wiki would have. */
	protected function activateLocalBaseProject(): void {
		ProjectStore::init();
		ProjectStore::activate( ProjectStore::getLocalBaseLanguage(), WikiMap::getCurrentWikiId() );
	}

	/**
	 * Stores $wiki's namespace names, standing in for that wiki's own ProjectStore::init().
	 *
	 * @param string $wiki
	 * @param array<int, string> $names Namespace ID => name in DB key form
	 */
	protected function registerNamespaces( string $wiki, array $names ): void {
		$rows = [];
		foreach ( $names as $namespace => $text ) {
			$rows[] = [ 'hn_wiki' => $wiki, 'hn_namespace_id' => $namespace, 'hn_namespace_text' => $text ];
		}
		Hermes::getDB( DB_PRIMARY )->newInsertQueryBuilder()
			->insertInto( 'hermes_namespaces' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();
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
