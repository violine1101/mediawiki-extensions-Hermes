<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;
use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

/**
 * Tracks the wikis of the family and which languages are available on which wiki (hermes_languages, as
 * HermesLanguage objects).
 * Each wiki registers its own base language (from $wgLanguageCode) on first use, along with its URL template
 * (from its own $wgServer/$wgArticlePath), which every row of the wiki repeats.
 * Translation project languages are registered on top of that via addProjectLanguage().
 * A language can be registered on several wikis (e.g. while a project moves to its own wiki), but is
 * active on at most one: only active pages are linked to and checked for conflicts. Base languages are
 * registered as inactive.
 */
class LanguageStore {

	private const TABLE_NAME = 'hermes_languages';

	/**
	 * Cached hermes_languages table, keyed by language code and wiki ID.
	 *
	 * @var array<string, array<string, HermesLanguage>>|null
	 */
	private static ?array $languages = null;

	/**
	 * Clears the $languages cache above, for use in testing.
	 * This makes sure that the tests don't pollute each other.
	 */
	public static function clearCacheForTesting(): void {
		self::$languages = null;
	}

	/**
	 * Look up the base language for the current wiki.
	 *
	 * @return string The wiki's base language code.
	 */
	public static function getLocalBaseLanguage(): string {
		return MediaWikiServices::getInstance()->getContentLanguage()->getCode();
	}

	/**
	 * All registered languages, base and project, active or not.
	 *
	 * @return array<string, array<string, HermesLanguage>> Keyed by language code and wiki ID.
	 */
	public static function getLanguages(): array {
		if ( self::$languages === null ) {
			self::init();
		}

		return self::$languages;
	}

	/**
	 * The active registration of each language.
	 *
	 * @return array<string, HermesLanguage> Keyed by language code.
	 */
	public static function getActiveLanguages(): array {
		$languages = [];
		foreach ( self::getLanguages() as $code => $byWiki ) {
			foreach ( $byWiki as $language ) {
				if ( $language->isActive ) {
					$languages[ $code ] = $language;
				}
			}
		}

		return $languages;
	}

	/**
	 * Look up the wiki a given language (base or project) is active on.
	 *
	 * @param string $language
	 * @return string|null The wiki ID, or null if it isn't active anywhere.
	 */
	public static function getWikiForLanguage( string $language ): ?string {
		return ( self::getActiveLanguages()[ $language ] ?? null )?->wiki;
	}

	/**
	 * All active base languages.
	 *
	 * @return array<string, HermesLanguage> Keyed by language code.
	 */
	public static function getBaseLanguages(): array {
		return array_filter( self::getActiveLanguages(), static fn ( HermesLanguage $language ) => $language->isBase );
	}

	/** Whether $language is registered on $wiki and active there. */
	public static function isActive( string $language, string $wiki ): bool {
		return ( self::getLanguages()[ $language ][ $wiki ] ?? null )?->isActive ?? false;
	}

	/**
	 * Whether the given language is registered as a translation project language for the
	 * current wiki, active or not.
	 *
	 * @param string $language
	 * @return bool
	 */
	public static function isLocalProjectLanguage( string $language ): bool {
		return ( self::getLanguages()[ $language ][ WikiMap::getCurrentWikiId() ] ?? null )?->isBase === false;
	}

	/** @return string[] The current wiki's translation project languages, active or not, sorted */
	public static function getLocalProjectLanguages(): array {
		return self::filterLocalProjects( static fn ( HermesLanguage $language ) => true );
	}

	/** @return string[] The current wiki's active translation project languages, sorted */
	public static function getActiveLocalProjectLanguages(): array {
		return self::filterLocalProjects( static fn ( HermesLanguage $language ) => $language->isActive );
	}

	/**
	 * Look up the base language registered for a given wiki, active or not.
	 *
	 * @param string $wiki
	 * @return string|null The wiki's base language code, or null if it isn't registered.
	 */
	public static function getBaseLanguageForWiki( string $wiki ): ?string {
		foreach ( self::forWiki( $wiki ) as $language ) {
			if ( $language->isBase ) {
				return $language->code;
			}
		}

		return null;
	}

	/**
	 * Look up the URL template (with "$1" as the page title placeholder) for a given wiki.
	 *
	 * @param string $wiki
	 * @return string|null The URL template, or null if none is registered for it.
	 */
	public static function getUrlTemplate( string $wiki ): ?string {
		foreach ( self::forWiki( $wiki ) as $language ) {
			if ( $language->urlTemplate !== null ) {
				return $language->urlTemplate;
			}
		}

		return null;
	}

	/** @return string[] IDs of all wikis with a registered language. */
	public static function getWikiIds(): array {
		$wikis = [];
		foreach ( self::getLanguages() as $byWiki ) {
			$wikis += array_fill_keys( array_keys( $byWiki ), true );
		}
		return array_keys( $wikis );
	}

