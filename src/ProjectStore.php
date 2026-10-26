<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

/**
 * Tracks the wikis of the family and their projects (hermes_projects, as Project objects).
 * Each wiki adds its own base project (in $wgLanguageCode) on first use, along with its URL template
 * (from its own $wgServer/$wgArticlePath), which every row of the wiki repeats.
 * Translation projects are added on top of that via addTranslationProject().
 * Several wikis can have a project in the same language (e.g. while a project moves to its own wiki), but at
 * most one of them is active: only active pages are linked to and checked for conflicts. Base projects are
 * added as inactive.
 */
class ProjectStore {

	private const TABLE_NAME = 'hermes_projects';
	private const NAMESPACES_TABLE = 'hermes_namespaces';

	/**
	 * Cached hermes_projects table, keyed by language code and wiki ID.
	 *
	 * @var array<string, array<string, Project>>|null
	 */
	private static ?array $projects = null;

	/**
	 * Cached hermes_namespaces table: namespace names in DB key form, keyed by wiki ID and namespace ID.
	 *
	 * @var array<string, array<int, string>>|null
	 */
	private static ?array $namespaces = null;

	/**
	 * Clears the $projects cache above, for use in testing.
	 * This makes sure that the tests don't pollute each other.
	 */
	public static function clearCacheForTesting(): void {
		self::$projects = null;
		self::$namespaces = null;
	}

	/**
	 * Look up the language of the current wiki's base project.
	 *
	 * @return string The wiki's base language code.
	 */
	public static function getLocalBaseLanguage(): string {
		return MediaWikiServices::getInstance()->getContentLanguage()->getCode();
	}

	/**
	 * All projects, base and translation, active or not.
	 *
	 * @return array<string, array<string, Project>> Keyed by language code and wiki ID.
	 */
	public static function getProjects(): array {
		if ( self::$projects === null ) {
			self::init();
		}

		return self::$projects;
	}

	/** $wiki's project in $language, active or not, if any. */
	public static function getProject( string $language, string $wiki ): ?Project {
		return self::getProjects()[ $language ][ $wiki ] ?? null;
	}

	/**
	 * The active project of each language.
	 *
	 * @return array<string, Project> Keyed by language code.
	 */
	public static function getActiveProjects(): array {
		$projects = [];
		foreach ( self::getProjects() as $code => $byWiki ) {
			foreach ( $byWiki as $project ) {
				if ( $project->isActive ) {
					$projects[ $code ] = $project;
				}
			}
		}

		return $projects;
	}

	/** The current wiki's base project, which init() makes sure exists. */
	public static function getLocalBaseProject(): Project {
		$wiki = WikiMap::getCurrentWikiId();
		// Usually the project in the content language, which avoids going through all projects.
		$project = self::getProject( self::getLocalBaseLanguage(), $wiki );
		return $project?->isBase ? $project : self::getBaseProject( $wiki );
	}

	/** The project with this ID (see Project::hasId()), if any. */
	public static function getProjectById( string $id ): ?Project {
		foreach ( self::getProjects()[ Project::languageOfId( $id ) ] ?? [] as $project ) {
			if ( $project->hasId( $id ) ) {
				return $project;
			}
		}
		return null;
	}

	/** The active project in $language (base or translation), if any. */
	public static function getActiveProject( string $language ): ?Project {
		return self::getActiveProjects()[ $language ] ?? null;
	}

	/**
	 * All active base projects.
	 *
	 * @return array<string, Project> Keyed by language code.
	 */
	public static function getActiveBaseProjects(): array {
		return array_filter( self::getActiveProjects(), static fn ( Project $project ) => $project->isBase );
	}

	/** $wiki's base project, active or not, if the wiki is known. */
	public static function getBaseProject( string $wiki ): ?Project {
		foreach ( self::getProjectsOfWiki( $wiki ) as $project ) {
			if ( $project->isBase ) {
				return $project;
			}
		}

		return null;
	}

	/** @return array<string, Project> $wiki's projects, keyed by language code */
	public static function getProjectsOfWiki( string $wiki ): array {
		$projects = [];
		foreach ( self::getProjects() as $code => $byWiki ) {
			if ( isset( $byWiki[ $wiki ] ) ) {
				$projects[ $code ] = $byWiki[ $wiki ];
			}
		}
		return $projects;
	}

	/** @return array<string, Project> The current wiki's translation projects, active or not, sorted by language */
	public static function getLocalTranslationProjects(): array {
		$projects = array_filter(
			self::getProjectsOfWiki( WikiMap::getCurrentWikiId() ), static fn ( Project $project ) => !$project->isBase
		);
		ksort( $projects );
		return $projects;
	}

