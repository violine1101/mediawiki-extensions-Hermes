<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;

/**
 * Special:HermesPages[/<language>]: all pages in the wiki family with Hermes tags, filterable by title
 * and language. Special:HermesPages/<language>/<title> shows which pages one page's tags link to.
 */
class SpecialHermesPages extends HermesSpecialPage {

	public function __construct( LanguageNameUtils $languageNameUtils ) {
		parent::__construct( 'HermesPages', $languageNameUtils );
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		$titleFilter = strtr( trim( $this->getRequest()->getText( 'title-contains' ) ), ' ', '_' );
		$parLanguage = self::parseLanguage( $par );
		$languages = $this->getLanguageFilter( $parLanguage !== null ? [ $parLanguage ] : [] );

		$this->showForm( [
			'title-contains' => [
				'type' => 'text',
				'name' => 'title-contains',
				'label-message' => 'hermespages-title',
				'default' => strtr( $titleFilter, '_', ' ' ),
			],
			'language' => $this->getLanguageField( $languages ),
		], 'hermespages-legend' );

		$this->showPager( new HermesPagesPager(
			$this->getContext(), $this->getFormatter(), $titleFilter, $languages
		) );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		if ( self::parseLanguage( $par ) !== null ) {
			return false;
		}

		$out = $this->getOutput();
		$out->addBacklinkSubtitle( $this->getPageTitle() );

		[ $language, $title ] = array_pad( explode( '/', $par, 2 ), 2, '' );
		$tags = TagQuery::findPage( $language, $title );
		if ( !$tags ) {
			$out->addWikiMsg( 'hermespages-notfound', $par );
			return true;
		}

		$page = $tags[ 0 ]->page->title->withSection( null );
		$out->setPageTitleMsg( $this->msg( 'hermespages-single', $page->getDisplayTitle( true ) ) );
		$out->addHTML( $this->linksTable( $tags, $page->getLanguageCode() ) );
		return true;
	}

	/** The registered language of a "<language>" or "<language>/" sub-page, if it is one. */
	private static function parseLanguage( string $par ): ?string {
		if ( !preg_match( '#^([^/]+)/?$#', $par, $m ) || !isset( LanguageStore::getLanguages()[ $m[ 1 ] ] ) ) {
			return null;
		}
		return $m[ 1 ];
	}

	/**
	 * One row per language, one column per tag of the page in fallback order, each cell listing the pages
	 * with that tag. In other languages, the cell the language link is taken from (the first non-empty one)
	 * is marked; the page's own language is marked as such, except where it conflicts with the page.
	 *
	 * @param TagLink[] $tags The page's tags
	 * @param string $ownLanguage
	 * @return string HTML
	 */
	private function linksTable( array $tags, string $ownLanguage ): string {
		$formatter = $this->getFormatter();
		$chain = array_map( static fn ( TagLink $link ) => $link->tag->name, $tags );
		$links = TagQuery::loadLinks( Hermes::getDB(), $chain );

		$languages = [ $ownLanguage ];
		foreach ( $links as $byLanguage ) {
			$languages = array_merge( $languages, array_keys( $byLanguage ) );
		}
		$languages = array_unique( $languages );
		sort( $languages );

		$headers = [ Html::element( 'th', [], $this->msg( 'hermespages-header-language' )->text() ) ];
		foreach ( $chain as $tag ) {
			$headers[] = Html::rawElement( 'th', [ 'scope' => 'col' ], $formatter->tagLink( $tag ) );
		}

		$rows = [];
		foreach ( $languages as $language ) {
			$isOwn = $language === $ownLanguage;
			// The page doesn't link to its own language.
			$linked = $isOwn;
			$cells = [];
			foreach ( $chain as $tag ) {
				$byPriority = $links[ $tag ][ $language ] ?? [];
				$classes = [];
				if ( $byPriority && !$linked ) {
					$linked = true;
					$classes[] = 'ext-hermes-link-target';
				}
				if ( TagFormatter::hasConflict( $byPriority ) ) {
					$classes[] = 'ext-hermes-tag-conflict';
				} elseif ( $isOwn ) {
					$classes[] = 'ext-hermes-own-language';
				}
				$lines = array_map(
					static fn ( array $pages ) => $formatter->pages( $pages, Html::element( 'br' ) ),
					$byPriority
				);
				$cells[] = Html::rawElement( 'td', [ 'class' => $classes ], implode( Html::element( 'br' ), $lines ) );
			}
			$rows[] = $formatter->languageRow( $language, $cells );
		}
		return $formatter->table( $headers, $rows );
	}
}
