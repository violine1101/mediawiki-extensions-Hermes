<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Html\Html;
use MediaWiki\Pager\TablePager;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\Subquery;

/**
 * Lists the Hermes tags of the whole wiki family, one row per tag and one column per active language (plus, if shown,
 * one per inactive project, dimmed), each cell listing the pages with that tag; conflicts are
 * highlighted. To make every column sortable, a subquery groups hermes_tags by tag, with a sort key per column:
 * its first title there.
 */
class HermesTagsPager extends TablePager {

	/** @var array<string, string> Column field => project ID */
	private array $languageFields = [];

	/** @var array<string, array<string, array<int, TagLink[]>>> Tag => project ID => priority => links */
	private array $links = [];

	/**
	 * @param IContextSource $context
	 * @param TagFormatter $formatter
	 * @param string $tagFilter Only tags containing this, if not empty
	 * @param string[] $languages Only these languages, or all if empty
	 * @param string[] $wikis Only pages on these wikis, or all if empty
	 * @param bool $withInactive Whether to include pages of inactive projects
	 * @param bool $conflictsOnly Only tags with a conflict
	 */
	public function __construct(
		IContextSource $context,
		private readonly TagFormatter $formatter,
		private readonly string $tagFilter,
		private readonly array $languages,
		private readonly array $wikis,
		private readonly bool $withInactive,
		private readonly bool $conflictsOnly,
	) {
		$columns = array_intersect( $languages ?: array_keys( ProjectStore::getProjects() ),
			array_keys( ProjectStore::getActiveProjects() ) );
		if ( $withInactive ) {
			foreach ( ProjectStore::getProjects() as $code => $projects ) {
				foreach ( $projects as $wiki => $project ) {
					if ( !$project->isActive && ( !$languages || in_array( $code, $languages, true ) )
						&& ( !$wikis || in_array( $wiki, $wikis, true ) )
					) {
						$columns[] = $project->toId();
					}
				}
			}
		}
		sort( $columns );
		foreach ( $columns as $id ) {
			$this->languageFields[ "lang-$id" ] = $id;
		}

		$this->mDb = Hermes::getDB();
		parent::__construct( $context );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabase();
		$query = TagQuery::joinProjects(
			$dbr->newSelectQueryBuilder()->from( 'hermes_tags', 't' ), 't', 'p', $this->withInactive
		)
			->select( [ 'ht_tag' => 't.ht_tag' ] )
			->groupBy( 't.ht_tag' )
			->caller( __METHOD__ );
		foreach ( array_values( $this->languageFields ) as $i => $id ) {
			$inColumn = TagQuery::inProject( $dbr, $id );
			$query->select( [
				"sort_$i" => TagQuery::sortKey( $dbr, "MIN(CASE WHEN $inColumn THEN t.ht_base_title END)" ),
			] );
		}

		if ( $this->tagFilter !== '' ) {
			$query->where( TagQuery::contains( $dbr, 't.ht_tag', $this->tagFilter ) );
		}
		if ( $this->languages ) {
			$query->where( TagQuery::inLanguages( $dbr, $this->languages ) );
		}
		if ( $this->wikis ) {
			$query->where( [ 't.ht_wiki' => $this->wikis ] );
		}
		if ( $this->conflictsOnly ) {
			$query->where( TagQuery::inConflict( $dbr ) );
		}

		return [
			'tables' => [ 'tags' => new Subquery( $query->getSQL() ) ],
			'fields' => [ 'tags.*' ],
		];
	}

	/**
	 * Loads the pages of all tags in this page of results.
	 *
	 * @param IResultWrapper $result
	 */
	protected function preprocessResults( $result ) {
		$tags = [];
		foreach ( $result as $row ) {
			$tags[] = $row->ht_tag;
		}
		$result->rewind();
		$this->links = TagQuery::loadLinks( $this->getDatabase(), $tags, $this->withInactive, $this->wikis );
	}

	/** @inheritDoc */
	public function getIndexField() {
		$column = array_search( $this->mSort, array_keys( $this->languageFields ), true );
		return [ $column === false ? [ 'ht_tag' ] : [ "sort_$column", 'ht_tag' ] ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		$fields = [ 'ht_tag' => $this->msg( 'hermestags-header-tag' )->text() ];
		foreach ( $this->languageFields as $field => $id ) {
			$fields[ $field ] = $this->formatter->projectLabel( $id );
		}
		return $fields;
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		if ( $name === 'ht_tag' ) {
			return $this->formatter->tagLink( $value );
		}

		// Nothing links to inactive pages, so they can't conflict.
		$inactive = str_contains( $this->languageFields[ $name ] ?? '', '@' );
		$lines = array_map(
			static fn ( string $line ) => Html::rawElement( 'li', [], $line ),
			$this->formatter->priorityLines( $this->getCell( $name ), !$inactive )
		);
		if ( !$lines ) {
			return '';
		}
		$html = Html::rawElement( 'ul', [ 'class' => 'ext-hermes-tag-pages' ], implode( '', $lines ) );
		return $inactive ? $this->formatter->dimmed( $html ) : $html;
	}

	/** @inheritDoc */
	protected function getCellAttrs( $field, $value ) {
		$attrs = parent::getCellAttrs( $field, $value );
		if ( str_contains( $this->languageFields[ $field ] ?? '', '@' ) ) {
			$attrs[ 'class' ] .= ' ext-hermes-inactive';
		} elseif ( TagFormatter::hasConflict( $this->getCell( $field ) ) ) {
			$attrs[ 'class' ] .= ' ext-hermes-tag-conflict';
		}
		return $attrs;
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'ht_tag';
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		return true;
	}

	/** @return array<int, TagLink[]> The current row's tag's links in a column, by priority */
	private function getCell( string $field ): array {
		$id = $this->languageFields[ $field ] ?? null;
		return $id !== null ? $this->links[ $this->mCurrentRow->ht_tag ][ $id ] ?? [] : [];
	}
}
