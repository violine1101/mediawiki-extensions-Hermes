<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\TagLink;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * SQL for the Hermes special pages, on the shared Hermes DB. Projects are identified as in Project::toId().
 */
final class TagQuery {

	/** A hermes_tags row's language: its translation project's, else its wiki's base language. */
	public static function language( string $tags = 't', string $projects = 'p' ): string {
		return "COALESCE($tags.ht_translation_project, $projects.hp_language)";
	}

	/**
	 * Joins the project of each hermes_tags row (its translation project, else its wiki's base project) as
	 * $projects, for language(). Rows without one are left out, and without $withInactive, so are rows of
	 * inactive projects.
	 */
	public static function joinProjects(
		SelectQueryBuilder $query, string $tags = 't', string $projects = 'p', bool $withInactive = false
	): SelectQueryBuilder {
		$conds = [
			"$projects.hp_wiki = $tags.ht_wiki",
			"($projects.hp_language = $tags.ht_translation_project"
				. " OR ($tags.ht_translation_project IS NULL AND $projects.hp_base = 1))",
		];
		return $query->join( 'hermes_projects', $projects, $withInactive ? $conds : [
			...$conds, "$projects.hp_active" => 1,
		] );
	}

	/** Rows whose language is one of $languages. */
	public static function inLanguages( IReadableDatabase $dbr, array $languages ): string {
		return self::language() . ' IN (' . $dbr->makeList( $languages ) . ')';
	}

	/** Rows of the project with this ID. */
	public static function inProject( IReadableDatabase $dbr, string $id ): string {
		$project = Project::parseId( $id );
		if ( $project === null ) {
			return '0 = 1';
		}
		return self::language() . ' = ' . $dbr->addQuotes( $project->code )
			. ' AND t.ht_wiki = ' . $dbr->addQuotes( $project->wiki );
	}

	/** Rows whose $field contains $text. */
	public static function contains( IReadableDatabase $dbr, string $field, string $text ): IExpression {
		return $dbr->expr( $field, IExpression::LIKE, new LikeValue( $dbr->anyString(), $text, $dbr->anyString() ) );
	}

	/**
	 * Rows in a conflict: active, and another active page sets their tag with the same priority in the same
	 * language, and no active page sets it there with a lower priority (which links would resolve to instead).
	 */
	public static function inConflict( IReadableDatabase $dbr ): string {
		$sameTag = static fn ( string $tags, string $projects ) => [
			"$tags.ht_tag = t.ht_tag",
			self::language( $tags, $projects ) . ' = ' . self::language(),
		];
		$tie = self::joinProjects( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 'o' ), 'o', 'op' )
			->select( '1' )
			->where( [
				...$sameTag( 'o', 'op' ),
				'o.ht_priority = t.ht_priority',
				'NOT (o.ht_wiki = t.ht_wiki AND o.ht_page_id = t.ht_page_id)',
			] )
			->caller( __METHOD__ );
		$lower = self::joinProjects( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 'b' ), 'b', 'bp' )
			->select( '1' )
			->where( [ ...$sameTag( 'b', 'bp' ), 'b.ht_priority < t.ht_priority' ] )
			->caller( __METHOD__ );
		return '(p.hp_active = 1 AND EXISTS(' . $tie->getSQL() . ') AND NOT EXISTS(' . $lower->getSQL() . '))';
	}

	/**
	 * A sort key for a nullable string expression that sorts its values (in their own order) before nulls,
	 * since IndexPager can't page on nulls.
	 */
	public static function sortKey( IReadableDatabase $dbr, string $expression ): string {
		$value = $dbr->buildConcat( [ $dbr->addQuotes( '0' ), $expression ] );
		return "COALESCE($value, " . $dbr->addQuotes( '1' ) . ')';
	}

	/** @return array<string, array<string, int>> Number of pages with tags, active or not, by wiki and language */
	public static function countPages( IReadableDatabase $dbr ): array {
		$pages = self::joinProjects( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 't' ), 't', 'p', true )
			->select( [ 't.ht_wiki', 't.ht_page_id', 'language' => self::language() ] )
			->distinct();
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'ht_wiki', 'language', 'pages' => 'COUNT(*)' ] )
			->from( $pages, 'pages' )
			->groupBy( [ 'ht_wiki', 'language' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ $row->ht_wiki ][ $row->language ] = (int)$row->pages;
		}
		return $counts;
	}

	/**
	 * @param IReadableDatabase $dbr
	 * @param string[] $tags
	 * @param bool $withInactive Whether to include pages of inactive projects too
	 * @param string[] $wikis Only pages on these wikis, or all if empty
	 * @return array<string, array<string, array<int, TagLink[]>>> tag => project ID => priority => links,
	 *   priorities ascending
	 */
	public static function loadLinks(
		IReadableDatabase $dbr, array $tags, bool $withInactive = false, array $wikis = []
	): array {
		if ( !$tags ) {
			return [];
		}

		$rows = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'hermes_tags' )
			->where( [ 'ht_tag' => $tags ] )
			->andWhere( $wikis ? [ 'ht_wiki' => $wikis ] : [] )
			->orderBy( [ 'ht_priority', 'ht_namespace_id', 'ht_base_title' ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$links = [];
		foreach ( $rows as $row ) {
			$link = TagLink::fromRow( $row );
			$project = $link->page->title->getProject();
			if ( $project !== null && ( $project->isActive || $withInactive ) ) {
				$links[ $link->tag->name ][ $project->toId() ][ $link->tag->priority ][] = $link;
			}
		}
		return $links;
	}

	/**
	 * The tags of a page, given as in TagFormatter::detailsTitle().
	 *
	 * @param string $id The page's project ID, which determines its wiki and translation project
	 * @param string $title Namespace name as stored, and base title
	 * @return TagLink[] In fallback order; none if there's no such page with tags
	 */
	public static function findPage( string $id, string $title ): array {
		$project = Project::parseId( $id );
		$title = strtr( trim( $title ), ' ', '_' );
		if ( $project === null || $title === '' ) {
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
				'ht_wiki' => $project->wiki,
				'ht_translation_project' => $project->isBase ? null : $project->code,
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
