<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Html\Html;
use MediaWiki\Title\Title;

/**
 * Special:HermesTranslate/<page>?language=xx: a form for translating a base page into one of this wiki's
 * translation projects, which opens the editor for "!xx:<translated title>" starting with the base page's
 * wikitext (see TranslateHooks).
 */
class SpecialHermesTranslate extends HermesSpecialPage {

	public function __construct() {
		parent::__construct( 'HermesTranslate' );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		return false;
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$pageName = trim( $request->getText( 'page', $par ) );
		$language = $request->getVal( 'language' );
		$translation = $request->getVal( 'translation' );
		$languages = array_keys( ProjectStore::getActiveLocalTranslationProjects() );

		$page = null;
		if ( $pageName !== '' ) {
			$page = $this->getBasePage( $pageName );
			if ( $page === null ) {
				$out->addHTML( Html::errorBox( $this->msg( 'hermestranslate-no-base', $pageName )->parse() ) );
			} else {
				// "!xx:Foo" picks the project too.
				$project = ProjectTitle::newFromLinkTarget( Title::newFromText( $pageName ) )->project;
				$language ??= $project->isBase ? null : $project->code;
				$pageName = $page->toTitle()->getPrefixedText();
				$out->setPageTitleMsg( $this->msg( 'hermestranslate-title' )->plaintextParams( $pageName ) );
			}
		}

		// Only projects that don't have the page yet.
		$available = $page !== null ? self::getUntranslatedProjects( $page ) : $languages;
		if ( $page !== null && $translation !== null && in_array( $language, $available, true ) ) {
			$target = $this->getTarget( $page, $translation, $language );
			if ( $target === null ) {
				$out->addHTML( Html::errorBox(
					$this->msg( 'hermestranslate-invalid-title', $translation )->parse()
				) );
			} elseif ( $target->exists() ) {
				$out->addHTML( Html::errorBox( $this->msg( 'hermestranslate-exists' )
					->rawParams( $this->getLinkRenderer()->makeKnownLink( $target ) )->parse()
				) );
			} else {
				$out->redirect( $target->getFullURL( [
					'action' => 'edit',
					TranslateHooks::PARAM => $pageName,
				] ) );
				return;
			}
		}

		if ( !$languages ) {
			$out->addWikiMsg( 'hermestranslate-no-projects' );
			return;
		}
		if ( $page !== null && !$available ) {
			$out->addHTML( Html::noticeBox( $this->msg( 'hermestranslate-all-translated', $pageName )->parse() ) );
		}
		$options = [ $this->msg( 'hermestranslate-language-placeholder' )->text() => '' ];
		foreach ( $available as $code ) {
			$options[ $this->getFormatter()->languageLabel( $code ) ] = $code;
		}
		$this->showForm( [
			// A sub-page names the page already.
			'page' => $page !== null && $par !== '' ? [
				'type' => 'hidden',
				'name' => 'page',
				'default' => $pageName,
			] : [
				'type' => 'title',
				'name' => 'page',
				'label-message' => 'hermestranslate-page',
				'default' => $pageName,
				'required' => true,
			],
			'language' => [
				'type' => 'select',
				'name' => 'language',
				'label-message' => 'hermestranslate-language',
				'options' => $options,
				'default' => in_array( $language, $available, true ) ? $language : '',
				'required' => true,
			],
			'translation' => [
				'type' => 'text',
				'name' => 'translation',
				'label-message' => 'hermestranslate-translation',
				'default' => $translation ?? $page?->getDisplayTitle() ?? '',
				'required' => true,
			],
		], null, 'hermestranslate-submit' );
	}

	/**
	 * This wiki's active translation projects without a translation of $page: neither one linked by its main tag
	 * (TagStore::getTranslationsOfPage()), nor an untagged page with the same title.
	 *
	 * @return string[]
	 */
	public static function getUntranslatedProjects( ProjectTitle $page ): array {
		$projects = ProjectStore::getActiveLocalTranslationProjects();
		$translated = $projects ? TagStore::getTranslationsOfPage( PageInfo::fromLocalPage( $page->toTitle() ) ) : [];
		return array_keys( array_filter( $projects, static fn ( Project $project ) =>
			!isset( $translated[ $project->code ] ) && !$page->withProject( $project )->toTitle()->exists()
		) );
	}

	/** The existing base page of $pageName (which may have a "!xx:" prefix), if it can have translations. */
	private function getBasePage( string $pageName ): ?ProjectTitle {
		$title = Title::newFromText( $pageName );
		if ( $title === null || $title->isExternal() || !Hermes::isProjectNamespace( $title->getNamespace() ) ) {
			return null;
		}
		$page = ProjectTitle::newFromLinkTarget( $title )->toBasePage();
		return $page->toTitle()->exists() ? $page : null;
	}

	/** "!xx:<translation>" in $page's namespace, or null if that's not a valid title. */
	private function getTarget( ProjectTitle $page, string $translation, string $language ): ?Title {
		$translation = strtr( trim( $translation ), ' ', '_' );
		if ( $translation === '' || ProjectTitle::isReserved( $translation ) ) {
			return null;
		}
		$project = ProjectStore::getActiveLocalTranslationProjects()[ $language ];
		return Title::makeTitleSafe( $page->namespace, $project->addPrefix( $translation, $page->namespace ) );
	}
}
