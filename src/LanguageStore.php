<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

/**
 * Tracks which languages are available on which wiki.
 * Each wiki registers its own base language (from $wgLanguageCode) into the shared
 * hermes_languages table on first use.
 * Translation project languages are registered on top of that via addProjectLanguage().
 */
class LanguageStore {

	private const TABLE_NAME = 'hermes_languages';

	/**
	 * Cached hermes_languages table, keyed by language code.
	 *
	 * @var array<string, array{wiki: string, isBase: bool}>|null
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
	 * Look up the wiki registered for a given language (base or project).
	 *
	 * @param string $language
	 * @return string|null The wiki ID, or null if no wiki is registered for it.
	 */
	public static function getWikiForLanguage( string $language ): ?string {
		return self::getLanguages()[ $language ]['wiki'] ?? null;
	}

	/**
	 * Whether the given language is registered as a translation project language for the
	 * current wiki.
	 *
	 * @param string $language
	 * @return bool
	 */
	public static function isProjectLanguage( string $language ): bool {
		$row = self::getLanguages()[ $language ] ?? null;

		return $row !== null && !$row[ 'isBase' ] && $row[ 'wiki' ] === WikiMap::getCurrentWikiId();
	}

	/**
	 * Ensures this wiki's base language is registered, populating the cache in the process.
	 *
	 * @throws LanguageConflictException If this language is already registered on a different
	 *   wiki, or as a project language.
	 * @throws \RuntimeException If this wiki is already registered for a different base language.
	 */
	public static function init(): void {
		self::addLanguage( self::getLocalBaseLanguage(), WikiMap::getCurrentWikiId(), true );
	}

	/**
	 * Register a new translation project language for a given wiki.
	 *
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @param string $language Language code of the project language.
	 * @return bool True if newly registered, false if it was already registered on $wiki.
	 * @throws LanguageConflictException If the language is already registered on a different
	 *   wiki, or registered as a base language.
	 */
	public static function addProjectLanguage( string $wiki, string $language ): bool {
		return self::addLanguage( $language, $wiki, false );
	}

	/**
	 * @return array<string, array{wiki: string, isBase: bool}>
	 */
	private static function getLanguages(): array {
		if ( self::$languages === null ) {
			self::init();
		}

		return self::$languages;
	}

	/**
	 * Loads the language definitions from hermes_languages into cache,
	 * discarding whatever was cached before.
	 */
	private static function loadLanguages(): void {
		$dbr = Hermes::getDB();
		$rows = $dbr->select(
			self::TABLE_NAME,
			[ 'hl_language', 'hl_wiki', 'hl_base' ],
			[],
			__METHOD__
		);

		self::$languages = [];
		foreach ( $rows as $row ) {
			self::$languages[ $row->hl_language ] = [ 'wiki' => $row->hl_wiki, 'isBase' => (bool)$row->hl_base ];
		}
	}

	/**
	 * Registers $language to $wiki as the given kind of language (base vs. project),
	 * inserting it into the languages table (and the cache) if it isn't registered yet.
	 * A matching existing registration is a no-op.
	 *
	 * @param string $language
	 * @param string $wiki
	 * @param bool $isBase
	 * @return bool True if newly registered, false if it was already registered exactly as requested.
	 * @throws LanguageConflictException If $language is already registered to a different wiki,
	 *   or as a different kind of language (base vs. project).
	 * @throws \RuntimeException If $wiki is already registered for a different base language.
	 */
	private static function addLanguage( string $language, string $wiki, bool $isBase ): bool {
		$dbw = Hermes::getDB( DB_PRIMARY );

		$dbw->lock( self::TABLE_NAME, __METHOD__ );
		try {
			self::loadLanguages();

			$entry = self::$languages[ $language ] ?? null;
			if ( $entry !== null ) {
				if ( $entry[ 'wiki' ] !== $wiki || $entry[ 'isBase' ] !== $isBase ) {
					throw new LanguageConflictException( $language, $entry[ 'wiki' ], $wiki );
				}
				return false;
			}

			if ( $isBase ) {
				// This wiki doesn't have a base language entry for $language yet - check that it
				// doesn't already have one under a different language code.
				foreach ( self::$languages as $otherLanguage => $row ) {
					if ( $row[ 'wiki' ] === $wiki && $row[ 'isBase' ] ) {
						throw new \RuntimeException(
							"Wiki \"{$wiki}\" is already registered for base language \"{$otherLanguage}\", " .
								"not \"{$language}\"; fix hermes_languages by hand to resolve this."
						);
					}
				}
			}

			$dbw->upsert(
				self::TABLE_NAME,
				[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => (int)$isBase ],
				[ 'hl_language' ],
				[ 'hl_wiki' => $wiki, 'hl_base' => (int)$isBase ],
				__METHOD__
			);

			self::$languages[ $language ] = [ 'wiki' => $wiki, 'isBase' => $isBase ];

			return true;
		} finally {
			$dbw->unlock( self::TABLE_NAME, __METHOD__ );
		}
	}
}
