<?php

namespace MediaWiki\Extension\Hermes;

use LogicException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;

/**
 * A title on some wiki in the family, split into project, namespace, base title and section, e.g.
 * "Category:!eo:Foo" -> (this wiki's "eo" project, NS_CATEGORY, 'Foo'), with its display forms. Its project gives
 * its wiki and language. toTitle() and hasCapitalizationError() only work for local titles.
 *
 * The static methods handle the title syntax itself: "!xx:Foo" for project pages, and "!:Foo"
 * as an escape to the base page "Foo".
 */
final class ProjectTitle {

	private function __construct(
		public readonly Project $project,
		public readonly int $namespace,
		/** Namespace name on the title's own wiki; looked up on first use for local titles */
		private ?string $namespaceText,
		/** DB key without namespace and "!xx:" prefix */
		public readonly string $baseTitle,
		/** Section in DB key form, if any */
		public readonly ?string $section,
	) {
	}

	/**
	 * @param Project $project Determines the title's wiki and language
	 * @param int $namespace
	 * @param string $baseTitle DB key without namespace and "!xx:" prefix
	 * @param string|null $section Section in DB key form, if any
	 * @param string|null $namespaceText Namespace name on the title's wiki; required unless it's this wiki
	 */
	public static function makeTitle(
		Project $project,
		int $namespace,
		string $baseTitle,
		?string $section = null,
		?string $namespaceText = null
	): self {
		return new self( $project, $namespace, $namespaceText, $baseTitle, $section );
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
		return new self( $project ?? ProjectStore::getLocalBaseProject(), $namespace, null, $baseTitle, null );
	}

	/** This title in $project, which must be on the same wiki. */
	public function withProject( Project $project ): self {
		return new self( $project, $this->namespace, $this->namespaceText, $this->baseTitle, $this->section );
	}

	/** This local title's base page. */
	public function toBasePage(): self {
		$this->assertLocal();
		return $this->project->isBase ? $this : $this->withProject( ProjectStore::getLocalBaseProject() );
	}

	/** This title's section $section, or the page as a whole if null. */
	public function withSection( ?string $section ): self {
		return new self( $this->project, $this->namespace, $this->namespaceText, $this->baseTitle, $section );
	}

	/** The title, capitalized correctly even after a "!xx:" prefix. */
	public function toTitle(): Title {
		$this->assertLocal();
		$dbKey = $this->baseTitle;
		if ( !$this->project->isBase ) {
			$dbKey = $this->project->getPrefix() . Title::capitalize( $this->baseTitle, $this->namespace );
		}
		return Title::makeTitle( $this->namespace, $dbKey, $this->section ?? '' );
	}

	/**
	 * Whether this is in an invalid Hermes title format: titles looking like "!...:" must be
	 * "!xx:", where "xx" is a registered translation project.
	 */
	public function isInvalid(): bool {
		return $this->project->isBase && self::isReserved( $this->baseTitle );
	}

	/** Whether $wgCapitalLinks requires a capital letter after the "!xx:" prefix that's missing. */
	public function hasCapitalizationError(): bool {
		$this->assertLocal();
		return !$this->project->isBase
			&& Title::capitalize( $this->baseTitle, $this->namespace ) !== $this->baseTitle;
	}

	/** The message key of the reason why this title can't be created or moved to, if any. */
	public function getErrorMessage(): ?string {
		if ( $this->isInvalid() ) {
			return 'hermes-invalid-project-title';
		}

		return $this->hasCapitalizationError() ? 'hermes-title-not-capitalized' : null;
	}

	/** The namespace name on the title's own wiki, or '' for the main namespace. */
	public function getNamespaceText(): string {
		$this->namespaceText ??= $this->toTitle()->getNsText();
		return $this->namespaceText;
	}

	/**
	 * The namespace name in the project's language (gender-specific for user pages), or the
	 * one on the title's own wiki if this isn't a project page. Empty for the main namespace.
	 */
	public function getLocalizedNamespaceText(): string {
		if ( $this->project->isBase || $this->namespace === NS_MAIN ) {
			return $this->getNamespaceText();
		}

		$services = MediaWikiServices::getInstance();
		$lang = $this->project->getLanguage();
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
		return strtr( $this->joinTitle( $this->getNamespaceText(), $this->project->getPrefix() ), ' ', '_' );
	}

