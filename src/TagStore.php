<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class TagStore {

	private const VIRTUAL_DOMAIN = 'virtual-hermes';
	private const TABLE_NAME = 'hermes_tags';

	private function getDB( int $mode = DB_REPLICA ): IDatabase {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $mode === DB_PRIMARY
			? $lbFactory->getPrimaryDatabase( self::VIRTUAL_DOMAIN )
			: $lbFactory->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}

	/**
	 * Remove all tags for a page (e.g. when the page is deleted).
	 */
	public function deleteTagsForPage( PageInfo $page ): void {
		$dbw = $this->getDB( DB_PRIMARY );
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
	public function setTagsForPage( PageInfo $page, array $tags ): void {
		$dbw = $this->getDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Clear old tags for this page first - order/tag set may have changed.
		$dbw->delete(
			self::TABLE_NAME,
			[ 'ht_wiki' => $page->wiki, 'ht_page_id' => $page->id ],
			__METHOD__
		);

		$rows = [];
		foreach ( array_values( $tags ) as $order => $tag ) {
			if ( $tag === '' ) {
				continue;
			}
			$rows[] = [
				'ht_wiki' => $page->wiki,
				'ht_language' => $page->language,
				'ht_page_id' => $page->id,
				'ht_page_title' => $page->title,
				'ht_tag' => $tag,
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
	private function getTagsForPage( PageInfo $page ): array {
		$dbr = $this->getDB();
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
	private function getLinksForTags( array $tags ): array {
		$dbr = $this->getDB();

		$pages = [];
		foreach ( $tags as $tag ) {
			$rows = $dbr->select(
				self::TABLE_NAME,
				// [ 'ht_wiki', 'ht_language', 'ht_page_id', 'ht_page_title', 'ht_section', 'ht_tag', 'ht_order' ],
				'*',
				[ 'ht_tag' => $tag ],
				__METHOD__
			);
			foreach ( $rows as $row ) {
				$tagLink = TagLink::fromRow( $row );
				$lang = $tagLink->page->language;

				if ( !isset( $pages[ $lang ] ) ) {
					$pages[ $lang ] = $tagLink;
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
	public function getLinksForPage( PageInfo $page ): array {
		$tags = $this->getTagsForPage( $page );
		$links = $this->getLinksForTags( $tags );

		unset( $links[ $page->language ] );

		return $links;
	}
}
