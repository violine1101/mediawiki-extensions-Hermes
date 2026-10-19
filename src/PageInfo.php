<?php

namespace MediaWiki\Extension\Hermes;

use LogicException;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\WikiMap\WikiMap;

class PageInfo {

	/** @var string The ID of this page's wiki */
	public string $wiki;

	/** @var ?int The ID of this page; looked up on first use for local pages. */
	private ?int $id = null;

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

	public static function fromLocalPage( LinkTarget|PageReference $page ): PageInfo {
		$title = $page instanceof PageReference
			? Title::newFromPageReference( $page )
			: Title::newFromLinkTarget( $page );
		$title->assertWiki( PageReference::LOCAL );

		$self = new PageInfo();

		$self->wiki = WikiMap::getCurrentWikiId();

		$self->namespace = $title->getNamespace();
		$self->namespaceText = $title->getNsText();

		// "!xx:" titles in these namespaces aren't projects; hasInvalidTitle() rejects them.
		[ $self->translationProject, $self->title ] = Hermes::isProjectNamespace( $self->namespace )
			? self::parseTitle( $title->getText() )
			: [ null, strtr( $title->getText(), ' ', '_' ) ];

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

			$fragment = $parsedTitle->getFragment();
			$self->section = $fragment !== '' ? strtr( $fragment, ' ', '_' ) : null;

			$self->title = $parsedTitle->getDBkey();
		} catch ( MalformedTitleException ) {
			$self->title = strtr( $title, ' ', '_' );
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
		$self->id = (int)$row->ht_page_id;
		$self->namespace = (int)$row->ht_namespace_id;
		$self->namespaceText = $row->ht_namespace_text;
		$self->translationProject = $row->ht_translation_project;
		$self->title = $row->ht_base_title;
		$self->section = $row->ht_section;

		return $self;
	}

