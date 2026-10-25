<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
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

	/**
	 * Cached hermes_projects table, keyed by language code and wiki ID.
	 *
	 * @var array<string, array<string, Project>>|null
	 */
	private static ?array $projects = null;

	/**
	 * Clears the $projects cache above, for use in testing.
	 * This makes sure that the tests don't pollute each other.
	 */
	public static function clearCacheForTesting(): void {
		self::$projects = null;
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

	/**
	 * Look up the wiki of a language's active project (base or translation).
	 *
	 * @param string $language
	 * @return string|null The wiki ID, or null if no project in that language is active.
	 */
	public static function getWikiForLanguage( string $language ): ?string {
		return ( self::getActiveProjects()[ $language ] ?? null )?->wiki;
	}

	/**
	 * All active base projects.
	 *
	 * @return array<string, Project> Keyed by language code.
	 */
	public static function getBaseProjects(): array {
		return array_filter( self::getActiveProjects(), static fn ( Project $project ) => $project->isBase );
	}

	/** Whether $wiki has a project in $language and it's active. */
	public static function isActive( string $language, string $wiki ): bool {
		return ( self::getProjects()[ $language ][ $wiki ] ?? null )?->isActive ?? false;
	}

	/**
	 * Whether the current wiki has a translation project in the given language, active or not.
	 *
	 * @param string $language
	 * @return bool
	 */
	public static function isLocalTranslationProject( string $language ): bool {
		return ( self::getProjects()[ $language ][ WikiMap::getCurrentWikiId() ] ?? null )?->isBase === false;
	}

	/** @return string[] The languages of the current wiki's translation projects, active or not, sorted */
	public static function getLocalTranslationProjects(): array {
		return self::filterLocalTranslationProjects( static fn ( Project $project ) => true );
	}

	/** @return string[] The languages of the current wiki's active translation projects, sorted */
	public static function getActiveLocalTranslationProjects(): array {
		return self::filterLocalTranslationProjects( static fn ( Project $project ) => $project->isActive );
	}

	/**
	 * Look up the language of a given wiki's base project, active or not.
	 *
	 * @param string $wiki
	 * @return string|null The wiki's base language code, or null if the wiki has no projects.
	 */
	public static function getBaseLanguageForWiki( string $wiki ): ?string {
		foreach ( self::forWiki( $wiki ) as $project ) {
			if ( $project->isBase ) {
				return $project->code;
			}
		}

		return null;
	}

	/**
	 * Look up the URL template (with "$1" as the page title placeholder) for a given wiki.
	 *
	 * @param string $wiki
	 * @return string|null The URL template, or null if none is known for it.
	 */
	public static function getUrlTemplate( string $wiki ): ?string {
		foreach ( self::forWiki( $wiki ) as $project ) {
			if ( $project->urlTemplate !== null ) {
				return $project->urlTemplate;
			}
		}

		return null;
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
	 * updating its URL template if it changed.
	 */
	public static function init(): void {
		self::loadProjects();

		$wiki = WikiMap::getCurrentWikiId();
		$code = self::getLocalBaseLanguage();
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$url = $config->get( MainConfigNames::Server ) . $config->get( MainConfigNames::ArticlePath );

		if ( self::getBaseLanguageForWiki( $wiki ) === null && !isset( self::$projects[ $code ][ $wiki ] ) ) {
			self::insert( new Project( $code, $wiki, true, false, $url ) );
		}

		$outdated = array_filter(
			self::forWiki( $wiki ), static fn ( Project $project ) => $project->urlTemplate !== $url
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
	}

	/**
	 * Add a new translation project to a given wiki: active, unless a project in its language is already
	 * active on another wiki.
	 *
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @param string $language Language code of the translation project.
	 * @return bool True if newly added, false if $wiki already had it.
	 * @throws LanguageConflictException If the language is $wiki's base language.
	 */
	public static function addTranslationProject( string $wiki, string $language ): bool {
		return self::withLock( static function () use ( $wiki, $language ) {
			$existing = self::$projects[ $language ][ $wiki ] ?? null;
			if ( $existing?->isBase ) {
				throw new LanguageConflictException( $language, $wiki, $wiki );
			}
			if ( $existing !== null ) {
				return false;
			}

			$isActive = self::getWikiForLanguage( $language ) === null;
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
	 * @return string|null The wiki whose project was active, or null if none.
	 */
	public static function deactivate( string $language ): ?string {
		return self::withLock( static function () use ( $language ) {
			$wiki = self::getWikiForLanguage( $language );
			if ( $wiki === null ) {
				return null;
			}
			self::setActive( $language, null );
			return $wiki;
		} );
	}

	/** @return Project[] The projects of $wiki */
	private static function forWiki( string $wiki ): array {
		$projects = [];
		foreach ( self::getProjects() as $byWiki ) {
			if ( isset( $byWiki[ $wiki ] ) ) {
				$projects[] = $byWiki[ $wiki ];
			}
		}
		return $projects;
	}

	/** @return string[] */
	private static function filterLocalTranslationProjects( callable $filter ): array {
		$languages = [];
		foreach ( self::forWiki( WikiMap::getCurrentWikiId() ) as $project ) {
			if ( !$project->isBase && $filter( $project ) ) {
				$languages[] = $project->code;
			}
		}
		sort( $languages );
		return $languages;
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