	/** @return array<string, Project> The current wiki's active translation projects, sorted by language */
	public static function getActiveLocalTranslationProjects(): array {
		return array_filter(
			self::getLocalTranslationProjects(), static fn ( Project $project ) => $project->isActive
		);
	}

	/**
	 * Look up the URL template (with "$1" as the page title placeholder) for a given wiki.
	 *
	 * @param string $wiki
	 * @return string|null The URL template, or null if none is known for it.
	 */
	public static function getUrlTemplate( string $wiki ): ?string {
		foreach ( self::getProjectsOfWiki( $wiki ) as $project ) {
			if ( $project->urlTemplate !== null ) {
				return $project->urlTemplate;
			}
		}

		return null;
	}

	/**
	 * The name of namespace $namespace on $wiki, in DB key form ('' for the main namespace); its canonical name
	 * (or '') if the wiki doesn't (or no longer) have it.
	 */
	public static function getNamespaceText( string $wiki, int $namespace ): string {
		return self::getNamespaces()[ $wiki ][ $namespace ]
			?? (string)MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalName( $namespace );
	}

	/** The ID of the namespace named $text (DB key form) on $wiki, if any. */
	public static function getNamespaceId( string $wiki, string $text ): ?int {
		$namespace = array_search( $text, self::getNamespaces()[ $wiki ] ?? [], true );
		return $namespace === false ? null : $namespace;
	}

	/** @return string[] IDs of all wikis with a project. */
	public static function getWikiIds(): array {
		$wikis = [];
		foreach ( self::getProjects() as $byWiki ) {
			$wikis += array_fill_keys( array_keys( $byWiki ), true );
		}
		return array_keys( $wikis );
	}

	/**
	 * Populates the cache, adding this wiki's base project (as inactive) if the wiki doesn't have one yet, and
	 * updating its URL template and namespace names if they changed.
	 */
	public static function init(): void {
		self::loadProjects();

		$wiki = WikiMap::getCurrentWikiId();
		$code = self::getLocalBaseLanguage();
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$url = $config->get( MainConfigNames::Server ) . $config->get( MainConfigNames::ArticlePath );

		if ( self::getBaseProject( $wiki ) === null && !isset( self::$projects[ $code ][ $wiki ] ) ) {
			self::insert( new Project( $code, $wiki, true, false, $url ) );
		}

		$outdated = array_filter(
			self::getProjectsOfWiki( $wiki ), static fn ( Project $project ) => $project->urlTemplate !== $url
		);
		if ( $outdated ) {
			Hermes::getDB( DB_PRIMARY )->newUpdateQueryBuilder()
				->update( self::TABLE_NAME )
				->set( [ 'hp_url' => $url ] )
				->where( [ 'hp_wiki' => $wiki ] )
				->caller( __METHOD__ )
				->execute();
			foreach ( $outdated as $project ) {
				self::$projects[ $project->code ][ $wiki ] = $project->withUrlTemplate( $url );
			}
		}

		self::syncNamespaces( $wiki );
	}

	/**
	 * Add a new translation project to a given wiki: active, unless a project in its language is already
	 * active on another wiki.
	 *
	 * @param string $language Language code of the translation project.
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @return bool True if newly added, false if $wiki already had it.
	 * @throws InvalidArgumentException If the language is $wiki's base language.
	 */
	public static function addTranslationProject( string $language, string $wiki ): bool {
		return self::withLock( static function () use ( $language, $wiki ) {
			$existing = self::$projects[ $language ][ $wiki ] ?? null;
			if ( $existing?->isBase ) {
				throw new InvalidArgumentException( "\"{$language}\" is the base language of wiki \"{$wiki}\"." );
			}
			if ( $existing !== null ) {
				return false;
			}

			$isActive = self::getActiveProject( $language ) === null;
			self::insert( new Project( $language, $wiki, false, $isActive, self::getUrlTemplate( $wiki ) ) );
			return true;
		} );
	}

	/**
	 * Makes $wiki's project in $language active, and every other project in that language inactive.
	 *
	 * @return bool False if it already was active.
	 * @throws InvalidArgumentException If $wiki has no project in $language.
	 */
	public static function activate( string $language, string $wiki ): bool {
		return self::withLock( static function () use ( $language, $wiki ) {
			$project = self::$projects[ $language ][ $wiki ] ?? null;
			if ( $project === null ) {
				throw new InvalidArgumentException( "Wiki \"{$wiki}\" has no project in language \"{$language}\"." );
			}
			if ( $project->isActive ) {
				return false;
			}
			self::setActive( $language, $wiki );
			return true;
		} );
	}