	/**
	 * Extracts a title's translation project prefix.
	 *
	 * @param string $baseTitle The title without namespace, already normalized by core
	 *   (e.g. from Title::getText() or getDBkey())
	 * @return array{0: ?string, 1: string} A tuple of (lang code, title in DB key form).
	 * 	 If the lang code is null, this article is not part of a translation project,
	 *   either because it doesn't have a "!xx:" prefix, or because the corresponding language
	 *   is not registered as a project language.
	 */
	public static function parseTitle( string $baseTitle ): array {
		$baseTitle = strtr( $baseTitle, ' ', '_' );

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
	 * Gets the language code of this page's translation project or wiki.
	 * Falls back to this wiki's content language if the page's wiki has no registered base language.
	 *
	 * @return string
	 */
	public function getLanguageCode(): string {
		return $this->translationProject
			?? LanguageStore::getBaseLanguageForWiki( $this->wiki )
			?? LanguageStore::getLocalBaseLanguage();
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
	 * Page titles looking like "!...:" must be in the format "!xx:",
	 * where "xx" is a registered translation project.
	 *
	 * @return bool True if the title format is invalid.
	 */
	public function hasInvalidTitle(): bool {
		return $this->translationProject === null && self::isReservedTitle( $this->title );
	}

	/** The title a "!:Foo" escape to the base page refers to ("Foo"), or null if $title isn't one. */
	public static function unescapeTitle( string $title ): ?string {
		return str_starts_with( $title, '!:' ) ? substr( $title, 2 ) : null;
	}

	/** Prefixes $title (without namespace) with "!xx:", capitalizing it as $wgCapitalLinks demands. */
	public static function addProjectPrefix( string $title, int $namespace, string $project ): string {
		// Core's capitalization only sees the leading "!", so it has to happen here.
		return "!{$project}:" . Title::capitalize( $title, $namespace );
	}

	/** Whether $title (without namespace) has the "!...:" shape reserved for translation projects. */
	public static function isReservedTitle( string $title ): bool {
		return (bool)preg_match( '/^!.*:/', $title );
	}

	/**
	 * Checks if this page's title violates $wgCapitalLinks or $wgCapitalLinkOverrides,
	 * ignoring the "!xx:" translation project prefix.
	 *
	 * @return bool True if the title requires but doesn't have a capitalized first letter.
	 */
	public function hasCapitalizationError(): bool {
		if ( $this->translationProject === null ) {
			return false;
		}

		return Title::capitalize( $this->title, $this->namespace ) !== $this->title;
	}

	/** The message key of the reason why this page can't be created or moved to, if any. */
	public function getTitleError(): ?string {
		if ( $this->hasInvalidTitle() ) {
			return 'hermes-invalid-project-title';
		}

		return $this->hasCapitalizationError() ? 'hermes-title-not-capitalized' : null;
	}

	/**
	 * The namespace name in the project's language (gender-specific for user pages), or the
	 * wiki-local one if this isn't a project page. Empty for the main namespace.
	 */
	public function getLocalizedNamespaceText(): string {
		if ( $this->translationProject === null || $this->namespace === NS_MAIN ) {
			return $this->namespaceText;
		}

		$lang = $this->getLanguage();
		if ( ( $this->namespace === NS_USER || $this->namespace === NS_USER_TALK ) && $lang->needsGenderDistinction() ) {
			$userName = strtok( $this->title, '/' );
			$gender = MediaWikiServices::getInstance()->getGenderCache()->getGenderOf( $userName, __METHOD__ );
			$nsText = $lang->getGenderNsText( $this->namespace, $gender );
		} else {
			$nsText = $lang->getFormattedNsText( $this->namespace );
		}

		// Empty for namespaces unknown here, e.g. another wiki's custom namespace.
		return $nsText ?: $this->namespaceText;
	}

	/**
	 * Builds the page's title as shown to readers: without any translation prefix, with the
	 * namespace in the project's language, and with spaces instead of underscores.
	 *
	 * @param bool $withNamespace Whether to include the namespace (like Title::getPrefixedText()),
	 *   or not (like Title::getText()).
	 */
	public function getDisplayTitle( bool $withNamespace = false ): string {
		$nsText = $withNamespace ? $this->getLocalizedNamespaceText() : '';
		return strtr( $this->joinTitle( $nsText ), '_', ' ' );
	}

	/**
	 * Builds the page's title without any translation prefix, i.e. the title used internally
	 * in a translation project, in DB key form.
	 * If the page is not in a translation project, this is identical to the full page title.
	 */
	public function getVirtualTitle(): string {
		return strtr( $this->joinTitle( $this->namespaceText ), ' ', '_' );
	}

	/**
	 * Builds the page's real, physical title as it's actually stored on its own wiki:
	 * namespace, "!xx:" translation project prefix, and "#section", all included.
	 */
	public function getRealTitle(): string {
		$project = $this->translationProject !== null ? "!{$this->translationProject}:" : '';
		return strtr( $this->joinTitle( $this->namespaceText, $project ), ' ', '_' );
	}

	/** "ns:", $projectPrefix, the title and "#section", with empty parts left out. */
	private function joinTitle( string $nsText, string $projectPrefix = '' ): string {
		$prefix = $nsText !== '' ? "{$nsText}:" : '';
		$suffix = $this->section !== null ? "#{$this->section}" : '';
		return "{$prefix}{$projectPrefix}{$this->title}{$suffix}";
	}

	/**
	 * This page's local title, capitalized correctly even for a translation project page.
	 */
	public function toTitle(): Title {
		$title = $this->translationProject !== null
			? self::addProjectPrefix( $this->title, $this->namespace, $this->translationProject )
			: $this->title;

		return Title::makeTitle( $this->namespace, $title, $this->section ?? '' );
	}

	/** A copy of this page in $project (or the base page if null). */
	public function withProject( ?string $project ): PageInfo {
		$copy = clone $this;
		$copy->translationProject = $project;
		$copy->id = null;
		return $copy;
	}

	/** Whether this page is on the current wiki. */
	public function isLocal(): bool {
		return $this->wiki === WikiMap::getCurrentWikiId();
	}

	/** The page ID, or 0 if the page doesn't exist. Only known for local pages and database rows. */
	public function getId(): int {
		if ( $this->id === null ) {
			if ( !$this->isLocal() ) {
				throw new LogicException( "Unknown page ID of {$this->getRealTitle()} on {$this->wiki}" );
			}

			// The stored title, which may not be correctly capitalized.
			$project = $this->translationProject !== null ? "!{$this->translationProject}:" : '';
			$this->id = Title::makeTitle( $this->namespace, $project . $this->title )->getArticleID();
		}

		return $this->id;
	}

	public function setId( int $id ): void {
		$this->id = $id;
	}

	/**
	 * Builds this page's interlanguage link.
	 */
	public function getInterlanguageLink(): string {
		return "{$this->getLanguageCode()}:{$this->getVirtualTitle()}";
	}

	/**
	 * Builds an HTML interlanguage link to the page.
	 * Falls back to plain (escaped) text if that doesn't resolve to a valid title.
	 */
	public function buildLink(): string {
		$target = Title::newFromText( $this->getInterlanguageLink() );
		if ( $target === null ) {
			return htmlspecialchars( $this->getDisplayTitle( true ) );
		}

		return MediaWikiServices::getInstance()->getLinkRenderer()
			->makeLink( $target, $this->getDisplayTitle( true ) );
	}
}
