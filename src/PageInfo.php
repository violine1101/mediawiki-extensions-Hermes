<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Html\Html;
use MediaWiki\Language\Language;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\WikiMap\WikiMap;

class PageInfo {

	/** @var string The ID of this page's wiki */
	public string $wiki;

	/** @var int The ID of this page */
	public int $id;

	/** @var int The page's namespace ID. */
	public int $namespace = NS_MAIN;

	/** @var string The wiki-local namespace text (as returned by Title::getNsText()), or ''
	 *    for the main namespace. Not translated for the remote wiki.
	 */
	public string $namespaceText = '';

	/** @var ?string The translation project language code, or null if this isn't a project page. */
	public ?string $translationProject = null;

	/** @var string The title without namespace and translation project prefix. */
	public string $title = '';

	/** @var ?string Page section, if any */
	public ?string $section = null;

	public static function fromLocalPage( PageReference $page ): PageInfo {
		$title = Title::newFromPageReference( $page );
		$title->assertWiki( PageReference::LOCAL );

		$self = new PageInfo();

		$self->wiki = WikiMap::getCurrentWikiId();
		$self->id = $title->getArticleID();

		$self->namespace = $title->getNamespace();
		$self->namespaceText = $title->getNsText();

		[ $self->translationProject, $self->title ] = self::parseTitle( $title->getText() );

		return $self;
	}

	/**
	 * Creates a PageInfo for the target of an interwiki-prefixed title. For example,
	 * for an interwiki link "xx:Category:Foo", $prefix is "xx" and $title is "Category:Foo".
	 *
	 * $prefix determines both the translation project (if any) and which wiki's namespace naming
	 * to parse $title's namespace against: namespace names are locale-dependent, and $title can
	 * name a page on a wiki with a different language than the one currently running - one whose
	 * own base language, and so own namespace naming, can differ from this wiki's.
	 *
	 * @param string $prefix The interwiki prefix (language code)
	 * @param string $title The virtual page title
	 * @return PageInfo
	 */
	public static function fromInterwiki( string $prefix, string $title ): PageInfo {
		$entry = LanguageStore::getLanguageEntry( $prefix );
		$hostLanguageCode = $entry !== null ? LanguageStore::getBaseLanguageForWiki( $entry[ 'wiki' ] ) : null;

		$services = MediaWikiServices::getInstance();
		$language = $hostLanguageCode !== null
			? $services->getLanguageFactory()->getLanguage( $hostLanguageCode )
			: $services->getLanguageFactory()->getLanguage( 'en' );

		$parser = new TitleParser(
			$language,
			$services->getInterwikiLookup(),
			$services->getNamespaceInfo(),
			$services->getMainConfig()->get( MainConfigNames::LocalInterwikis )
		);

		$self = new PageInfo();

		try {
			$parsedTitle = $parser->parseTitle( $title );

			$self->namespace = $parsedTitle->getNamespace();
			$self->namespaceText = $language->getFormattedNsText( $self->namespace );

			$fragment = $parsedTitle->getFragment() !== '' ? $parsedTitle->getFragment() : null;
			$self->section = self::normalizeSection( $fragment );

			$self->title = self::normalizeTitle( $parsedTitle->getDBkey() );
		} catch ( MalformedTitleException ) {
			$self->title = self::normalizeTitle( $title );
		}

		if ( $entry !== null && !$entry[ 'isBase' ] ) {
			$self->translationProject = $prefix;
		}

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
		$self->namespace = (int)$row->ht_namespace_id;
		$self->namespaceText = $row->ht_namespace_text;
		$self->translationProject = $row->ht_translation_project;
		$self->title = $row->ht_base_title;
		$self->section = $row->ht_section;

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
			if ( LanguageStore::isLocalProjectLanguage( $lang ) ) {
				return [ $lang, $title ];
			}
		}

		return [ null, $baseTitle ];
	}

	/**
	 * Normalizes a page title (without namespace or translation project prefix).
	 *
	 * @param string $title The non-normalized title text.
	 * @return string The normalized title: trimmed, whitespace/underscore runs collapsed to a
	 *   single space.
	 */
	private static function normalizeTitle( string $title ): string {
		return Sanitizer::normalizeSectionNameWhitespace( $title );
	}

	/**
	 * Normalizes a page's section value.
	 *
	 * @param ?string $section The non-normalized section, or null if there is none.
	 * @return ?string The normalized section: trimmed, whitespace/underscore runs collapsed to a
	 *   single space. Case is preserved, since HTML anchors are case-sensitive.
	 */
	private static function normalizeSection( ?string $section ): ?string {
		if ( $section === null ) {
			return null;
		}
		$section = Sanitizer::normalizeSectionNameWhitespace( $section );
		return $section === '' ? null : $section;
	}

	/**
	 * Gets the language code of this page's translation project or wiki.
	 *
	 * @return string
	 */
	public function getLanguageCode(): string {
		return $this->translationProject ?? LanguageStore::getBaseLanguageForWiki( $this->wiki );
	}

	/**
	 * Gets the Language object for this page's language.
	 *
	 * @return Language
	 */
	public function getLanguage(): Language {
		return MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( $this->getLanguageCode() );
	}

	/**
	 * Checks if the title of this page is in an invalid Hermes title format:
	 * Page titles starting with "!" must be in the format "!xx:",
	 * where "xx" is a registered translation project.
	 *
	 * @return bool True if the title format is invalid.
	 */
	public function hasInvalidTitle(): bool {
		return $this->translationProject === null && str_starts_with( $this->title, '!' );
	}

	/**
	 * Builds the page's title without any translation prefix, i.e. the title used internally
	 * in a translation project.
	 * If the page is not in a translation project, this is identical to the full page title.
	 */
	public function getVirtualTitle(): string {
		$prefix = $this->namespaceText !== '' ? "{$this->namespaceText}:" : '';
		$suffix = $this->section !== null ? "#{$this->section}" : '';
		return self::normalizeTitle( "{$prefix}{$this->title}{$suffix}" );
	}

	/**
	 * Builds the page's real, physical title as it's actually stored on its own wiki:
	 * namespace, "!xx:" translation project prefix, and "#section", all included.
	 */
	public function getRealTitle(): string {
		if ( $this->translationProject === null ) {
			return $this->getVirtualTitle();
		}

		$prefix = $this->namespaceText !== '' ? "{$this->namespaceText}:" : '';
		$suffix = $this->section !== null ? "#{$this->section}" : '';
		return self::normalizeTitle( "{$prefix}!{$this->translationProject}:{$this->title}{$suffix}" );
	}

	/**
	 * Builds this page's interlanguage link.
	 */
	public function getInterlanguageLink(): string {
		return "{$this->getLanguageCode()}:{$this->getVirtualTitle()}";
	}

	// TODO: replace buildLink() with native MediaWiki interwiki link rendering, if possible.

	/**
	 * Builds a raw HTML link to the page, as if it was an interlanguage link.
	 * Falls back to plain (escaped) text if that doesn't resolve to a valid title.
	 */
	public function buildLink(): string {
		$target = Title::newFromText( $this->getInterlanguageLink() );
		if ( $target === null ) {
			return htmlspecialchars( $this->getVirtualTitle() );
		}

		return Html::element( 'a', [ 'href' => $target->getFullURL() ], $this->getVirtualTitle() );
	}
}
