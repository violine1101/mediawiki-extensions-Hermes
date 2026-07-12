<?php

namespace MediaWiki\Extension\Hermes;

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
	 * Register a new translation project language for a given wiki.
	 *
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @param string $language Language code of the project language.
	 */
	public static function addProjectLanguage( string $wiki, string $language ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->upsert(
			self::TABLE_NAME,
			[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => 0 ],
			[ 'hl_language' ],
			[ 'hl_wiki' => $wiki, 'hl_base' => 0 ],
			__METHOD__
		);

		self::$languages = null;
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
	 * Look up the base language for the current wiki.
	 *
	 * @return string The wiki's base language code.
	 */
	public static function getLocalBaseLanguage(): string {
		return MediaWikiServices::getInstance()->getContentLanguage()->getCode();
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
	 * @return array<string, array{wiki: string, isBase: bool}>
	 */
	private static function getLanguages(): array {
		if ( self::$languages === null ) {
			self::loadLanguages();
		}

		return self::$languages;
	}

	/**
	 * Loads the language definitions from hermes_languages into cache.
	 * Then, validates that the languages table matches the wiki's configuration.
	 */
	public static function loadLanguages() {
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

		self::validateLanguages();
	}

	/**
	 * Ensures that this wiki's base language is in the languages table, inserting it if missing,
	 * or throwing an error if the table is conflicting with the wiki configuration.
	 *
	 * @throws \RuntimeException If there is a configuration error between wiki config and languages table.
	 */
	private static function validateLanguages() {
		$wiki = WikiMap::getCurrentWikiId();
		$language = self::getLocalBaseLanguage();
		$entry = self::$languages[ $language ] ?? null;

		if ( isset( $entry ) ) {
			// This language already has an associated wiki.
			// If it doesn't match the current wiki's configuration, throw an error.
			if ( $entry[ 'wiki' ] !== $wiki || !$entry[ 'isBase' ] ) {
				throw new \RuntimeException(
					"Language \"{$language}\" is already registered to a different wiki, or as a " .
						"project language; fix hermes_languages by hand to resolve this."
				);
			}
			return;
		}

		// This language does not have an associated wiki yet.
		// Check if this wiki already has a base language entry, and if so, throw an error.
		foreach ( self::$languages as $otherLanguage => $row ) {
			if ( $row[ 'wiki' ] === $wiki && $row[ 'isBase' ] ) {
				throw new \RuntimeException(
					"Wiki \"{$wiki}\" is already registered for base language \"{$otherLanguage}\", " .
						"not \"{$language}\"; fix hermes_languages by hand to resolve this."
				);
			}
		}

		// If not, we associate this wiki with the language in the database and update the cache.
		Hermes::getDB( DB_PRIMARY )->insert(
			self::TABLE_NAME,
			[ 'hl_language' => $language, 'hl_wiki' => $wiki, 'hl_base' => 1 ],
			__METHOD__
		);

		self::$languages[ $language ] = [ 'wiki' => $wiki, 'isBase' => true ];
	}
}
