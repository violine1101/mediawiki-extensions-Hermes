<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Hermes\LanguageLink;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Language\LanguageFactory;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\Skin\Hook\SidebarBeforeOutputHook;
use MediaWiki\Skin\Skin;

/**
 * Adds Hermes links to the skin's "In other languages" box, where no manual [[xx:Foo]] link exists
 * for that language. Links to translation projects get the ext-hermes-translation-project-link class.
 * Disabled by $wgHermesLanguageLinksInSidebar.
 */
class LanguageLinkHooks implements SidebarBeforeOutputHook {

	/** Without locale-specific ucfirst overrides, like core (T294695) */
	private readonly Language $caseLanguage;

	public function __construct(
		private readonly Config $config,
		private readonly LanguageNameUtils $languageNameUtils,
		LanguageFactory $languageFactory,
	) {
		$this->caseLanguage = $languageFactory->getLanguage( 'en' );
	}

	/** @inheritDoc */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$title = $skin->getTitle();
		if (
			!isset( $sidebar['LANGUAGES'] )
			|| !$this->config->get( 'HermesLanguageLinksInSidebar' )
			|| !$skin->getOutput()->isArticle()
			|| !$title->canExist()
			|| !$title->exists()
		) {
			return;
		}

		$existingLangs = array_column( $sidebar['LANGUAGES'], 'lang' );
		foreach ( LanguageLink::forPage( PageInfo::fromLocalPage( $title ) ) as $link ) {
			$item = $this->makeItem( $link, $skin );
			if ( !in_array( $item['lang'], $existingLangs, true ) ) {
				$sidebar['LANGUAGES'][] = $item;
			}
		}

		usort( $sidebar['LANGUAGES'], static fn ( $a, $b ) => strcmp( $a['lang'] ?? '', $b['lang'] ?? '' ) );
	}

	/** An item in the format of Skin::getLanguages(). */
	private function makeItem( LanguageLink $link, Skin $skin ): array {
		$code = $link->getLanguageCode();
		$bcp47 = LanguageCode::bcp47( $code );
		$autonym = $this->languageNameUtils->getLanguageName( $code );
		$localName = $this->languageNameUtils->getLanguageName( $code, $skin->getLanguage()->getCode() );
		$title = $link->getDisplayTitle();
		$class = "interlanguage-link interwiki-$code";
		if ( $link->isProject() ) {
			$class .= ' ext-hermes-translation-project-link';
		}

		return [
			'href' => $link->url,
			'text' => $autonym !== '' ? $this->caseLanguage->ucfirst( $autonym ) : $code,
			'title' => $skin->msg( 'interlanguage-link-title', $title, $localName ?: $code )->text(),
			'class' => $class,
			'link-class' => 'interlanguage-link-target',
			'lang' => $bcp47,
			'hreflang' => $bcp47,
			'data-title' => $title,
			'data-language-autonym' => $autonym,
			'data-language-local-name' => $localName,
		];
	}
}
