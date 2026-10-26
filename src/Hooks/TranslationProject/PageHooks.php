<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Content\Hook\PageContentLanguageHook;
use MediaWiki\Context\Hook\UserGetLanguageObjectHook;
use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Html\Html;
use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

/**
 * Page-level behavior of translation-project pages: page and interface language, display title,
 * hatnote, and title validation.
 */
class PageHooks implements
	GetUserPermissionsErrorsHook,
	PageContentLanguageHook,
	UserGetLanguageObjectHook,
	ParserAfterParseHook,
	OutputPageBeforeHTMLHook,
	BeforeInitializeHook
{

	private const CHECKED_ACTIONS = [ 'create', 'move-target', 'upload' ];

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( !in_array( $action, self::CHECKED_ACTIONS, true ) ) {
			return true;
		}

		$error = ProjectTitle::newFromLinkTarget( $title )->getError();
		if ( $error !== null ) {
			$result = $error;
			return false;
		}

		return true;
	}

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWikiEntryPoint ) {
		// A pre-existing miscapitalized page must stay directly accessible at its actual title,
		// not get redirected away to a likely-nonexistent "corrected" one.
		if ( $title->exists() ) {
			return;
		}

		$projectTitle = ProjectTitle::newFromLinkTarget( $title );
		if ( !$projectTitle->hasCapitalizationError() ) {
			return;
		}

		$query = $request->getQueryValues();
		unset( $query[ 'title' ] );
		$output->redirect( $projectTitle->toTitle()->getFullURL( $query ) );
		return false;
	}

	/** @inheritDoc */
	public function onPageContentLanguage( $title, &$pageLang, $userLang ) {
		$projectTitle = ProjectTitle::newFromLinkTarget( $title );
		if ( !$projectTitle->getProject()->isBase ) {
			$pageLang = $projectTitle->getProject()->getLanguage();
		}
	}

	/** @inheritDoc */
	public function onUserGetLanguageObject( $user, &$code, $context ) {
		if ( $context->getRequest()->getCheck( 'uselang' ) ) {
			return;
		}

		$title = $context->getTitle();
		$contentLanguage = $title
			? ProjectTitle::newFromLinkTarget( $title )->getProject()->code
			: ProjectStore::getLocalBaseLanguage();
		// Core's "language" option (the incoming $code) is hidden in favor of Hermes's own; users whose
		// options can't be loaded yet get the content language, like logged-out users.
		$code = $user->isSafeToLoad() ? DisplayLanguage::forUser( $user, $contentLanguage ) : $contentLanguage;
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$parserPage = $parser->getPage();
		if ( $parserPage === null ) {
			return;
		}

		$page = ProjectTitle::newFromLinkTarget( $parserPage );
		if ( $page->getProject()->isBase ) {
			return;
		}

		$parser->getOutput()->setDisplayTitle( Parser::formatPageTitle(
			$page->getLocalizedNamespaceText(), ':', $page->getDisplayTitle(), $page->getProject()->getLanguage()
		) );
	}

	/** @inheritDoc */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		$project = ProjectTitle::newFromLinkTarget( $title )->getProject();
		if ( $project->isBase ) {
			return;
		}

		$hatnote = Html::rawElement(
			'div',
			[ 'class' => 'subpages' ],
			$out->msg( 'hermes-project-hatnote', $project->code )->parse()
		);

		$out->addSubtitle( $hatnote );
	}
}
