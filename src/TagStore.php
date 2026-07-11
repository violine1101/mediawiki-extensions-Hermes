<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class TagStore {

	private const VIRTUAL_DOMAIN = 'virtual-hermes';
	private const TABLE_NAME = 'hermes_tags';

	private static function getDB( int $mode = DB_REPLICA ): IDatabase {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $mode === DB_PRIMARY
			? $lbFactory->getPrimaryDatabase( self::VIRTUAL_DOMAIN )
			: $lbFactory->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}

	/**
	 * Remove all tags for a page (e.g. when the page is deleted).
	 */
	public static function deleteTagsForPage( PageInfo $page ): void {
		$dbw = self::getDB( DB_PRIMARY );
		$dbw->delete(
			self::TABLE_NAME,
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__
		);
	}

	/**
	 * Replace the tags for a given page.
	 *
	 * @param PageInfo $page
	 * @param Tag[] $tags Ordered list of tags
	 */
	public static function setTagsForPage( PageInfo $page, array $tags ): void {
		$dbw = self::getDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Clear old tags for this page first - order/tag set may have changed.
		$dbw->delete(
			self::TABLE_NAME,
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__
		);

		$rows = [];
		foreach ( array_values( $tags ) as $order => $tag ) {
			$rows[] = [
				'ht_wiki' => $page->wiki,
				'ht_language' => $page->language,
				'ht_page_id' => $page->id,
				'ht_page_title' => $page->title,
				'ht_section' => $tag->section,
				'ht_tag' => $tag->name,
				'ht_order' => $order,
			];
		}

		if ( $rows ) {
			$dbw->insert( self::TABLE_NAME, $rows, __METHOD__ );
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get all tags (with order) for a given page.
	 *
	 * @return Tag[] Array of tags associated with the page, in order.
	 */
	private static function getTagsForPage( PageInfo $page ): array {
		$dbr = self::getDB();
		$res = $dbr->select(
			self::TABLE_NAME,
			[ 'ht_tag', 'ht_order' ],
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__,
			[ 'ORDER BY' => 'ht_order ASC' ]
		);

		$tags = [];
		foreach ( $res as $row ) {
			$tags[] = $row->ht_tag;
		}
		return $tags;
	}

	/**
	 * Find all relevant interwikis, following the given tag fallback chain.
	 *
	 * @param Tag[] $tags Ordered tag fallback chain
	 * @return TagLink[] An array of links to pages with the given tags. Key is the language.
	 */
	private static function getLinksForTags( array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$dbr = self::getDB();

		// Get all matching pages, sorted by where the tag is in the order
		$rows = $dbr->select(
			self::TABLE_NAME,
			'*',
			[ 'ht_tag' => $tags ],
			__METHOD__,
			[ 'ORDER BY' => 'ht_order ASC' ]
		);

		// Convert rows, and sort them by tag
		$links = [];
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			$links[ $link->tag->name ][] = $link;
		}

		// Walk tags in fallback order so earlier tags take priority; within a
		// tag, rows are already ht_order-ascending, so the first row seen for
		// a language is the one with the lowest ht_order.
		$pages = [];
		foreach ( $tags as $tag ) {
			foreach ( $links[ $tag ] ?? [] as $link ) {
				$lang = $link->page->language;

				if ( !isset( $pages[ $lang ] ) ) {
					$pages[ $lang ] = $link;
				}
			}
		}

		return $pages;
	}

	/**
	 * Find all relevant interwikis for the given page on the current wiki.
	 *
	 * @param PageInfo $page The page to get the links for
	 * @return TagLink[] An array of links to pages with the given tags, excluding any self-link.
	 */
	public static function getLinksForPage( PageInfo $page ): array {
		$tags = self::getTagsForPage( $page );
		$links = self::getLinksForTags( $tags );

		unset( $links[ $page->language ] );

		return $links;
	}
}
