<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Rewrites wikilink targets on translation-project pages, so editors can omit the "!xx:" prefix:
 *  - `[[Foo]]` -> `[[!xx:Foo|Foo]]`, `[[Talk:Foo]]` -> `[[Talk:!xx:Foo|Talk:Foo]]`
 *    (display text is added so readers don't see the prefix)
 *  - `[[Category:Foo]]` -> `[[Category:!xx:Foo]]` (no display text: after "|" it's a sort key)
 *  - `[[!:Foo]]` -> `[[Foo]]`, an escape to the base page. Also applied on non-project pages.
 *  - Left alone: `[[!yy:Foo]]`, `[[#Section]]`, `[[/Subpage]]`, and interwiki links.
 *  - Never prefixed: File, Media, File talk and Special links. Files have no fallback for
 *    missing project versions, and special pages aren't per-language content.
 *
 * Namespace names in the project's language (e.g. "Kategorio:" on "!eo:" pages) are recognized
 * and canonicalized to the wiki's content language.
 */
class LinkRewriter {

	/**
	 * Rewrites every `[[...]]` link target in $text. On non-project pages, only "!:" escapes
	 * are stripped.
	 *
	 * @param string $text
	 * @param ProjectTitle $page The page $text belongs to.
	 * @return string
	 */
	public static function rewrite( string $text, ProjectTitle $page ): string {
		if (
			!str_contains( $text, '[[' )
			|| ( $page->project === null && !str_contains( $text, '!:' ) )
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
	 * if $hasPipe is false and the rendered text would otherwise change.
	 */
	private static function rewriteTarget( string $raw, ProjectTitle $page, bool $hasPipe ): string {
		if ( $page->project === null && !str_contains( $raw, '!:' ) ) {
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
		$unescaped = $hadColon ? substr( $trimmed, 1 ) : $trimmed;
		if ( $unescaped === '' ) {
			return $raw;
		}

		$contentLang = MediaWikiServices::getInstance()->getContentLanguage();
		$projectLang = $page->project !== null ? $page->getLanguage() : $contentLang;

		$colonPos = strpos( $unescaped, ':' );
		$nsPrefix = $colonPos !== false ? substr( $unescaped, 0, $colonPos ) : '';
		$afterNs = $colonPos !== false ? substr( $unescaped, $colonPos + 1 ) : '';

		$nsId = null;
		$nsOutputText = null;
		$nsFromProjectLang = false;
		$rest = $unescaped;

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
			$rest = $unescaped;
		}

		if ( !Hermes::isProjectNamespace( $nsId ) ) {
			// Still strip "!:" escapes, e.g. from core's "[[:Template:!:Foo]]" red link for a
			// missing "{{!:Foo}}" transclusion.
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

		if ( $page->project === null && ProjectTitle::unescape( $bareTitle ) === null ) {
			return $raw;
		}

		$insertingPrefix = !ProjectTitle::isReserved( $bareTitle );
		$rewrittenBareTitle = self::rewriteBareTitle( $bareTitle, $nsId, $page->project );

		$result = $leadingSpace
			. ( $hadColon ? ':' : '' )
			. ( $nsOutputText !== null ? "{$nsOutputText}:" : '' )
			. $rewrittenBareTitle
			. $fragment;

		// In a category assignment (but not a "[[:Category:...]]" link), "|" starts a sort key.
		$pipeMeansSortKey = $nsId === NS_CATEGORY && !$hadColon;
		if ( $insertingPrefix && !$hasPipe && !$pipeMeansSortKey ) {
			$result .= '|' . $unescaped;
		}

		return $result;
	}

	/**
	 * Inserts or strips the "!xx:" prefix on a title without namespace or fragment.
	 * $projectLanguage is null on non-project pages.
	 */
	private static function rewriteBareTitle( string $bareTitle, int $nsId, ?string $projectLanguage ): string {
		$unescaped = ProjectTitle::unescape( $bareTitle );
		if ( $unescaped !== null ) {
			return $unescaped;
		}

		if ( $projectLanguage === null || ProjectTitle::isReserved( $bareTitle ) ) {
			// Includes "!yy:" titles. Invalid ones are left for ProjectTitle::hasInvalidTitle() to report.
			return $bareTitle;
		}

		return ProjectTitle::addPrefix( $bareTitle, $nsId, $projectLanguage );
	}
}
