<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Html\Html;

/**
 * Special:HermesPages[/<id>]: all pages in the wiki family with Hermes tags, filterable by title, language and
 * wiki, optionally with inactive projects; the sub-page is a project ID (Project::toId()), where
 * "<code>@<wiki>" also selects the wiki and shows inactive projects. Special:HermesPages/<id>/<title> shows
 * which pages one page's tags link to.
 */
class SpecialHermesPages extends HermesSpecialPage {

	public function __construct() {
		parent::__construct( 'HermesPages' );
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		$titleFilter = strtr( trim( $this->getRequest()->getText( 'title-contains' ) ), ' ', '_' );
		$parProject = Project::parseId( rtrim( $par, '/' ) );
		$parWiki = str_contains( $par, '@' ) ? $parProject?->wiki : null;
		$languages = $this->getLanguageFilter( $parProject !== null ? [ $parProject->code ] : [] );
		$wikis = $this->getWikiFilter( $parWiki !== null ? [ $parWiki ] : [] );
		$withInactive = $this->getInactiveFilter( $parWiki !== null, 'title-contains' );
		$conflictsOnly = $this->getRequest()->getBool( 'conflicts' );

		$this->showForm( [
			'title-contains' => [
				'type' => 'text',
				'name' => 'title-contains',
				'label-message' => 'hermespages-title',
				'default' => strtr( $titleFilter, '_', ' ' ),
			],
			'language' => $this->getLanguageField( $languages ),
			'wiki' => $this->getWikiField( $wikis ),
			'inactive' => $this->getInactiveField( $withInactive ),
			'conflicts' => $this->getConflictsField( $conflictsOnly ),
		], 'hermespages-legend' );

		$this->showPager( new HermesPagesPager(
			$this->getContext(), $this->getFormatter(), $titleFilter, $languages, $wikis, $withInactive, $conflictsOnly
		) );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		if ( Project::parseId( rtrim( $par, '/' ) ) !== null ) {
			return false;
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		[ $id, $title ] = array_pad( explode( '/', $par, 2 ), 2, '' );
		$tags = TagQuery::findPage( $id, $title );
		if ( !$tags ) {
			$out->addWikiMsg( 'hermespages-notfound', $par );
			return true;
		}

		$page = $tags[ 0 ]->page->title->withSection( null );
		$out->setPageTitleMsg( $this->msg( 'hermespages-single', $page->getDisplayTitle( true ) ) );
		$out->addHTML( $this->linksTable( $tags, $page->getProject()->toId() ) );
		return true;
	}

	/**
	 * One row per active language (and per inactive project, dimmed), one column per tag of the page in
	 * fallback order, each cell listing the pages with that tag. In other active languages, the cell the language
	 * link is taken from (the first non-empty one) is marked, and only it can be in conflict; the page's own row is
	 * marked as such, except where it's in conflict.
	 *
	 * @param TagLink[] $tags The page's tags
	 * @param string $ownId The page's project ID
	 * @return string HTML
	 */
	private function linksTable( array $tags, string $ownId ): string {
		$formatter = $this->getFormatter();
		$chain = array_map( static fn ( TagLink $link ) => $link->tag->name, $tags );
		$links = TagQuery::loadLinks( Hermes::getDB(), $chain, true );

		$ids = [ $ownId ];
		foreach ( $links as $byId ) {
			$ids = array_merge( $ids, array_keys( $byId ) );
		}
		$ids = array_unique( $ids );
		sort( $ids );

		$headers = [ Html::element( 'th', [], $this->msg( 'hermespages-header-language' )->text() ) ];
		foreach ( $chain as $tag ) {
			$headers[] = Html::rawElement( 'th', [ 'scope' => 'col' ], $formatter->tagLink( $tag ) );
		}

		$rows = [];
		$ownCode = explode( '@', $ownId )[ 0 ];
		foreach ( $ids as $id ) {
			$isOwn = $id === $ownId;
			$isActive = !str_contains( $id, '@' );
			// The page doesn't link to its own language (even if it's inactive itself), nor to inactive pages.
			$linked = $isOwn || !$isActive || $id === $ownCode;
			$cells = [];
			foreach ( $chain as $tag ) {
				$byPriority = $links[ $tag ][ $id ] ?? [];
				$classes = [];
				// In other languages, only the cell the link comes from can be ambiguous.
				$resolved = $isOwn && $isActive;
				if ( $byPriority && !$linked ) {
					$linked = true;
					$resolved = true;
					$classes[] = 'ext-hermes-link-target';
				}
				if ( $resolved && TagFormatter::hasConflict( $byPriority ) ) {
					$classes[] = 'ext-hermes-tag-conflict';
				} elseif ( $isOwn ) {
					$classes[] = 'ext-hermes-own-project';
				}
				$lines = $formatter->priorityLines( $byPriority, $resolved );
				$html = implode( Html::element( 'br' ), $lines );
				$cells[] = Html::rawElement( 'td', [ 'class' => $classes ],
					$isActive ? $html : $formatter->dimmed( $html )
				);
			}
			$rows[] = $formatter->projectRow( $id, $cells );
		}
		return $formatter->table( $headers, $rows );
	}
}
