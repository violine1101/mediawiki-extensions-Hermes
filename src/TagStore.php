<?php

namespace MediaWiki\Extension\Hermes;

class TagStore {

	private const TABLE_NAME = 'hermes_tags';

	/**
	 * Remove all tags for a page (e.g. when the page is deleted).
	 */
	public static function deleteTagsForPage( ProjectTitle $page ): void {
		Hermes::getDB( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE_NAME )
			->where( self::pageConds( $page ) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Replace the tags for a given page.
	 *
	 * @param ProjectTitle $page
	 * @param Tag[] $tags Tags, each carrying its own priority.
	 */
	public static function setTagsForPage( ProjectTitle $page, array $tags ): void {
		$project = $page->project;
		$dbw = Hermes::getDB( DB_PRIMARY );
		$dbw->startAtomic( __METHOD__ );

		// Clear old tags for this page first - priorities/tag set may have changed.
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE_NAME )
			->where( self::pageConds( $page ) )
			->caller( __METHOD__ )
			->execute();

		$rows = [];
		foreach ( $tags as $tag ) {
			$rows[] = self::pageConds( $page ) + [
				'ht_namespace_id' => $page->namespace,
				'ht_namespace_text' => $page->getNamespaceText(),
				'ht_language' => $project->isBase ? null : $project->code,
				'ht_base_title' => $page->baseTitle,
				'ht_section' => $tag->section,
				'ht_tag' => $tag->name,
				'ht_priority' => $tag->priority,
			];
		}

		if ( $rows ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( self::TABLE_NAME )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get all tags for a given page.
	 *
	 * @return string[] Names of the tags associated with the page, by priority.
	 */
	private static function getTagsForPage( ProjectTitle $page ): array {
		return Hermes::getDB()->newSelectQueryBuilder()
			->select( 'ht_tag' )
			->from( self::TABLE_NAME )
			->where( self::pageConds( $page ) )
			->orderBy( [ 'ht_priority', 'ht_tag' ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * Finds all conflicts and ambiguities caused by $page's own tag set.
	 *
	 * Links resolve to the pages setting a tag in a language with the lowest priority, so only a tie there
	 * counts. In $page's own language, that's a conflict for each of its tags it's part of. In other
	 * languages, its links are ambiguous if the first of its tags set there has such a tie.
	 *
	 * @param ProjectTitle $page The page $tags is being set on.
	 * @param Tag[] $tags The tags to check, each with its assigned priority, in fallback order.
	 * @return array<string, array<string, TagLink[]>> language => tag name => conflicted pages
	 */
	public static function findConflicts( ProjectTitle $page, array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$dbr = Hermes::getDB();
		$rows = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( self::TABLE_NAME )
			->where( [
				'ht_tag' => array_map( static fn ( Tag $tag ) => $tag->name, $tags ),
				$dbr->expr( 'ht_wiki', '!=', $page->project->wiki )->or( 'ht_page_id', '!=', $page->getId() ),
			] )
			->orderBy( [ 'ht_namespace_id', 'ht_base_title' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ownLanguage = $page->project->code;
		// language => tag => priority => pages
		$pages = [];
		foreach ( $tags as $tag ) {
			$pages[ $ownLanguage ][ $tag->name ][ $tag->priority ][] = new TagLink( $page, $tag );
		}
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			$project = $link->title->project;
			if ( $project->isActive ) {
				$pages[ $project->code ][ $link->tag->name ][ $link->tag->priority ][] = $link;
			}
		}
		// Nothing links to an inactive page, so it can't conflict in its own language.
		if ( !$page->project->isActive ) {
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

		// Get all matching pages, sorted by priority.
		// In case of a conflict, use the page title as a tiebreak,
		// so that it's deterministic which link gets displayed.
		$rows = Hermes::getDB()->newSelectQueryBuilder()
			->select( '*' )
			->from( self::TABLE_NAME )
			->where( [ 'ht_tag' => $tags ] )
			->orderBy( [ 'ht_priority', 'ht_namespace_id', 'ht_base_title' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Convert rows, and sort them by tag
		$links = [];
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			if ( $link->title->project->isActive ) {
				$links[ $link->tag->name ][] = $link;
			}
		}

		// Walk tags in fallback order so earlier tags take priority; within a
		// tag, rows are already priority-ascending, so the first row seen for
		// a language is the one with the highest priority.
		$pages = [];
		foreach ( $tags as $tag ) {
			foreach ( $links[ $tag ] ?? [] as $link ) {
				$lang = $link->title->project->code;

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
	 * @param ProjectTitle $page The page to get the links for
	 * @return TagLink[] Links to pages with the given tags, excluding any self-link.
	 */
	public static function getLinksForPage( ProjectTitle $page ): array {
		$tags = self::getTagsForPage( $page );
		$links = self::getLinksForTags( $tags );

		unset( $links[ $page->project->code ] );

		return $links;
	}

	/**
	 * The translations of $page: other-language pages sharing its priority-0 tag at priority 0, rather
	 * than pages it only falls back to.
	 *
	 * @return TagLink[] Keyed by language code
	 */
	public static function getTranslationsOfPage( ProjectTitle $page ): array {
		$tags = self::getTagsForPage( $page );
		if ( !$tags ) {
			return [];
		}
		// getLinksForTags() prefers the first tag, at the lowest priority.
		$links = self::getLinksForTags( $tags );
		unset( $links[ $page->project->code ] );
		return array_filter(
			$links, static fn ( TagLink $link ) => $link->tag->name === $tags[0] && $link->tag->priority === 0
		);
	}

	/** Conditions selecting $page's rows. */
	private static function pageConds( ProjectTitle $page ): array {
		return [ 'ht_wiki' => $page->project->wiki, 'ht_page_id' => $page->getId() ];
	}
}
