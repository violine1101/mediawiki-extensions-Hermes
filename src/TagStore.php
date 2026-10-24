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
	 * @return string[] Names of the tags associated with the page, by priority.
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
	 * Links resolve to the pages setting a tag in a language with the lowest priority, so only a tie there
	 * counts. In $page's own language, that's a conflict for each of its tags it's part of. In other
	 * languages, its links are ambiguous if the first of its tags set there has such a tie.
	 *
	 * @param PageInfo $page The page $tags is being set on.
	 * @param Tag[] $tags The tags to check, each with its assigned priority, in fallback order.
	 * @return array<string, array<string, PageInfo[]>> language => tag name => conflicted pages
	 */
	public static function findConflicts( PageInfo $page, array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$rows = Hermes::getDB()->select(
			self::TABLE_NAME,
			'*',
			[
				'ht_tag' => array_map( static fn ( Tag $tag ) => $tag->name, $tags ),
				'NOT (' . Hermes::getDB()->makeList(
					[ 'ht_wiki' => $page->title->wiki, 'ht_page_id' => $page->getId() ],
					LIST_AND
				) . ')',
			],
			__METHOD__,
			[ 'ORDER BY' => 'ht_namespace_id ASC, ht_base_title ASC' ]
		);

		$ownLanguage = $page->title->getLanguageCode();
		// language => tag => priority => pages
		$pages = [];
		foreach ( $tags as $tag ) {
			$pages[ $ownLanguage ][ $tag->name ][ $tag->priority ][] = $page;
		}
		foreach ( $rows as $row ) {
			$rowPage = PageInfo::fromRow( $row );
			if ( $rowPage->title->isActive() ) {
				$pages[ $rowPage->title->getLanguageCode() ][ $row->ht_tag ][ (int)$row->ht_priority ][] = $rowPage;
			}
		}
		// Nothing links to an inactive page, so it can't conflict in its own language.
		if ( !$page->title->isActive() ) {
			unset( $pages[ $ownLanguage ] );
		}

		$conflicts = [];
		foreach ( $pages as $language => $byTag ) {
			foreach ( $tags as $tag ) {
				if ( !isset( $byTag[ $tag->name ] ) ) {
					continue;
				}
				$lowest = min( array_keys( $byTag[ $tag->name ] ) );
				$tied = $byTag[ $tag->name ][ $lowest ];
				if ( $language === $ownLanguage ) {
					if ( $lowest === $tag->priority && count( $tied ) > 1 ) {
						$conflicts[ $language ][ $tag->name ] = $tied;
					}
				} else {
					// The first tag set in this language is the one links resolve by.
					if ( count( $tied ) > 1 ) {
						$conflicts[ $language ][ $tag->name ] = $tied;
					}
					break;
				}
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
			if ( $link->page->title->isActive() ) {
				$links[ $link->tag->name ][] = $link;
			}
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

	/**
	 * The translations of $page: other-language pages sharing its priority-0 tag at priority 0, rather
	 * than pages it only falls back to.
	 *
	 * @return TagLink[] Keyed by language code
	 */
	public static function getTranslationsOfPage( PageInfo $page ): array {
		$tags = self::getTagsForPage( $page );
		if ( !$tags ) {
			return [];
		}
		// getLinksForTags() prefers the first tag, at the lowest priority.
		$links = self::getLinksForTags( $tags );
		unset( $links[ $page->title->getLanguageCode() ] );
		return array_filter(
			$links, static fn ( TagLink $link ) => $link->tag->name === $tags[0] && $link->tag->priority === 0
		);
	}
}
