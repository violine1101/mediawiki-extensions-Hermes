<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

class PageInfo {

	/** @var string The ID of this page's wiki */
	public string $wiki;

	/** @var int The ID of this page */
	public int $id;

	/** @var string The full title of the page.
	 *    Includes namespace and translation project prefix, but not section
	 */
	public string $fullTitle;

	/** @var ?int The page's namespace ID. Unavailable for database entries. */
	public ?int $namespace = null;

	/** @var ?string The translation project language code, or null if this isn't a project page.
	 *    Unavailable for database entries.
	 */
	public ?string $translationProject = null;

	/** @var string Language code of the page's translation project or wiki. */
	public string $language;

	/** @var ?string The title without namespace and translation project prefix.
	 *    Unavailable for database entries.
	 */
	public ?string $title = null;

	/** @var ?string Page section, if any */
	public ?string $section = null;

	public static function fromLocalPage( PageReference $page ): PageInfo {
		$self = new PageInfo();
		$title = Title::newFromPageReference( $page );

		$self->wiki = WikiMap::getCurrentWikiId();
		$title->assertWiki( PageReference::LOCAL );

		$self->namespace = $title->getNamespace();

		$baseTitle = $title->getText();
		[ $self->translationProject, $self->title ] = self::parseTitle( $baseTitle );

		$self->language = $self->translationProject ?? LanguageStore::getLocalBaseLanguage();

		$self->id = $title->getArticleID();
		$self->fullTitle = $title->getPrefixedDBkey();

		return $self;
	}

	/**
	 * Creates a PageInfo from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return PageInfo The resulting page info object.
	 */
	public static function fromRow( $row ): PageInfo {
		$self = new PageInfo();

		$self->wiki = $row->ht_wiki;
		$self->id = $row->ht_page_id;
		$self->fullTitle = $row->ht_page_title;
		$self->language = $row->ht_language;
		$self->section = $row->ht_section;

		// FIXME: This only works properly for pages without a namespace.
		[ $self->translationProject, $self->title ] = self::parseTitle( $self->fullTitle );
		// TODO: Populate the remaining properties.
		// May require more columns to be added to the table (e.g. namespace ID + text)

		return $self;
	}

	/**
	 * Extracts the translation project prefix from a title (without namespace).
	 *
	 * @param string $baseTitle The title to be parsed, without namespace
	 * @return array{0: ?string, 1: string} A tuple of (lang code, title).
	 * 	 If the lang code is null, this article is not part of a translation project,
	 *   either because it doesn't have a "!xx:" prefix, or because the corresponding language
	 *   is not registered as a project language.
	 */
	public static function parseTitle( string $baseTitle ): array {
		if ( preg_match( '/^!([a-z0-9-]+):(.+)$/', $baseTitle, $match ) ) {
			$lang = $match[ 1 ];
			$title = $match[ 2 ];
			if ( LanguageStore::isProjectLanguage( $lang ) ) {
				return [ $lang, $title ];
			}
		}

		return [ null, $baseTitle ];
	}

	/**
	 * Gets the Language object for this page's language.
	 *
	 * @return Language
	 */
	public function getLanguage(): Language {
		return MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( $this->language );
	}

	/**
	 * Checks if the title of this page is in an invalid Hermes title format:
	 * Page titles starting with "!" must be in the format "!xx:",
	 * where "xx" is a registered translation project.
	 *
	 * @return bool True if the title format is invalid.
	 */
	public function hasInvalidTitle(): bool {
		return $this->translationProject === null
			&& $this->title !== null
			&& str_starts_with( $this->title, '!' );
	}

	/**
	 * Builds a link to the page, using the language codes for interlanguage links.
	 * Falls back to plain (escaped) text if that doesn't resolve to a valid title.
	 */
	public function buildLink(): string {
		$target = Title::newFromText( "{$this->language}:{$this->fullTitle}" );
		if ( $target === null ) {
			return htmlspecialchars( $this->fullTitle );
		}

		return Html::element( 'a', [ 'href' => $target->getFullURL() ], $this->title );
	}
}
