<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Extension\Hermes\TagStore;
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

	/** @var array<string, Project> Column field => project */
	private array $projectFields = [];

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
		$columns = [];
		foreach ( ProjectStore::getProjects() as $code => $projects ) {
			foreach ( $projects as $wiki => $project ) {
				// The wiki filter only hides inactive projects; active ones show their pages on those wikis.
				$shown = $project->isActive || (
					$withInactive && ( !$wikis || in_array( $wiki, $wikis, true ) )
				);
				if ( $shown && ( !$languages || in_array( $code, $languages, true ) ) ) {
					$columns[ $project->toId() ] = $project;
				}
			}
		}
		ksort( $columns );
		foreach ( $columns as $id => $project ) {
			$this->projectFields[ "project-$id" ] = $project;
		}

		$this->mDb = Hermes::getDB();
		parent::__construct( $context );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$query = TagStore::newTagsQuery(
			$this->projectFields, $this->tagFilter, $this->languages, $this->wikis, $this->withInactive,
			$this->conflictsOnly
		);
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
		$this->links = TagStore::loadLinks( $tags, $this->withInactive, $this->wikis );
	}

	/** @inheritDoc */
	public function getIndexField() {
		$column = array_search( $this->mSort, array_keys( $this->projectFields ), true );
		return [ $column === false ? [ 'ht_tag' ] : [ "sort_$column", 'ht_tag' ] ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		$fields = [ 'ht_tag' => $this->msg( 'hermestags-header-tag' )->text() ];
		foreach ( $this->projectFields as $field => $project ) {
			$fields[ $field ] = $this->formatter->projectLabel( $project );
		}
		return $fields;
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		if ( $name === 'ht_tag' ) {
			return $this->formatter->tagLink( $value );
		}

		// Nothing links to inactive pages, so they can't conflict.
		$inactive = !( $this->projectFields[ $name ]->isActive ?? true );
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
		if ( !( $this->projectFields[ $field ]->isActive ?? true ) ) {
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
		$id = ( $this->projectFields[ $field ] ?? null )?->toId();
		return $id !== null ? $this->links[ $this->mCurrentRow->ht_tag ][ $id ] ?? [] : [];
	}
}
