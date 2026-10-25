<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * HTML for the Hermes special pages: links to pages and tags, language names and tables.
 */
final class TagFormatter {

	public function __construct(
		private readonly IContextSource $context,
		private readonly LinkRenderer $linkRenderer,
	) {
	}

	/**
	 * Special:HermesPages/<language>/<title> for a page: its language identifies the wiki and translation
	 * project, the title is the namespace name as stored and the base title.
	 */
	public static function detailsTitle( PageInfo $page ): Title {
		$title = $page->title;
		$namespace = $title->getNamespaceText() !== '' ? $title->getNamespaceText() . ':' : '';
		return SpecialPage::getTitleFor( 'HermesPages', "{$title->getLanguageCode()}/$namespace{$title->baseTitle}" );
	}

	/** A link to the page itself, followed by an icon linking to its details on Special:HermesPages. */
	public function pageLink( PageInfo $page ): string {
		$details = $this->context->msg( 'hermes-page-details' )->text();
		return $page->buildLink() . ' ' . Html::element( 'a', [
			'href' => self::detailsTitle( $page )->getLocalURL(),
			'class' => 'ext-hermes-details-link',
			'title' => $details,
			'aria-label' => $details,
		] );
	}

	/** A link to the tag's details on Special:HermesTags. */
	public function tagLink( string $tag ): string {
		return $this->linkRenderer->makeKnownLink( SpecialPage::getTitleFor( 'HermesTags', $tag ), $tag );
	}

	/**
	 * Links to pages setting a tag with the same priority: in bold if they're in conflict, in italics if
	 * they're fallbacks.
	 *
	 * @param TagLink[] $links
	 * @param bool $conflict
	 * @param string $separator HTML between the pages
	 * @return string HTML
	 */
	public function pages( array $links, bool $conflict = false, string $separator = ' / ' ): string {
		if ( !$links ) {
			return '';
		}
		$html = implode( $separator, array_map( fn ( TagLink $link ) => $this->pageLink( $link->page ), $links ) );
		if ( $conflict ) {
			$html = Html::rawElement( 'b', [], $html );
		}
		if ( $links[ 0 ]->tag->priority > 0 ) {
			$html = Html::rawElement( 'i', [], $html );
		}
		return $html;
	}

	/**
	 * pages() for each priority of a tag in one language, conflicts in bold.
	 *
	 * @param array<int, TagLink[]> $byPriority
	 * @param bool $resolved False if links don't resolve here (an earlier tag in a fallback chain wins),
	 *   so there's no conflict either
	 * @return string[] HTML
	 */
	public function priorityLines( array $byPriority, bool $resolved = true ): array {
		$conflict = $resolved ? self::conflictPriority( $byPriority ) : null;
		$lines = [];
		foreach ( $byPriority as $priority => $links ) {
			$lines[] = $this->pages( $links, $priority === $conflict );
		}
		return $lines;
	}

	/**
	 * Links resolve to the pages with the lowest priority, so only a tie there is a conflict.
	 *
	 * @param array<int, TagLink[]> $byPriority
	 * @return int|null The conflicting priority, if any
	 */
	public static function conflictPriority( array $byPriority ): ?int {
		if ( !$byPriority ) {
			return null;
		}
		$lowest = min( array_keys( $byPriority ) );
		return count( $byPriority[ $lowest ] ) > 1 ? $lowest : null;
	}

	/** @param array<int, TagLink[]> $byPriority */
	public static function hasConflict( array $byPriority ): bool {
		return self::conflictPriority( $byPriority ) !== null;
	}

	/** "Autonym (code)", or just the code if the name is unknown. */
	public function languageLabel( string $code ): string {
		$name = Hermes::getAutonym( $code );
		return $name !== '' ? "$name ($code)" : $code;
	}

	/** @return array<string, string> Options for a language multiselect, label => code */
	public function languageOptions(): array {
		$codes = array_keys( LanguageStore::getActiveLanguages() );
		sort( $codes );
		$options = [];
		foreach ( $codes as $code ) {
			$name = Hermes::getAutonym( $code );
			$options[ $name !== '' ? "$code - $name" : $code ] = $code;
		}
		return $options;
	}

	/**
	 * @param string[] $headers HTML of the header cells, or none for no header
	 * @param string[] $rows HTML of the rows
	 * @param bool $sortable Whether readers can sort it (needs the jquery.tablesorter modules)
	 * @param string[] $classes Additional classes of the table
	 * @return string HTML
	 */
	public function table( array $headers, array $rows, bool $sortable = false, array $classes = [] ): string {
		$thead = $headers ? Html::rawElement( 'thead', [], Html::rawElement( 'tr', [], implode( '', $headers ) ) ) : '';
		$classes = [ 'wikitable', 'ext-hermes-details', 'sortable' => $sortable, ...$classes ];
		return Html::rawElement( 'table', [ 'class' => $classes ],
			$thead . Html::rawElement( 'tbody', [], implode( '', $rows ) )
		);
	}

	/**
	 * @param string $language Language code, labelling the row
	 * @param string[] $cells HTML of the other cells
	 * @return string HTML
	 */
	public function languageRow( string $language, array $cells ): string {
		return Html::rawElement( 'tr', [],
			Html::element( 'th', [ 'scope' => 'row' ], $this->languageLabel( $language ) ) . implode( '', $cells )
		);
	}
}
