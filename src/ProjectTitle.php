<?php

namespace MediaWiki\Extension\Hermes;

use LogicException;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * A title on some wiki in the family, split into namespace, translation project, base title and
 * section, e.g. "Category:!eo:Foo" -> (NS_CATEGORY, 'eo', 'Foo'), with its display forms and
 * language. toTitle(), toStoredTitle() and hasCapitalizationError() only work for local titles.
 *
 * The static methods handle the title syntax itself: "!xx:Foo" for project pages, and "!:Foo"
 * as an escape to the base page "Foo".
 */
final class ProjectTitle {

	private function __construct(
		/** ID of the title's wiki, or null if unknown */
		public readonly ?string $wiki,
		public readonly int $namespace,
		/** DB key without namespace and "!xx:" prefix */
		public readonly string $baseTitle,
		/** Translation project language code, or null for a base page */
		public readonly ?string $project,
		/** Section in DB key form, if any */
		public readonly ?string $section,
		/** Namespace name on the title's own wiki; looked up on first use for local titles */
		private ?string $namespaceText,
	) {
	}

	/** A title on this wiki. */
	public static function makeTitle(
		int $namespace,
		string $baseTitle,
		?string $project = null,
		?string $section = null,
		?string $namespaceText = null
	): self {
		return new self( WikiMap::getCurrentWikiId(), $namespace, $baseTitle, $project, $section, $namespaceText );
	}

	/** A title on $wiki (null if unknown), whose namespace name can't be looked up here. */
	public static function makeTitleOnWiki(
		?string $wiki,
		int $namespace,
		string $baseTitle,
		?string $project,
		?string $section,
		string $namespaceText
	): self {
		return new self( $wiki, $namespace, $baseTitle, $project, $section, $namespaceText );
	}

	/**
	 * Parses a local title, ignoring its fragment. "!xx:" prefixes are ignored in
	 * Hermes::NON_PROJECT_NAMESPACES and on interwiki titles, since they only mean something on
	 * this wiki.
	 */
	public static function newFromLinkTarget( LinkTarget|PageReference $page ): self {
		if ( $page instanceof PageReference ) {
			$page->assertWiki( PageReference::LOCAL );
		}

		$namespace = $page->getNamespace();
		$isExternal = $page instanceof LinkTarget && $page->isExternal();
		[ $project, $baseTitle ] = !$isExternal && Hermes::isProjectNamespace( $namespace )
			? self::parse( $page->getDBkey() )
			: [ null, $page->getDBkey() ];
		return new self( WikiMap::getCurrentWikiId(), $namespace, $baseTitle, $project, null, null );
	}

	/** This title in $project, or the base page if null. */
	public function withProject( ?string $project ): self {
		return new self(
			$this->wiki, $this->namespace, $this->baseTitle, $project, $this->section, $this->namespaceText
		);
	}

	/** This title's section $section, or the page as a whole if null. */
	public function withSection( ?string $section ): self {
		return new self(
			$this->wiki, $this->namespace, $this->baseTitle, $this->project, $section, $this->namespaceText
		);
	}

	public function isLocal(): bool {
		return $this->wiki === WikiMap::getCurrentWikiId();
	}

	/** The project's language code, else the wiki's base language, else this wiki's. */
	public function getLanguageCode(): string {
		return $this->project
			?? ( $this->wiki !== null ? LanguageStore::getBaseLanguageForWiki( $this->wiki ) : null )
			?? LanguageStore::getLocalBaseLanguage();
	}

	public function getLanguage(): Language {
		return MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( $this->getLanguageCode() );
	}

	/** The title, capitalized correctly even after a "!xx:" prefix. */
	public function toTitle(): Title {
		$this->assertLocal();
		$dbKey = $this->project !== null
			? self::addPrefix( $this->baseTitle, $this->namespace, $this->project )
			: $this->baseTitle;
		return Title::makeTitle( $this->namespace, $dbKey, $this->section ?? '' );
	}

	/** The title exactly as given, which may not be correctly capitalized. */
	public function toStoredTitle(): Title {
		$this->assertLocal();
		return Title::makeTitle( $this->namespace, $this->getProjectPrefix() . $this->baseTitle, $this->section ?? '' );
	}

	/**
	 * Whether this is in an invalid Hermes title format: titles looking like "!...:" must be
	 * "!xx:", where "xx" is a registered translation project.
	 */
	public function hasInvalidTitle(): bool {
		return $this->project === null && self::isReserved( $this->baseTitle );
	}

	/** Whether $wgCapitalLinks requires a capital letter after the "!xx:" prefix that's missing. */
	public function hasCapitalizationError(): bool {
		$this->assertLocal();
		return $this->project !== null
			&& Title::capitalize( $this->baseTitle, $this->namespace ) !== $this->baseTitle;
	}

