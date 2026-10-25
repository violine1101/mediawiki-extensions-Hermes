<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Html\Html;
use MediaWiki\Pager\TablePager;
use Wikimedia\Rdbms\Subquery;

/**
 * Lists the pages of the whole wiki family that have Hermes tags, one column per fallback priority;
 * tags in a conflict are highlighted, and pages of inactive projects (if shown) dimmed. To make every
 * column sortable, a subquery groups hermes_tags by page, with the tag, section, conflict flag and a sort key
 * per priority.
 */
class HermesPagesPager extends TablePager {

	/** Unique per page, for stable paging. */
	private const TIEBREAK = [ 'ht_base_title', 'ht_wiki', 'ht_page_id' ];

	/** @var array<string, int> Priority column field => priority */
	private array $priorityFields = [];

	/**
	 * @param IContextSource $context
	 * @param TagFormatter $formatter
	 * @param string $titleFilter Only base titles containing this, in DB key form, if not empty
	 * @param string[] $languages Only these languages, or all if empty
	 * @param string[] $wikis Only pages on these wikis, or all if empty
	 * @param bool $withInactive Whether to include pages of inactive projects
	 * @param bool $conflictsOnly Only pages with a tag in a conflict
	 */
	public function __construct(
		IContextSource $context,
		private readonly TagFormatter $formatter,
		private readonly string $titleFilter,
		private readonly array $languages,
		private readonly array $wikis,
		private readonly bool $withInactive,
		private readonly bool $conflictsOnly,
	) {
		$this->mDb = Hermes::getDB();
		$maxPriority = (int)$this->mDb->newSelectQueryBuilder()
			->select( 'MAX(ht_priority)' )
			->from( 'hermes_tags' )
			->caller( __METHOD__ )
			->fetchField();
		foreach ( range( 0, $maxPriority ) as $priority ) {
			$this->priorityFields[ "priority-$priority" ] = $priority;
		}

		parent::__construct( $context );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabase();
		$pageFields = [
			't.ht_wiki', 't.ht_page_id', 't.ht_namespace_id', 't.ht_namespace_text',
			't.ht_translation_project', 't.ht_base_title',
		];
		$query = TagQuery::joinProjects(
			$dbr->newSelectQueryBuilder()->from( 'hermes_tags', 't' ), 't', 'p', $this->withInactive
		)
			->select( $pageFields )
			->select( [
				'language' => TagQuery::language(),
				'is_active' => 'p.hp_active',
				'has_conflict' => 'MAX(CASE WHEN ' . TagQuery::inConflict( $dbr ) . ' THEN 1 ELSE 0 END)',
			] )
			->groupBy( [ ...$pageFields, 'p.hp_language', 'p.hp_active' ] )
			->caller( __METHOD__ );
		foreach ( $this->priorityFields as $priority ) {
			$tag = "MAX(CASE WHEN t.ht_priority = $priority THEN t.ht_tag END)";
			$query->select( [
				"tag_$priority" => $tag,
				"section_$priority" => "MAX(CASE WHEN t.ht_priority = $priority THEN t.ht_section END)",
				"conflict_$priority" => "MAX(CASE WHEN t.ht_priority = $priority AND " . TagQuery::inConflict( $dbr )
					. ' THEN 1 ELSE 0 END)',
				"sort_$priority" => TagQuery::sortKey( $dbr, $tag ),
			] );
		}

		if ( $this->titleFilter !== '' ) {
			$query->where( TagQuery::contains( $dbr, 't.ht_base_title', $this->titleFilter ) );
		}
		if ( $this->languages ) {
			$query->where( TagQuery::inLanguages( $dbr, $this->languages ) );
		}
		if ( $this->wikis ) {
			$query->where( [ 't.ht_wiki' => $this->wikis ] );
		}

		return [
			'tables' => [ 'pages' => new Subquery( $query->getSQL() ) ],
			// The page as a whole, not one of its tags' sections.
			'fields' => [ 'pages.*', 'ht_section' => 'NULL' ],
			'conds' => $this->conflictsOnly ? [ 'has_conflict' => 1 ] : [],
		];
	}

	/** @inheritDoc */
	public function getIndexField() {
		if ( isset( $this->priorityFields[ $this->mSort ] ) ) {
			return [ [ 'sort_' . $this->priorityFields[ $this->mSort ], ...self::TIEBREAK ] ];
		}
		if ( $this->mSort === 'language' ) {
			return [ [ 'language', ...self::TIEBREAK ] ];
		}
		return [ self::TIEBREAK ];
	}

	/** @inheritDoc */
	protected function getFieldNames() {
		$fields = [
			'ht_base_title' => $this->msg( 'hermespages-header-page' )->text(),
			'language' => $this->msg( 'hermespages-header-language' )->text(),
		];
		foreach ( $this->priorityFields as $field => $priority ) {
			$fields[ $field ] = $this->msg( 'hermespages-header-priority' )->numParams( $priority )->text();
		}
		return $fields;
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		$html = $this->formatCell( $name, $value );
		return $this->mCurrentRow->is_active ? $html : $this->formatter->dimmed( $html );
	}

	private function formatCell( string $name, ?string $value ): string {
		$row = $this->mCurrentRow;
		if ( $name === 'ht_base_title' ) {
			return $this->formatter->pageLink( PageInfo::fromRow( $row ) );
		}
		if ( $name === 'language' ) {
			$id = PageInfo::fromRow( $row )->title->getProject()?->toId() ?? $value;
			return htmlspecialchars( $this->formatter->projectLabel( $id ) );
		}

		$priority = $this->priorityFields[ $name ];
		$tag = $row->{"tag_$priority"};
		return $tag !== null ? $this->formatTag( $tag, $row->{"section_$priority"} ) : '';
	}

	/** A link to the tag's details, followed by a link to its section on the page, if any. */
	private function formatTag( string $tag, ?string $section ): string {
		$html = $this->formatter->tagLink( $tag );
		if ( $section === null ) {
			return $html;
		}

		$text = '#' . strtr( $section, '_', ' ' );
		$url = PageInfo::fromRow( $this->mCurrentRow )->title->withSection( $section )->getUrl();
		$sectionLink = $url !== null
			? Html::element( 'a', [ 'href' => $url, 'class' => 'extiw' ], $text )
			: htmlspecialchars( $text );
		return "$html ($sectionLink)";
	}

	/** @inheritDoc */
	protected function getCellAttrs( $field, $value ) {
		$attrs = parent::getCellAttrs( $field, $value );
		$priority = $this->priorityFields[ $field ] ?? null;
		if ( $priority !== null && $this->mCurrentRow->{"conflict_$priority"} ) {
			$attrs[ 'class' ] .= ' ext-hermes-tag-conflict';
		}
		return $attrs;
	}

	/** @inheritDoc */
	protected function getRowClass( $row ) {
		return $row->is_active ? '' : 'ext-hermes-inactive';
	}

	/** @inheritDoc */
	public function getDefaultSort() {
		return 'ht_base_title';
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ) {
		return true;
	}
}
