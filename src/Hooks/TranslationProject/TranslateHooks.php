<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Special\SpecialHermesTranslate;
use MediaWiki\Hook\EditFormPreloadTextHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Translating base pages: a "Translate" action linking to Special:HermesTranslate while some translation
 * project lacks the page, and starting the new translation-project page with the wikitext of the base page
 * given as PARAM in its edit URL (or a redirect to the page given as REDIRECT_PARAM).
 */
class TranslateHooks implements EditFormPreloadTextHook, SkinTemplateNavigation__UniversalHook {

	public const PARAM = 'hermes-preload';
	/** For a red link to "!xx:Foo" when "Foo" is translated under another title: a redirect there */
	public const REDIRECT_PARAM = 'hermes-redirect';

	public function __construct(
		private readonly RevisionLookup $revisionLookup,
		private readonly IContentHandlerFactory $contentHandlerFactory,
	) {
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$title = $sktemplate->getTitle();
		if ( !$title->exists() || !Hermes::isProjectNamespace( $title->getNamespace() ) ) {
			return;
		}
		$page = ProjectTitle::newFromLinkTarget( $title );
		if ( $page->project !== null ) {
			return;
		}

		if ( SpecialHermesTranslate::getUntranslatedProjects( $page ) ) {
			$links['actions']['hermes-translate'] = [
				'text' => $sktemplate->msg( 'hermes-translate-action' )->text(),
				'href' => SpecialPage::getTitleFor( 'HermesTranslate', $title->getPrefixedText() )->getLocalURL(),
			];
		}
	}

	/** @inheritDoc */
	public function onEditFormPreloadText( &$text, $title ) {
		$context = RequestContext::getMain();
		$project = ProjectTitle::newFromLinkTarget( $title )->project;
		if ( $project === null ) {
			return;
		}

		$redirect = Title::newFromText( $context->getRequest()->getText( self::REDIRECT_PARAM ) );
		if ( $redirect !== null && ProjectTitle::newFromLinkTarget( $redirect )->project === $project ) {
			$text = $this->contentHandlerFactory->getContentHandler( $title->getContentModel() )
				->makeRedirectContent( $redirect )?->serialize() ?? $text;
			return;
		}

		$source = Title::newFromText( $context->getRequest()->getText( self::PARAM ) );
		if (
			$source === null
			|| ProjectTitle::newFromLinkTarget( $source )->project !== null
			|| !$context->getAuthority()->probablyCan( 'read', $source )
		) {
			return;
		}

		$content = $this->revisionLookup->getRevisionByTitle( $source )?->getContent( SlotRecord::MAIN );
		if ( $content instanceof TextContent ) {
			$text = $content->getText();
		}
	}
}
