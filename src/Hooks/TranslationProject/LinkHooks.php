<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\LinkRewriter;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererBeginHook;
use MediaWiki\Linker\Hook\HtmlPageLinkRendererEndHook;
use MediaWiki\Parser\Hook\InternalParseBeforeLinksHook;
use MediaWiki\Parser\Hook\ParserAfterTidyHook;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;

/**
 * Links in and to translation projects: rewrites link targets on project pages (see LinkRewriter),
 * sets hover texts, and when a linked "!xx:Foo" page is missing but the base page "Foo" exists,
 * appends a "(xx)" link to the base page after the red link. In Hermes::LINK_FALLBACK_NAMESPACES,
 * the link goes straight to the base page instead.
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

		self::$renderingMainLink = true;
		try {
			$mainLink = $linkRenderer->makeLink( $target, $text, $customAttribs, $query );
		} finally {
			self::$renderingMainLink = false;
		}

		$fallbackLink = $linkRenderer->makeKnownLink( $baseTitle, LanguageStore::getLocalBaseLanguage() );

		$ret = $mainLink . Html::rawElement( 'sup', [ 'class' => 'hermes-fallback-link' ], "({$fallbackLink})" );
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
