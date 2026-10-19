<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Html\Html;
use MediaWiki\Pager\TablePager;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\Subquery;

/**
 * Lists the Hermes tags of the whole wiki family, one row per tag and one column per language, each
 * cell listing the pages with that tag; conflicts are highlighted. To make every column sortable, a
 * subquery groups hermes_tags by tag, with a sort key per language: its first title there.
 */
class HermesTagsPager extends TablePager {

	/** @var array<string, string> Language column field => language code */
	private array $languageFields = [];

	/** @var array<string, array<string, array<int, TagLink[]>>> Tag => language => priority => links */
	private array $links = [];

	/**
	 * @param IContextSource $context
	 * @param TagFormatter $formatter
	 * @param string $tagFilter Only tags containing this, if not empty
	 * @param string[] $languages Only these languages, or all if empty
	 * @param bool $conflictsOnly Only tags with a conflict
	 */
	public function __construct(
		IContextSource $context,
		private readonly TagFormatter $formatter,
		private readonly string $tagFilter,
		private readonly array $languages,
		private readonly bool $conflictsOnly,
	) {
		$columns = $languages ?: array_keys( LanguageStore::getLanguages() );
		sort( $columns );
		foreach ( $columns as $language ) {
			$this->languageFields[ "lang-$language" ] = $language;
		}

		$this->mDb = Hermes::getDB();
		parent::__construct( $context );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabase();
		$query = TagQuery::joinLanguages( $dbr->newSelectQueryBuilder()->from( 'hermes_tags', 't' ) )
			->select( [ 'ht_tag' => 't.ht_tag' ] )
			->groupBy( 't.ht_tag' )
			->caller( __METHOD__ );
		foreach ( array_values( $this->languageFields ) as $i => $language ) {
			$inLanguage = TagQuery::language() . ' = ' . $dbr->addQuotes( $language );
			$query->select( [
				"sort_$i" => TagQuery::sortKey( $dbr, "MIN(CASE WHEN $inLanguage THEN t.ht_base_title END)" ),
			] );
		}

		if ( $this->tagFilter !== '' ) {
			$query->where( TagQuery::contains( $dbr, 't.ht_tag', $this->tagFilter ) );
		}
		if ( $this->languages ) {
			$query->where( TagQuery::inLanguages( $dbr, $this->languages ) );
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
		$this->links = TagQuery::loadLinks( $this->getDatabase(), $tags );
	}

	/** @inheritDoc */
	public function getIndexField() {
		$column = array_search( $this->mSort, array_keys( $this->languageFields ), true );
		return [ $column === false ? [ 'ht_tag' ] : [ "sort_$column", 'ht_tag' ] ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		$fields = [ 'ht_tag' => $this->msg( 'hermestags-header-tag' )->text() ];
		foreach ( $this->languageFields as $field => $language ) {
			$fields[ $field ] = $this->formatter->languageLabel( $language );
		}
		return $fields;
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		if ( $name === 'ht_tag' ) {
			return $this->formatter->tagLink( $value );
		}

		$lines = array_map(
			fn ( array $pages ) => Html::rawElement( 'li', [], $this->formatter->pages( $pages ) ),
			$this->getCell( $name )
		);
		return $lines ? Html::rawElement( 'ul', [ 'class' => 'ext-hermes-tag-pages' ], implode( '', $lines ) ) : '';
	}

	/** @inheritDoc */
	protected function getCellAttrs( $field, $value ) {
		$attrs = parent::getCellAttrs( $field, $value );
		if ( TagFormatter::hasConflict( $this->getCell( $field ) ) ) {
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

	/** @return array<int, TagLink[]> The current row's tag's links in a language column, by priority */
	private function getCell( string $field ): array {
		$language = $this->languageFields[ $field ] ?? null;
		return $language !== null ? $this->links[ $this->mCurrentRow->ht_tag ][ $language ] ?? [] : [];
	}
}