	/**
	 * The URL of this title on its own wiki, from its project's URL template (so possibly
	 * protocol-relative), or null if that's unknown.
	 */
	public function getUrl(): ?string {
		$template = $this->project->urlTemplate;
		if ( $template === null ) {
			return null;
		}

		$path = strtr( $this->joinTitle( $this->getNamespaceText(), $this->project->getPrefix(), false ), ' ', '_' );
		$fragment = $this->section !== null ? '#' . Sanitizer::escapeIdForLink( $this->section ) : '';
		return str_replace( '$1', wfUrlencode( $path ), $template ) . $fragment;
	}

	private function assertLocal(): void {
		if ( !$this->project->isLocal() ) {
			throw new LogicException( "{$this->getRealTitle()} is on {$this->project->wiki}, not on this wiki" );
		}
	}

	/** "ns:", $projectPrefix, the base title and "#section", with empty parts left out. */
	private function joinTitle( string $nsText, string $projectPrefix = '', bool $withSection = true ): string {
		$prefix = $nsText !== '' ? "{$nsText}:" : '';
		$suffix = $withSection && $this->section !== null ? "#{$this->section}" : '';
		return "{$prefix}{$projectPrefix}{$this->baseTitle}{$suffix}";
	}

	/**
	 * Extracts a local title's translation project prefix.
	 *
	 * @param string $baseTitle The title without namespace, already normalized by core
	 *   (e.g. from Title::getText() or getDBkey())
	 * @return array{0: ?Project, 1: string} A tuple of (translation project, title in DB key form).
	 * 	 If the project is null, this article is not part of a translation project,
	 *   either because it doesn't have a "!xx:" prefix, or because this wiki has no translation project
	 *   in that language.
	 */
	public static function parse( string $baseTitle ): array {
		$baseTitle = strtr( $baseTitle, ' ', '_' );

		if ( preg_match( '/^!([a-z0-9-]+):(.+)$/', $baseTitle, $match ) ) {
			$project = ProjectStore::getProject( $match[ 1 ], WikiMap::getCurrentWikiId() );
			if ( $project !== null && !$project->isBase ) {
				return [ $project, $match[ 2 ] ];
			}
		}

		return [ null, $baseTitle ];
	}

	/** The title a "!:Foo" escape to the base page refers to ("Foo"), or null if $title isn't one. */
	public static function unescape( string $title ): ?string {
		return str_starts_with( $title, '!:' ) ? substr( $title, 2 ) : null;
	}

	/** Whether $title (without namespace) has the "!...:" shape reserved for translation projects. */
	public static function isReserved( string $title ): bool {
		return (bool)preg_match( '/^!.*:/', $title );
	}

	/**
	 * SQL condition for titles that aren't translation-project pages: not isReserved(), or in a namespace
	 * without projects.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $namespace Namespace field
	 * @param string $title Title field, without namespace
	 */
	public static function notProjectSqlCondition(
		IReadableDatabase $dbr, string $namespace, string $title
	): IExpression {
		$reserved = new LikeValue( '!', $dbr->anyString(), ':', $dbr->anyString() );
		return $dbr->expr( $title, IExpression::NOT_LIKE, $reserved )
			->or( $namespace, '=', Hermes::NON_PROJECT_NAMESPACES );
	}

	/**
	 * SQL condition for titles in the translation projects in one of $languages ("!xx:Foo").
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $title Title field, without namespace
	 * @param string[] $languages Not empty
	 */
	public static function projectSqlCondition( IReadableDatabase $dbr, string $title, array $languages ): IExpression {
		return $dbr->orExpr( array_map(
			static fn ( string $language ) => $dbr->expr(
				$title, IExpression::LIKE, new LikeValue( "!$language:", $dbr->anyString() )
			),
			$languages
		) );
	}
}
