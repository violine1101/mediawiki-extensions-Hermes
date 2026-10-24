<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;
use OOUI\ActionFieldLayout;
use OOUI\ButtonInputWidget;
use OOUI\DropdownInputWidget;

/**
 * Special:HermesLanguages: the wikis of the family with their languages (marking inactive ones), and how many
 * pages with Hermes tags each language has there. Users with the hermes-add-translation-project right can add
 * translation projects to this wiki.
 */
class SpecialHermesLanguages extends HermesSpecialPage {

	private const RIGHT = 'hermes-add-translation-project';

	public function __construct( private readonly LanguageNameUtils $languageNameUtils ) {
		parent::__construct( 'HermesLanguages' );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		return false;
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		$canEdit = $this->getAuthority()->isAllowed( self::RIGHT );
		if ( $canEdit ) {
			$this->getOutput()->enableOOUI();
			$this->handleAdd();
		}
		$this->getOutput()->addHTML( $this->languagesTable( $canEdit ) );
	}

	/**
	 * One row per language, grouped by wiki: its base language first (in bold), then its translation projects,
	 * and for this wiki the form adding one.
	 */
	private function languagesTable( bool $canEdit ): string {
		$formatter = $this->getFormatter();
		$counts = TagQuery::countPages( Hermes::getDB() );
		$byWiki = [];
		foreach ( LanguageStore::getLanguages() as $code => $registrations ) {
			foreach ( $registrations as $wiki => $language ) {
				$byWiki[ $wiki ][ $language->isBase ? '' : $code ] = $language;
			}
		}
		ksort( $byWiki );

		$headers = array_map( fn ( string $msg ) => Html::element( 'th', [], $this->msg( $msg )->text() ), [
			'hermeslanguages-header-wiki',
			'hermeslanguages-header-language',
			'hermeslanguages-header-pages',
		] );
		$rows = [];
		foreach ( $byWiki as $wiki => $languages ) {
			ksort( $languages );
			$addRow = $canEdit && $wiki === WikiMap::getCurrentWikiId();
			$wikiCell = Html::rawElement( 'th',
				[ 'scope' => 'rowgroup', 'rowspan' => count( $languages ) + (int)$addRow ],
				$this->wikiLink( $wiki )
			);
			foreach ( $languages as $key => $language ) {
				$code = $language->code;
				$label = $formatter->languageLabel( $code );
				if ( !$language->isActive ) {
					$label .= ' ' . $this->msg( 'hermeslanguages-inactive' )->text();
				}
				$count = $this->getLanguage()->formatNum( $counts[ $wiki ][ $code ] ?? 0 );
				$rows[] = Html::rawElement( 'tr', [ 'class' => $key === '' ? 'ext-hermes-base-language' : null ],
					$wikiCell .
					Html::element( 'td', [], $label ) .
					// Special:HermesPages only lists active pages.
					Html::rawElement( 'td', [], $language->isActive
						? $this->getLinkRenderer()->makeKnownLink(
							SpecialPage::getTitleFor( 'HermesPages', $code ), $count
						)
						: htmlspecialchars( $count )
					)
				);
				$wikiCell = '';
			}
			if ( $addRow ) {
				$rows[] = Html::rawElement( 'tr', [], Html::rawElement( 'td', [ 'colspan' => 2 ], $this->addForm() ) );
			}
		}
		return $formatter->table( $headers, $rows );
	}

	/** A link to the wiki's main page, or just its ID if it has no registered URL. */
	private function wikiLink( string $wiki ): string {
		$url = LanguageStore::getUrlTemplate( $wiki );
		return $url !== null
			? Html::element( 'a', [ 'href' => str_replace( '$1', '', $url ), 'class' => 'extiw' ], $wiki )
			: htmlspecialchars( $wiki );
	}

	/** @return array<string, string> Languages MediaWiki supports that aren't registered yet, code => autonym */
	private function getAddableLanguages(): array {
		$codes = array_keys( array_diff_key(
			$this->languageNameUtils->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::SUPPORTED ),
			LanguageStore::getLanguages()
		) );
		sort( $codes );
		return array_combine( $codes, array_map( Hermes::getAutonym( ... ), $codes ) );
	}

	private function addForm(): string {
		$options = [ [ 'data' => '', 'label' => $this->msg( 'hermeslanguages-add-placeholder' )->text() ] ];
		foreach ( $this->getAddableLanguages() as $code => $name ) {
			$options[] = [ 'data' => $code, 'label' => "$name ($code)" ];
		}
		return Html::rawElement( 'form', [ 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL() ],
			new ActionFieldLayout(
				new DropdownInputWidget( [ 'name' => 'add-language', 'options' => $options ] ),
				new ButtonInputWidget( [
					'type' => 'submit',
					'label' => $this->msg( 'hermeslanguages-add-submit' )->text(),
					'flags' => [ 'progressive' ],
				] ),
				[ 'label' => $this->msg( 'hermeslanguages-add-placeholder' )->text(), 'invisibleLabel' => true ]
			) .
			Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() )
		);
	}

	private function handleAdd(): void {
		$request = $this->getRequest();
		$language = $request->getVal( 'add-language' );
		if ( !$request->wasPosted() || ( $language ?? '' ) === '' ) {
			return;
		}

		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			$this->getOutput()->addHTML( Html::errorBox( $this->msg( 'sessionfailure' )->parse() ) );
			return;
		}
		$this->checkReadOnly();

		if ( isset( $this->getAddableLanguages()[ $language ] ) ) {
			try {
				LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), $language );
			} catch ( LanguageConflictException ) {
				// Impossible, because getAddableLanguages excludes conflicts
			}
		}
	}
}
