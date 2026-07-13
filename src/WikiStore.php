<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;

/**
 * Tracks the interwiki URL template each wiki in the family uses to link to the others.
 * Each wiki registers its own URL template, derived from its own $wgServer/$wgArticlePath
 * (no separate Hermes-specific config needed) into the shared hermes_wikis table on first use.
 * Unlike a language, a wiki's own ID is inherently unique to it - no cross-wiki conflict is possible.
 */
class WikiStore {

	private const TABLE_NAME = 'hermes_wikis';

	/**
	 * Cached hermes_wikis table, keyed by wiki ID.
	 *
	 * @var array<string, string>|null
	 */
	private static ?array $wikis = null;

	/**
	 * Clears the $wikis cache above, for use in testing.
	 * This makes sure that the tests don't pollute each other.
	 */
	public static function clearCacheForTesting(): void {
		self::$wikis = null;
	}

	/**
	 * Look up the URL template (with "$1" as the page title placeholder) for a given wiki.
	 *
	 * @param string $wiki
	 * @return string|null The URL template, or null if no wiki is registered for it.
	 */
	public static function getUrlTemplate( string $wiki ): ?string {
		return self::getWikis()[ $wiki ] ?? null;
	}

	/**
	 * Ensures this wiki's URL template is registered, populating the cache in the process.
	 */
	public static function init(): void {
		self::loadWikis();

		$wiki = WikiMap::getCurrentWikiId();
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$url = $config->get( MainConfigNames::Server ) . $config->get( MainConfigNames::ArticlePath );
		if ( ( self::$wikis[ $wiki ] ?? null ) === $url ) {
			return;
		}

		Hermes::getDB( DB_PRIMARY )->upsert(
			self::TABLE_NAME,
			[ 'hw_wiki' => $wiki, 'hw_url' => $url ],
			[ 'hw_wiki' ],
			[ 'hw_url' => $url ],
			__METHOD__
		);

		self::$wikis[ $wiki ] = $url;
	}

	/**
	 * @return array<string, string>
	 */
	private static function getWikis(): array {
		if ( self::$wikis === null ) {
			self::init();
		}

		return self::$wikis;
	}

	/**
	 * Loads the wiki definitions from hermes_wikis into cache, discarding whatever was cached
	 * before.
	 */
	private static function loadWikis(): void {
		$dbr = Hermes::getDB();
		$rows = $dbr->select(
			self::TABLE_NAME,
			[ 'hw_wiki', 'hw_url' ],
			[],
			__METHOD__
		);

		self::$wikis = [];
		foreach ( $rows as $row ) {
			self::$wikis[ $row->hw_wiki ] = $row->hw_url;
		}
	}
}