	/**
	 * Populates the cache, registering this wiki's base language (as inactive) if the wiki doesn't have one
	 * yet, and updating its URL template if it changed.
	 */
	public static function init(): void {
		self::loadLanguages();

		$wiki = WikiMap::getCurrentWikiId();
		$code = self::getLocalBaseLanguage();
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$url = $config->get( MainConfigNames::Server ) . $config->get( MainConfigNames::ArticlePath );

		if ( self::getBaseLanguageForWiki( $wiki ) === null && !isset( self::$languages[ $code ][ $wiki ] ) ) {
			self::insert( new HermesLanguage( $code, $wiki, true, false, $url ) );
		}

		$outdated = array_filter(
			self::forWiki( $wiki ), static fn ( HermesLanguage $language ) => $language->urlTemplate !== $url
		);
		if ( $outdated ) {
			Hermes::getDB( DB_PRIMARY )->newUpdateQueryBuilder()
				->update( self::TABLE_NAME )
				->set( [ 'hl_url' => $url ] )
				->where( [ 'hl_wiki' => $wiki ] )
				->caller( __METHOD__ )
				->execute();
			foreach ( $outdated as $language ) {
				self::$languages[ $language->code ][ $wiki ] = $language->withUrlTemplate( $url );
			}
		}
	}

	/**
	 * Register a new translation project language for a given wiki: active, unless the language is already
	 * active on another wiki.
	 *
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @param string $language Language code of the project language.
	 * @return bool True if newly registered, false if it was already registered on $wiki.
	 * @throws LanguageConflictException If the language is $wiki's base language.
	 */
	public static function addProjectLanguage( string $wiki, string $language ): bool {
		return self::withLock( static function () use ( $wiki, $language ) {
			$existing = self::$languages[ $language ][ $wiki ] ?? null;
			if ( $existing?->isBase ) {
				throw new LanguageConflictException( $language, $wiki, $wiki );
			}
			if ( $existing !== null ) {
				return false;
			}

			$isActive = self::getWikiForLanguage( $language ) === null;
			self::insert( new HermesLanguage( $language, $wiki, false, $isActive, self::getUrlTemplate( $wiki ) ) );
			return true;
		} );
	}

	/**
	 * Makes $language active on $wiki, and inactive on every other wiki it's registered on.
	 *
	 * @return bool False if it already was active there.
	 * @throws InvalidArgumentException If $language isn't registered on $wiki.
	 */
	public static function activate( string $language, string $wiki ): bool {
		return self::withLock( static function () use ( $language, $wiki ) {
			$registration = self::$languages[ $language ][ $wiki ] ?? null;
			if ( $registration === null ) {
				throw new InvalidArgumentException( "Language \"{$language}\" isn't registered on wiki \"{$wiki}\"." );
			}
			if ( $registration->isActive ) {
				return false;
			}
			self::setActive( $language, $wiki );
			return true;
		} );
	}

	/**
	 * Makes $language inactive on every wiki.
	 *
	 * @return string|null The wiki it was active on, or null if none.
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

	/** @return HermesLanguage[] The languages registered on $wiki */
	private static function forWiki( string $wiki ): array {
		$languages = [];
		foreach ( self::getLanguages() as $byWiki ) {
			if ( isset( $byWiki[ $wiki ] ) ) {
				$languages[] = $byWiki[ $wiki ];
			}
		}
		return $languages;
	}

	/** @return string[] */
	private static function filterLocalProjects( callable $filter ): array {
		$projects = [];
		foreach ( self::forWiki( WikiMap::getCurrentWikiId() ) as $language ) {
			if ( !$language->isBase && $filter( $language ) ) {
				$projects[] = $language->code;
			}
		}
		sort( $projects );
		return $projects;
	}

	/**
	 * Runs $callback with hermes_languages locked and freshly loaded into the cache.
	 *
	 * @param callable(): mixed $callback
	 * @return mixed What $callback returns.
	 */
	private static function withLock( callable $callback ): mixed {
		// Registers this wiki first, if it's new.
		self::getLanguages();

		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->lock( self::TABLE_NAME, __METHOD__ );
		try {
			self::loadLanguages();
			return $callback();
		} finally {
			$dbw->unlock( self::TABLE_NAME, __METHOD__ );
		}
	}

	/**
	 * Loads the language definitions from hermes_languages into cache,
	 * discarding whatever was cached before.
	 */
	private static function loadLanguages(): void {
		$rows = Hermes::getDB()->newSelectQueryBuilder()
			->select( [ 'hl_language', 'hl_wiki', 'hl_base', 'hl_active', 'hl_url' ] )
			->from( self::TABLE_NAME )
			->caller( __METHOD__ )
			->fetchResultSet();

		self::$languages = [];
		foreach ( $rows as $row ) {
			$language = HermesLanguage::fromRow( $row );
			self::$languages[ $language->code ][ $language->wiki ] = $language;
		}
	}

	/** Makes $language active on $wiki only, or on none if null. */
	private static function setActive( string $language, ?string $wiki ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( self::TABLE_NAME )
			->set( [ 'hl_active' => 0 ] )
			->where( [ 'hl_language' => $language ] )
			->caller( __METHOD__ )
			->execute();
		if ( $wiki !== null ) {
			$dbw->newUpdateQueryBuilder()
				->update( self::TABLE_NAME )
				->set( [ 'hl_active' => 1 ] )
				->where( [ 'hl_language' => $language, 'hl_wiki' => $wiki ] )
				->caller( __METHOD__ )
				->execute();
		}

		foreach ( self::$languages[ $language ] as $otherWiki => $registration ) {
			self::$languages[ $language ][ $otherWiki ] = $registration->withActive( $otherWiki === $wiki );
		}
	}

	private static function insert( HermesLanguage $language ): void {
		// Ignored if a concurrent request registered it first.
		Hermes::getDB( DB_PRIMARY )->newInsertQueryBuilder()
			->insertInto( self::TABLE_NAME )
			->ignore()
			->row( $language->toRow() )
			->caller( __METHOD__ )
			->execute();

		self::$languages[ $language->code ][ $language->wiki ] = $language;
	}
}
