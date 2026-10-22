<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;

/**
 * Special:HermesTags: all Hermes tags in the wiki family, filterable by tag name and language, with
 * conflicts highlighted. Special:HermesTags/<tag> shows a single tag, one row per language and one
 * column per priority.
 */
class SpecialHermesTags extends HermesSpecialPage {

	public function __construct( LanguageNameUtils $languageNameUtils ) {
		parent::__construct( 'HermesTags', $languageNameUtils );
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		$request = $this->getRequest();
		// Normalized like tag names, but partial names are fine here.
		$tagFilter = trim( preg_replace( '/[\s_]+/', '_', strtolower( $request->getText( 'tag' ) ) ), '_' );
		$languages = $this->getLanguageFilter();
		$conflictsOnly = $request->getBool( 'conflicts' );

		$this->showForm( [
			'tag' => [
				'type' => 'text',
				'name' => 'tag',
				'label-message' => 'hermestags-tag',
				'default' => $tagFilter,
			],
			'language' => $this->getLanguageField( $languages ),
			'conflicts' => $this->getConflictsField( $conflictsOnly ),
		], 'hermestags-legend' );

		$this->showPager( new HermesTagsPager(
			$this->getContext(), $this->getFormatter(), $tagFilter, $languages, $conflictsOnly
		) );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		$out = $this->getOutput();
		try {
			$tag = Tag::fromArg( $par )->name;
		} catch ( InvalidTagNameException $e ) {
			$out->addHTML( Html::errorBox( $this->msg( 'hermes-invalid-tag-name', $e->tagName )->parse() ) );
			return true;
		}
		$out->setPageTitleMsg( $this->msg( 'hermestags-single', $tag ) );
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		$byLanguage = TagQuery::loadLinks( Hermes::getDB(), [ $tag ] )[ $tag ] ?? [];
		if ( !$byLanguage ) {
			$out->addWikiMsg( 'hermestags-empty' );
			return true;
		}
		ksort( $byLanguage );
		$maxPriority = max( array_map( static fn ( array $links ) => max( array_keys( $links ) ), $byLanguage ) );
		$priorities = range( 0, $maxPriority );

		$formatter = $this->getFormatter();
		$rows = [];
		foreach ( $byLanguage as $language => $links ) {
			$conflict = TagFormatter::conflictPriority( $links );
			$cells = [];
			foreach ( $priorities as $priority ) {
				$cells[] = Html::rawElement(
					'td',
					[ 'class' => $priority === $conflict ? 'ext-hermes-tag-conflict' : null ],
					$formatter->pages( $links[ $priority ] ?? [], $priority === $conflict, Html::element( 'br' ) )
				);
			}
			$rows[] = $formatter->languageRow( $language, $cells );
		}

		// With a single column, there's nothing for a priority header to tell apart.
		$headers = [];
		if ( $maxPriority > 0 ) {
			$headers[] = Html::element( 'th', [], $this->msg( 'hermestags-header-priority' )->text() );
			foreach ( $priorities as $priority ) {
				$headers[] = Html::element( 'th', [ 'scope' => 'col' ], $this->getLanguage()->formatNum( $priority ) );
			}
		}
		$out->addHTML( $formatter->table( $headers, $rows ) );
		return true;
	}
}