	/**
	 * Makes every project in $language inactive.
	 *
	 * @return Project|null The project that was active (as it was), or null if none.
	 */
	public static function deactivate( string $language ): ?Project {
		return self::withLock( static function () use ( $language ) {
			$active = self::getActiveProject( $language );
			if ( $active !== null ) {
				self::setActive( $language, null );
			}
			return $active;
		} );
	}

	/**
	 * Runs $callback with hermes_projects locked and freshly loaded into the cache.
	 *
	 * @param callable(): mixed $callback
	 * @return mixed What $callback returns.
	 */
	private static function withLock( callable $callback ): mixed {
		// Adds this wiki first, if it's new.
		self::getProjects();

		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->lock( self::TABLE_NAME, __METHOD__ );
		try {
			self::loadProjects();
			return $callback();
		} finally {
			$dbw->unlock( self::TABLE_NAME, __METHOD__ );
		}
	}

	/**
	 * Loads the projects from hermes_projects into cache,
	 * discarding whatever was cached before.
	 */
	private static function loadProjects(): void {
		$rows = Hermes::getDB()->newSelectQueryBuilder()
			->select( [ 'hp_language', 'hp_wiki', 'hp_base', 'hp_active', 'hp_url' ] )
			->from( self::TABLE_NAME )
			->caller( __METHOD__ )
			->fetchResultSet();

		self::$projects = [];
		foreach ( $rows as $row ) {
			$project = Project::fromRow( $row );
			self::$projects[ $project->code ][ $project->wiki ] = $project;
		}
	}

	/** Makes $wiki's project in $language active and all others in it inactive, or all of them if null. */
	private static function setActive( string $language, ?string $wiki ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( self::TABLE_NAME )
			->set( [ 'hp_active' => 0 ] )
			->where( [ 'hp_language' => $language ] )
			->caller( __METHOD__ )
			->execute();
		if ( $wiki !== null ) {
			$dbw->newUpdateQueryBuilder()
				->update( self::TABLE_NAME )
				->set( [ 'hp_active' => 1 ] )
				->where( [ 'hp_language' => $language, 'hp_wiki' => $wiki ] )
				->caller( __METHOD__ )
				->execute();
		}

		foreach ( self::$projects[ $language ] as $otherWiki => $project ) {
			self::$projects[ $language ][ $otherWiki ] = $project->withActive( $otherWiki === $wiki );
		}
	}

	/** @return array<string, array<int, string>> */
	private static function getNamespaces(): array {
		if ( self::$namespaces === null ) {
			$rows = Hermes::getDB()->newSelectQueryBuilder()
				->select( [ 'hn_wiki', 'hn_namespace_id', 'hn_namespace_text' ] )
				->from( self::NAMESPACES_TABLE )
				->caller( __METHOD__ )
				->fetchResultSet();
			self::$namespaces = [];
			foreach ( $rows as $row ) {
				self::$namespaces[ $row->hn_wiki ][ (int)$row->hn_namespace_id ] = $row->hn_namespace_text;
			}
		}
		return self::$namespaces;
	}

	/** Stores $wiki's (this wiki's) namespace names, if they changed. */
	private static function syncNamespaces( string $wiki ): void {
		$current = MediaWikiServices::getInstance()->getContentLanguage()->getNamespaces();
		$stored = self::getNamespaces()[ $wiki ] ?? [];
		ksort( $current );
		ksort( $stored );
		if ( $current === $stored ) {
			return;
		}

		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( self::NAMESPACES_TABLE )
			->where( [ 'hn_wiki' => $wiki ] )
			->caller( __METHOD__ )
			->execute();
		$rows = [];
		foreach ( $current as $namespace => $text ) {
			$rows[] = [ 'hn_wiki' => $wiki, 'hn_namespace_id' => $namespace, 'hn_namespace_text' => $text ];
		}
		// Ignored if a concurrent request added them first.
		$dbw->newInsertQueryBuilder()
			->insertInto( self::NAMESPACES_TABLE )
			->ignore()
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
		$dbw->endAtomic( __METHOD__ );
		self::$namespaces[ $wiki ] = $current;
	}

	private static function insert( Project $project ): void {
		// Ignored if a concurrent request added it first.
		Hermes::getDB( DB_PRIMARY )->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->ignore()
			->row( $project->toRow() )
			->caller( __METHOD__ )
			->execute();

		self::$projects[ $project->code ][ $project->wiki ] = $project;
	}
}
