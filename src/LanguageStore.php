<?php

namespace MediaWiki\Extension\Hermes;

/**
 * Tracks which languages are available on which wiki.
 * The wikis themselves (their IDs and URL templates) are configured globally via $wgHermesWikis;
 * this store only maps languages to the wiki that serves them.
 */
class LanguageStore {

	private const TABLE_NAME = 'hermes_languages';

	/**
	 * Register (or update) the language served by a given wiki.
	 *
	 * @param string $wiki Wiki ID, as configured in $wgHermesWikis.
	 * @param string $language Language code other wikis should match against.
	 */
	public static function setLanguage( string $wiki, string $language ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->upsert(
			self::TABLE_NAME,
			[ 'hl_language' => $language, 'hl_wiki' => $wiki ],
			[ 'hl_language' ],
			[ 'hl_wiki' => $wiki ],
			__METHOD__
		);
	}

	/**
	 * Look up the wiki registered for a given language.
	 *
	 * @param string $language
	 * @return string|null The wiki ID, or null if no wiki is registered for it.
	 */
	public static function getWikiForLanguage( string $language ): ?string {
		$dbr = Hermes::getDB();
		$wiki = $dbr->selectField(
			self::TABLE_NAME,
			'hl_wiki',
			[ 'hl_language' => $language ],
			__METHOD__
		);

		return $wiki === false ? null : $wiki;
	}

	/**
	 * Look up the language registered for a given wiki.
	 *
	 * @param string $wiki
	 * @return string|null The registered language code, or null if this wiki isn't registered.
	 */
	public static function getLanguageForWiki( string $wiki ): ?string {
		$dbr = Hermes::getDB();
		$language = $dbr->selectField(
			self::TABLE_NAME,
			'hl_language',
			[ 'hl_wiki' => $wiki ],
			__METHOD__
		);

		return $language === false ? null : $language;
	}
}
