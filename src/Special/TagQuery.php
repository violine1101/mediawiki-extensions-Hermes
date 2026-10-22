<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\TagLink;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * SQL for the Hermes special pages, on the shared Hermes DB.
 */
final class TagQuery {

	/** A hermes_tags row's language: its translation project, else its wiki's base language. */
	public static function language( string $tags = 't', string $languages = 'l' ): string {
		return "COALESCE($tags.ht_translation_project, $languages.hl_language)";
	}

	/** Joins hermes_languages as $languages to hermes_tags as $tags, for language(). */
	public static function joinLanguages(
		SelectQueryBuilder $query, string $tags = 't', string $languages = 'l'
	): SelectQueryBuilder {
		return $query->leftJoin( 'hermes_languages', $languages, [
			"$languages.hl_wiki = $tags.ht_wiki",
			"$languages.hl_base" => 1,
		] );
	}

	/** Rows whose language is one of $languages. */
	public static function inLanguages( IReadableDatabase $dbr, array $languages ): string {
		return self::language() . ' IN (' . $dbr->makeList( $languages ) . ')';
	}

	/** Rows whose $field contains $text. */
	public static function contains( IReadableDatabase $dbr, string $field, string $text ): IExpression {
		return $dbr->expr( $field, IExpression::LIKE, new LikeValue( $dbr->anyString(), $text, $dbr->anyString() ) );
	}

	/** Rows whose tag another page sets with the same priority in the same language. */
	public static function inConflict( IReadableDatabase $dbr ): string {
		$other = self::joinLanguages( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 'o' ), 'o', 'ol' )
			->select( '1' )
			->where( [
				'o.ht_tag = t.ht_tag',
				'o.ht_priority = t.ht_priority',
				self::language( 'o', 'ol' ) . ' = ' . self::language(),
				'NOT (o.ht_wiki = t.ht_wiki AND o.ht_page_id = t.ht_page_id)',
			] )
			->caller( __METHOD__ );
		return 'EXISTS(' . $other->getSQL() . ')';
	}

	/**
	 * A sort key for a nullable string expression that sorts its values (in their own order) before nulls,
	 * since IndexPager can't page on nulls.
	 */
	public static function sortKey( IReadableDatabase $dbr, string $expression ): string {
		$value = $dbr->buildConcat( [ $dbr->addQuotes( '0' ), $expression ] );
		return "COALESCE($value, " . $dbr->addQuotes( '1' ) . ')';
	}

	/** @return array<string, int> Number of pages with tags, by language */
	public static function countPages( IReadableDatabase $dbr ): array {
		$pages = self::joinLanguages( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 't' ) )
			->select( [ 't.ht_wiki', 't.ht_page_id', 'language' => self::language() ] )
			->distinct();
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'language', 'pages' => 'COUNT(*)' ] )
			->from( $pages, 'p' )
			->groupBy( 'language' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ $row->language ?? '' ] = (int)$row->pages;
		}
		return $counts;
	}

	/**
	 * @param IReadableDatabase $dbr
	 * @param string[] $tags
	 * @return array<string, array<string, array<int, TagLink[]>>> tag => language => priority => links,
	 *   priorities ascending
	 */
	public static function loadLinks( IReadableDatabase $dbr, array $tags ): array {
		if ( !$tags ) {
			return [];
		}

		$rows = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'hermes_tags' )
			->where( [ 'ht_tag' => $tags ] )
			->orderBy( [ 'ht_priority', 'ht_namespace_id', 'ht_base_title' ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$links = [];
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			$links[ $link->tag->name ][ $link->page->title->getLanguageCode() ][ $link->tag->priority ][] = $link;
		}
		return $links;
	}

	/**
	 * The tags of a page, given as in TagFormatter::detailsTitle().
	 *
	 * @param string $language The page's language, which determines its wiki and translation project
	 * @param string $title Namespace name as stored, and base title
	 * @return TagLink[] In fallback order; none if there's no such page with tags
	 */
	public static function findPage( string $language, string $title ): array {
		$wiki = LanguageStore::getWikiForLanguage( $language );
		$title = strtr( trim( $title ), ' ', '_' );
		if ( $wiki === null || $title === '' ) {
			return [];
		}

		$dbr = Hermes::getDB();
		// Main namespace titles may contain ":" too.
		$titleConds = [ $dbr->makeList( [ 'ht_namespace_text' => '', 'ht_base_title' => $title ], LIST_AND ) ];
		if ( str_contains( $title, ':' ) ) {
			[ $namespace, $baseTitle ] = explode( ':', $title, 2 );
			$titleConds[] = $dbr->makeList(
				[ 'ht_namespace_text' => $namespace, 'ht_base_title' => $baseTitle ], LIST_AND
			);
		}
		$rows = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'hermes_tags' )
			->where( [
				'ht_wiki' => $wiki,
				'ht_translation_project' => LanguageStore::getLanguages()[ $language ][ 'isBase' ] ? null : $language,
				$dbr->makeList( $titleConds, LIST_OR ),
			] )
			->orderBy( [ 'ht_priority', 'ht_tag' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		/** @var TagLink[] $links */
		$links = [];
		foreach ( $rows as $row ) {
			$links[] = TagLink::fromRow( $row );
		}
		if ( !$links ) {
			return [];
		}

		// A main namespace title can't start with a namespace name, so prefer the namespaced match.
		$page = $links[ 0 ]->page;
		foreach ( $links as $link ) {
			if ( $link->page->title->getNamespaceText() !== '' ) {
				$page = $link->page;
				break;
			}
		}
		return array_values( array_filter(
			$links,
			static fn ( TagLink $link ) => $link->page->getId() === $page->getId()
		) );
	}
}
