<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Rewrites wikilink targets, so editors can omit the "!xx:" prefix on translation-project pages.
 * A link's project is given by its prefix ("!yy:" for a project, "!:" for the base page), or
 * defaults to the current page's:
 *  - `[[Foo]]` -> `[[!xx:Foo|Foo]]`, `[[Talk:Foo]]` -> `[[Talk:!xx:Foo|Talk:Foo]]`
 *    (display text is added so readers see what was written)
 *  - `[[Category:Foo]]` -> `[[Category:!xx:Foo]]` (no display text: after "|" it's a sort key)
 *  - `[[!:foo]]` -> `[[Foo|!:foo]]`, `[[!yy:foo]]` -> `[[!yy:Foo|!yy:foo]]`, also on non-project pages.
 *  - Left alone: unregistered "!yy:" prefixes, `[[#Section]]`, `[[/Subpage]]`, and interwiki links.
 *  - Never prefixed: File, Media, File talk and Special links. Files have no fallback for
 *    missing project versions, and special pages aren't per-language content.
 *
 * Namespace names in the project's language (e.g. "Kategorio:" on "!eo:" pages) are recognized
 * and canonicalized to the wiki's content language.
 */
class LinkRewriter {

	/**
	 * Rewrites every `[[...]]` link target in $text.
	 *
	 * @param string $text
	 * @param ProjectTitle $page The page $text belongs to.
	 * @return string
	 */
	public static function rewrite( string $text, ProjectTitle $page ): string {
		if (
			!str_contains( $text, '[[' )
			|| ( $page->project === null && !str_contains( $text, '!' ) )
		) {
			return $text;
		}

		$tc = Title::legalChars() . '#%';
		return preg_replace_callback(
			"/\\[\\[([{$tc}]+)(\\|)?/",
			static function ( array $m ) use ( $page ) {
				$hasPipe = array_key_exists( 2, $m ) && $m[2] !== '';
				return '[[' . self::rewriteTarget( $m[1], $page, $hasPipe ) . ( $hasPipe ? '|' : '' );
			},
			$text
		);
	}

	/**
	 * Rewrites a single link target (the part of "[[...]]" before any "|"), adding display text
	 * if $hasPipe is false and the target changed.
	 */
	private static function rewriteTarget( string $raw, ProjectTitle $page, bool $hasPipe ): string {
		if ( $page->project === null && !str_contains( $raw, '!' ) ) {
			// Fast path: this runs for every link on every page.
			return $raw;
		}

		$trimmed = ltrim( $raw, ' ' );
		if ( $trimmed === '' ) {
			return $raw;
		}
		$leadingSpace = substr( $raw, 0, strlen( $raw ) - strlen( $trimmed ) );

		// A leading ":" (e.g. "[[:Category:Foo]]") doesn't change the target, but must be kept.
		$hadColon = $trimmed[0] === ':';
		$target = $hadColon ? substr( $trimmed, 1 ) : $trimmed;
		if ( $target === '' ) {
			return $raw;
		}

		$contentLang = MediaWikiServices::getInstance()->getContentLanguage();
		$projectLang = $page->project !== null ? $page->getLanguage() : $contentLang;

		$colonPos = strpos( $target, ':' );
		$nsPrefix = $colonPos !== false ? substr( $target, 0, $colonPos ) : '';
		$afterNs = $colonPos !== false ? substr( $target, $colonPos + 1 ) : '';

		$nsId = null;
		$nsOutputText = null;
		$nsFromProjectLang = false;
		$rest = $target;

		if ( $nsPrefix !== '' ) {
			$ns = $contentLang->getNsIndex( $nsPrefix );
			if ( $ns !== false ) {
				$nsId = $ns;
				$nsOutputText = $nsPrefix;
				$rest = $afterNs;
			} else {
				$ns = $projectLang->getNsIndex( $nsPrefix );
				if ( $ns !== false ) {
					$nsId = $ns;
					$nsFromProjectLang = true;
					// Core only knows the content language's namespace names.
					$nsOutputText = $contentLang->getFormattedNsText( $ns );
					$rest = $afterNs;
				}
			}
		}

		if ( $nsId === null ) {
			// Not a namespace - leave interwiki links (e.g. "de:Foo") alone.
			$nt = Title::newFromText( $trimmed );
			if ( $nt === null || $nt->isExternal() ) {
				return $raw;
			}
			$nsId = NS_MAIN;
			$rest = $target;
		}

		if ( !Hermes::isProjectNamespace( $nsId ) ) {
			// These only have base pages, so "!:" is redundant.
			$unescapedRest = ProjectTitle::unescape( $rest ) ?? $rest;
			if ( !$nsFromProjectLang && $unescapedRest === $rest ) {
				return $raw;
			}
			return $leadingSpace
				. ( $hadColon ? ':' : '' )
				. "{$nsOutputText}:"
				. $unescapedRest;
		}

		$hashPos = strpos( $rest, '#' );
		$bareTitle = $hashPos !== false ? substr( $rest, 0, $hashPos ) : $rest;
		$fragment = $hashPos !== false ? substr( $rest, $hashPos ) : '';

		if ( $bareTitle === '' || ( $nsOutputText === null && str_starts_with( $bareTitle, '/' ) ) ) {
			// "[[#Section]]" and "[[/Sub]]" are already relative to the current page.
			return $raw;
		}

		$resolved = self::resolveBareTitle( $bareTitle, $nsId, $page->project );
		if ( !$nsFromProjectLang && strtr( $resolved, '_', ' ' ) === strtr( $bareTitle, '_', ' ' ) ) {
			return $raw;
		}

		$result = $leadingSpace
			. ( $hadColon ? ':' : '' )
			. ( $nsOutputText !== null ? "{$nsOutputText}:" : '' )
			. $resolved
			. $fragment;

		// In a category assignment (but not a "[[:Category:...]]" link), "|" starts a sort key.
		$pipeMeansSortKey = $nsId === NS_CATEGORY && !$hadColon;
		if ( !$hasPipe && !$pipeMeansSortKey ) {
			$result .= '|' . $target;
		}

		return $result;
	}

	/**
	 * The title (without namespace or fragment) $bareTitle links to, in the project its prefix
	 * names, else in $defaultProject (null for the base page).
	 */
	private static function resolveBareTitle( string $bareTitle, int $nsId, ?string $defaultProject ): string {
		$title = ProjectTitle::unescape( $bareTitle );
		$project = null;
		if ( $title === null ) {
			[ $project, $title ] = ProjectTitle::parse( $bareTitle );
			if ( $project === null ) {
				if ( $defaultProject === null || ProjectTitle::isReserved( $bareTitle ) ) {
					// Invalid "!...:" prefixes are left for ProjectTitle::hasInvalidTitle() to report.
					return $bareTitle;
				}
				$project = $defaultProject;
				$title = $bareTitle;
			}
		}

		return $project !== null
			? ProjectTitle::addPrefix( $title, $nsId, $project )
			: Title::capitalize( $title, $nsId );
	}
}