	/** The message key of the reason why this title can't be created or moved to, if any. */
	public function getError(): ?string {
		if ( $this->hasInvalidTitle() ) {
			return 'hermes-invalid-project-title';
		}

		return $this->hasCapitalizationError() ? 'hermes-title-not-capitalized' : null;
	}

	/** The namespace name on the title's own wiki, or '' for the main namespace. */
	public function getNamespaceText(): string {
		$this->namespaceText ??= $this->toStoredTitle()->getNsText();
		return $this->namespaceText;
	}

	/**
	 * The namespace name in the project's language (gender-specific for user pages), or the
	 * one on the title's own wiki if this isn't a project page. Empty for the main namespace.
	 */
	public function getLocalizedNamespaceText(): string {
		if ( $this->project === null || $this->namespace === NS_MAIN ) {
			return $this->getNamespaceText();
		}

		$services = MediaWikiServices::getInstance();
		$lang = $services->getLanguageFactory()->getLanguage( $this->project );
		$isUserNamespace = $this->namespace === NS_USER || $this->namespace === NS_USER_TALK;
		if ( $isUserNamespace && $lang->needsGenderDistinction() ) {
			$userName = strtok( $this->baseTitle, '/' );
			$gender = $services->getGenderCache()->getGenderOf( $userName, __METHOD__ );
			$nsText = $lang->getGenderNsText( $this->namespace, $gender );
		} else {
			$nsText = $lang->getFormattedNsText( $this->namespace );
		}

		// Empty for namespaces unknown here, e.g. another wiki's custom namespace.
		return $nsText ?: $this->getNamespaceText();
	}

	/**
	 * The title as shown to readers: without any translation prefix, with the namespace in the
	 * project's language, and with spaces instead of underscores.
	 *
	 * @param bool $withNamespace Whether to include the namespace (like Title::getPrefixedText()),
	 *   or not (like Title::getText()).
	 */
	public function getDisplayTitle( bool $withNamespace = false ): string {
		$nsText = $withNamespace ? $this->getLocalizedNamespaceText() : '';
		return strtr( $this->joinTitle( $nsText ), '_', ' ' );
	}

	/**
	 * The real, physical title as it's actually stored on its own wiki: namespace, "!xx:"
	 * translation project prefix, and "#section", all included.
	 */
	public function getRealTitle(): string {
		return strtr( $this->joinTitle( $this->getNamespaceText(), $this->getProjectPrefix() ), ' ', '_' );
	}

	/**
	 * The URL of this title on its own wiki, as registered in WikiStore (so possibly
	 * protocol-relative), or null if that wiki has none.
	 */
	public function getUrl(): ?string {
		$template = $this->wiki !== null ? WikiStore::getUrlTemplate( $this->wiki ) : null;
		if ( $template === null ) {
			return null;
		}

		$path = strtr( $this->joinTitle( $this->getNamespaceText(), $this->getProjectPrefix(), false ), ' ', '_' );
		$fragment = $this->section !== null ? '#' . Sanitizer::escapeIdForLink( $this->section ) : '';
		return str_replace( '$1', wfUrlencode( $path ), $template ) . $fragment;
	}

	private function assertLocal(): void {
		if ( !$this->isLocal() ) {
			$wiki = $this->wiki ?? 'an unknown wiki';
			throw new LogicException( "{$this->getRealTitle()} is on {$wiki}, not on this wiki" );
		}
	}

	private function getProjectPrefix(): string {
		return $this->project !== null ? "!{$this->project}:" : '';
	}

	/** "ns:", $projectPrefix, the base title and "#section", with empty parts left out. */
	private function joinTitle( string $nsText, string $projectPrefix = '', bool $withSection = true ): string {
		$prefix = $nsText !== '' ? "{$nsText}:" : '';
		$suffix = $withSection && $this->section !== null ? "#{$this->section}" : '';
		return "{$prefix}{$projectPrefix}{$this->baseTitle}{$suffix}";
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
	public static function parse( string $baseTitle ): array {
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

	/** The title a "!:Foo" escape to the base page refers to ("Foo"), or null if $title isn't one. */
	public static function unescape( string $title ): ?string {
		return str_starts_with( $title, '!:' ) ? substr( $title, 2 ) : null;
	}

	/** Prefixes $title (without namespace) with "!xx:", capitalizing it as $wgCapitalLinks demands. */
	public static function addPrefix( string $title, int $namespace, string $project ): string {
		// Core's capitalization only sees the leading "!", so it has to happen here.
		return "!{$project}:" . Title::capitalize( $title, $namespace );
	}

	/** Whether $title (without namespace) has the "!...:" shape reserved for translation projects. */
	public static function isReserved( string $title ): bool {
		return (bool)preg_match( '/^!.*:/', $title );
	}
}
