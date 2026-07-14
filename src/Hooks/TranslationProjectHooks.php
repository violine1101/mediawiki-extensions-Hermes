<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Content\Hook\PageContentLanguageHook;
use MediaWiki\Context\Hook\UserGetLanguageObjectHook;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Html\Html;
use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

class TranslationProjectHooks implements
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

		$page = PageInfo::fromLocalPage( $title );

		if ( $page->hasInvalidTitle() ) {
			$result = 'hermes-invalid-project-title';
			return false;
		}

		if ( $page->hasCapitalizationError() ) {
			$result = 'hermes-title-not-capitalized';
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

		$page = PageInfo::fromLocalPage( $title );
		if ( !$page->hasCapitalizationError() ) {
			return;
		}

		$query = $request->getQueryValues();
		unset( $query[ 'title' ] );
		$output->redirect( $page->getCapitalizedTitle()->getFullURL( $query ) );
		return false;
	}

	/** @inheritDoc */
	public function onPageContentLanguage( $title, &$pageLang, $userLang ) {
		$page = PageInfo::fromLocalPage( $title );
		if ( $page->translationProject !== null ) {
			$pageLang = $page->getLanguage();
		}
	}

	/** @inheritDoc */
	public function onUserGetLanguageObject( $user, &$code, $context ) {
		// Respect an explicit ?uselang= request over the project's language.
		if ( $context->getRequest()->getCheck( 'uselang' ) ) {
			return;
		}

		$title = $context->getTitle();
		if ( !$title ) {
			return;
		}

		$page = PageInfo::fromLocalPage( $title );
		if ( $page->translationProject !== null ) {
			$code = $page->translationProject;
		}
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$page = PageInfo::fromLocalPage( $parser->getPage() );
		if ( $page->translationProject === null ) {
			return;
		}

		$lang = $page->getLanguage();
		$nsText = $lang->getFormattedNsText( $page->namespace );

		$parser->getOutput()->setDisplayTitle(
			Parser::formatPageTitle( $nsText, ':', $page->title, $lang )
		);
	}

	/** @inheritDoc */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		$title = $out->getTitle();
		if ( !$title ) {
			return;
		}

		$page = PageInfo::fromLocalPage( $title );
		if ( $page->translationProject === null ) {
			return;
		}

		$hatnote = Html::rawElement(
			'div',
			[ 'class' => 'subpages' ],
			$out->msg( 'hermes-project-hatnote', $page->translationProject )->parse()
		);

		$out->addSubtitle( $hatnote );
	}
}
