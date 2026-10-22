<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Title\Title;

/**
 * Special:HermesTranslate/<page>?language=xx: a form for translating a base page into one of this wiki's
 * translation projects, which opens the editor for "!xx:<translated title>" starting with the base page's
 * wikitext (see TranslateHooks).
 */
class SpecialHermesTranslate extends HermesSpecialPage {

	public function __construct( LanguageNameUtils $languageNameUtils ) {
		parent::__construct( 'HermesTranslate', $languageNameUtils );
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
		$projects = LanguageStore::getLocalProjectLanguages();

		$page = null;
		if ( $pageName !== '' ) {
			$page = $this->getBasePage( $pageName );
			if ( $page === null ) {
				$out->addHTML( Html::errorBox( $this->msg( 'hermestranslate-no-base', $pageName )->parse() ) );
			} else {
				// "!xx:Foo" picks the project too.
				$language ??= ProjectTitle::newFromLinkTarget( Title::newFromText( $pageName ) )->project;
				$pageName = $page->toTitle()->getPrefixedText();
				$out->setPageTitleMsg( $this->msg( 'hermestranslate-title' )->plaintextParams( $pageName ) );
			}
		}

		// Only projects that don't have the page yet.
		$available = $page !== null ? self::getUntranslatedProjects( $page ) : $projects;
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

		if ( !$projects ) {
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
	 * This wiki's translation projects without a translation of $page: neither one linked by its main tag
	 * (TagStore::getTranslationsOfPage()), nor an untagged page with the same title.
	 *
	 * @return string[]
	 */
	public static function getUntranslatedProjects( ProjectTitle $page ): array {
		$projects = LanguageStore::getLocalProjectLanguages();
		$translated = $projects ? TagStore::getTranslationsOfPage( PageInfo::fromLocalPage( $page->toTitle() ) ) : [];
		return array_values( array_filter( $projects, static fn ( string $project ) =>
			!isset( $translated[ $project ] ) && !$page->withProject( $project )->toTitle()->exists()
		) );
	}

	/** The existing base page of $pageName (which may have a "!xx:" prefix), if it can have translations. */
	private function getBasePage( string $pageName ): ?ProjectTitle {
		$title = Title::newFromText( $pageName );
		if ( $title === null || $title->isExternal() || !Hermes::isProjectNamespace( $title->getNamespace() ) ) {
			return null;
		}
		$page = ProjectTitle::newFromLinkTarget( $title )->withProject( null );
		return $page->toTitle()->exists() ? $page : null;
	}

	/** "!xx:<translation>" in $page's namespace, or null if that's not a valid title. */
	private function getTarget( ProjectTitle $page, string $translation, string $language ): ?Title {
		$translation = strtr( trim( $translation ), ' ', '_' );
		if ( $translation === '' || ProjectTitle::isReserved( $translation ) ) {
			return null;
		}
		return Title::makeTitleSafe(
			$page->namespace, ProjectTitle::addPrefix( $translation, $page->namespace, $language )
		);
	}
}
