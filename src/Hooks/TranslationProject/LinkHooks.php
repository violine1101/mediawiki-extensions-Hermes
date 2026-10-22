<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\LinkRewriter;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Parser\Hook\InternalParseBeforeLinksHook;
use MediaWiki\Parser\Hook\ParserAfterTidyHook;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Links in and to translation projects: rewrites link targets on project pages (see LinkRewriter),
 * sets hover texts, and when a linked "!xx:Foo" page is missing but the base page "Foo" exists,
 * makes the red link go to Special:HermesTranslate for translating the base page, and appends a
 * "(en)" link to the base page. If the base page has a translation under another title, appends
 * "(en, xx)" instead; if it shares the base page's priority-0 tag at priority 0, the red link creates
 * a redirect to it (see TranslateHooks).
 * In Hermes::LINK_FALLBACK_NAMESPACES, the link goes straight to the base page instead.
 */
class LinkHooks implements
	InternalParseBeforeLinksHook,
	HtmlPageLinkRendererBeginHook,
	HtmlPageLinkRendererEndHook,
	ParserAfterTidyHook
{

	/** Prevents recursion when rendering the main link from within the hook */
	private static bool $renderingMainLink = false;

	/** @inheritDoc */
	public function onInternalParseBeforeLinks( $parser, &$text, $stripState ) {
		$parserPage = $parser->getPage();
		if ( $parserPage === null ) {
			return;
		}

		$text = LinkRewriter::rewrite( $text, ProjectTitle::newFromLinkTarget( $parserPage ) );
	}

	/** @inheritDoc */
	public function onHtmlPageLinkRendererBegin( $linkRenderer, $target, &$text, &$customAttribs, &$query, &$ret ) {
		if ( self::$renderingMainLink ) {
			return true;
		}

		if ( $query !== [] ) {
			// Not a content link (e.g. action=edit), and possibly from makeKnownLink().
			return true;
		}

		if ( $target->isExternal() ) {
			return true;
		}

		// Cheap prefix check first: this runs for every rendered link.
		$projectTitle = ProjectTitle::newFromLinkTarget( $target );
		if ( $projectTitle->project === null ) {
			return true;
		}

		$baseTitle = $projectTitle->withProject( null )->toTitle();
		if ( Title::newFromLinkTarget( $target )->exists() ) {
			return true;
		}

		if ( Hermes::hasLinkFallback( $target->getNamespace() ) ) {
			self::$renderingMainLink = true;
			try {
				$ret = $linkRenderer->makeLink( $baseTitle, $text, $customAttribs, $query );
			} finally {
				self::$renderingMainLink = false;
			}
			return false;
		}

		if ( !$baseTitle->exists() ) {
			return true;
		}

		$fallbackLinks = [ $linkRenderer->makeKnownLink( $baseTitle, LanguageStore::getLocalBaseLanguage() ) ];
		// The translation may exist under another title, linked by tags.
		$basePage = PageInfo::fromLocalPage( $baseTitle );
		$translation = TagStore::getLinksForPage( $basePage )[ $projectTitle->project ] ?? null;
		$redirectTo = null;
		if ( $translation !== null && $translation->page->title->isLocal() ) {
			$translationTitle = $translation->page->title->toTitle();
			$fallbackLinks[] = $linkRenderer->makeKnownLink( $translationTitle, $projectTitle->project );
			// Only a redirect to a real translation, not a page it falls back to.
			if ( isset( TagStore::getTranslationsOfPage( $basePage )[ $projectTitle->project ] ) ) {
				$redirectTo = $translationTitle;
			}
		}

		self::$renderingMainLink = true;
		try {
			if ( $redirectTo !== null ) {
				// Creating the page as a redirect to the translation.
				$mainLink = $linkRenderer->makeLink( $target, $text, $customAttribs, [
					TranslateHooks::REDIRECT_PARAM => $redirectTo->getPrefixedText(),
				] );
			} else {
				// Still a red link, in the project's language like onHtmlPageLinkRendererEnd() does.
				$mainLink = $linkRenderer->makeKnownLink(
					SpecialPage::getTitleFor( 'HermesTranslate', $baseTitle->getPrefixedText() ),
					$text ?? Title::newFromLinkTarget( $target )->getPrefixedText(),
					[
						'class' => trim( ( $customAttribs['class'] ?? '' ) . ' new' ),
						'title' => $customAttribs['title'] ?? wfMessage(
							'red-link-title', $projectTitle->getDisplayTitle( true )
						)->inLanguage( $projectTitle->getLanguage() )->text(),
					] + $customAttribs,
					[ 'language' => $projectTitle->project ]
				);
			}
		} finally {
			self::$renderingMainLink = false;
		}

		$ret = $mainLink . Html::rawElement( 'sup', [ 'class' => 'hermes-fallback-link' ],
			'(' . implode( wfMessage( 'comma-separator' )->inContentLanguage()->escaped(), $fallbackLinks ) . ')'
		);
		return false;
	}

	/**
	 * Makes the parser cache depend on the base pages of linked "!xx:" pages, so the "(xx)" link
	 * updates when they're created or deleted. (Links aren't registered yet in ParserAfterParse.)
	 *
	 * @inheritDoc
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		$output = $parser->getOutput();
		foreach ( $output->getLinkList( ParserOutputLinkTypes::LOCAL ) as [ 'link' => $link ] ) {
			$projectTitle = ProjectTitle::newFromLinkTarget( $link );
			if ( $projectTitle->project !== null ) {
				$output->addExistenceDependency( $projectTitle->withProject( null )->toTitle() );
			}
		}
	}

	/**
	 * Core's hover text for links shows the "!xx:" prefix, and for red links is in the wiki's
	 * content language. Use the display title and the target project's language instead.
	 *
	 * @inheritDoc
	 */
	public function onHtmlPageLinkRendererEnd( $linkRenderer, $target, $isKnown, &$text, &$attribs, &$ret ) {
		// Cheap prefix check first: this runs for every rendered link.
		if (
			$target->isExternal()
			|| !isset( $attribs['title'] )
			|| ProjectTitle::newFromLinkTarget( $target )->project === null
		) {
			return;
		}

		$title = Title::newFromLinkTarget( $target );
		$page = ProjectTitle::newFromLinkTarget( $title );

		$prefixedText = $title->getPrefixedText();
		$displayTitle = $page->getDisplayTitle( true );
		if ( $isKnown ) {
			$default = $prefixedText;
			$replacement = $displayTitle;
		} else {
			$default = wfMessage( 'red-link-title', $prefixedText )->inContentLanguage()->text();
			$replacement = wfMessage( 'red-link-title', $displayTitle )
				->inLanguage( $page->getLanguage() )->text();
		}

		// Leave hover texts set explicitly by the caller alone.
		if ( $attribs['title'] === $default ) {
			$attribs['title'] = $replacement;
		}
	}
}
