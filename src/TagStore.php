<?php

namespace MediaWiki\Extension\Hermes;

class TagStore {

	private const TABLE_NAME = 'hermes_tags';

	/**
	 * Remove all tags for a page (e.g. when the page is deleted).
	 */
	public static function deleteTagsForPage( PageInfo $page ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->delete(
			self::TABLE_NAME,
			[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__
		);
	}

	/**
	 * Replace the tags for a given page.
	 *
	 * @param PageInfo $page
	 * @param Tag[] $tags Tags, each carrying its own priority.
	 */
	public static function setTagsForPage( PageInfo $page, array $tags ): void {
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Clear old tags for this page first - priorities/tag set may have changed.
		$dbw->delete(
			self::TABLE_NAME,
			[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__
		);

		$rows = [];
		foreach ( $tags as $tag ) {
			$rows[] = [
				'ht_wiki' => $page->title->wiki,
				'ht_page_id' => $page->getId(),
				'ht_namespace_id' => $page->title->namespace,
				'ht_namespace_text' => $page->title->getNamespaceText(),
				'ht_translation_project' => $page->title->project,
				'ht_base_title' => $page->title->baseTitle,
				'ht_section' => $tag->section,
				'ht_tag' => $tag->name,
				'ht_priority' => $tag->priority,
			];
		}

		if ( $rows ) {
			$dbw->insert( self::TABLE_NAME, $rows, __METHOD__ );
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get all tags for a given page.
	 *
	 * @return Tag[] Array of tags associated with the page, in order.
	 */
	private static function getTagsForPage( PageInfo $page ): array {
		$dbr = Hermes::getDB();
		$res = $dbr->select(
			self::TABLE_NAME,
			[ 'ht_tag', 'ht_priority' ],
			[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
			__METHOD__,
			[ 'ORDER BY' => 'ht_priority ASC, ht_tag ASC' ]
		);

		$tags = [];
		foreach ( $res as $row ) {
			$tags[] = $row->ht_tag;
		}
		return $tags;
	}

	/**
	 * Finds all conflicts and ambiguities caused by $page's own tag set.
	 *
	 * Two or more pages are in conflict if they have set the same tag with the same priority
	 * and are in the same language.
	 *
	 * @param PageInfo $page The page $tags is being set on.
	 * @param Tag[] $tags The tags to check, each with its assigned priority.
	 * @return array<string, array<string, PageInfo[]>> language => tag name => conflicted pages
	 */
	public static function findConflicts( PageInfo $page, array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$dbr = Hermes::getDB();

		$queryConditions = [];
		foreach ( $tags as $tag ) {
			$queryConditions[] = $dbr->makeList(
				[ 'ht_tag' => $tag->name, 'ht_priority' => $tag->priority ],
				LIST_AND
			);
		}

		$rows = $dbr->select(
			self::TABLE_NAME,
			'*',
			[
				$dbr->makeList( $queryConditions, LIST_OR ),
				'NOT (' . $dbr->makeList(
					[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
					LIST_AND
				) . ')',
			],
			__METHOD__,
			[ 'ORDER BY' => 'ht_namespace_id ASC, ht_base_title ASC' ]
		);

		$conflicts = [];
		foreach ( $tags as $tag ) {
			$conflicts[ $page->title->getLanguageCode() ][ $tag->name ][] = $page;
		}
		foreach ( $rows as $row ) {
			$rowPage = PageInfo::fromRow( $row );
			$conflicts[ $rowPage->title->getLanguageCode() ][ $row->ht_tag ][] = $rowPage;
		}

		foreach ( $conflicts as $lang => $langConflicts ) {
			foreach ( $langConflicts as $tag => $pages ) {
				if ( count( $pages ) <= 1 ) {
					unset( $conflicts[ $lang ][ $tag ] );
				}
			}
			if ( !count( $conflicts[ $lang ] ) ) {
				unset( $conflicts[ $lang ] );
			}
		}
		return $conflicts;
	}

	/**
	 * Find the pages linked to by the given tag fallback chain, one per language.
	 *
	 * @param Tag[] $tags Ordered tag fallback chain
	 * @return TagLink[] Links to pages with the given tags, keyed by language.
	 */
	private static function getLinksForTags( array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$dbr = Hermes::getDB();

		// Get all matching pages, sorted by priority.
		// In case of a conflict, use the page title as a tiebreak,
		// so that it's deterministic which link gets displayed.
		$rows = $dbr->select(
			self::TABLE_NAME,
			'*',
			[ 'ht_tag' => $tags ],
			__METHOD__,
			[ 'ORDER BY' => 'ht_priority ASC, ht_namespace_id ASC, ht_base_title ASC' ]
		);

		// Convert rows, and sort them by tag
		$links = [];
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			$links[ $link->tag->name ][] = $link;
		}

		// Walk tags in fallback order so earlier tags take priority; within a
		// tag, rows are already priority-ascending, so the first row seen for
		// a language is the one with the highest priority.
		$pages = [];
		foreach ( $tags as $tag ) {
			foreach ( $links[ $tag ] ?? [] as $link ) {
				$lang = $link->page->title->getLanguageCode();

				if ( !isset( $pages[ $lang ] ) ) {
					$pages[ $lang ] = $link;
				}
			}
		}

		return $pages;
	}

	/**
	 * Find the other-language versions of the given page on the current wiki.
	 *
	 * @param PageInfo $page The page to get the links for
	 * @return TagLink[] Links to pages with the given tags, excluding any self-link.
	 */
	public static function getLinksForPage( PageInfo $page ): array {
		$tags = self::getTagsForPage( $page );
		$links = self::getLinksForTags( $tags );

		unset( $links[ $page->title->getLanguageCode() ] );

		return $links;
	}
}
