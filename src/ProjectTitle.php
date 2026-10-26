<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Sanitizer;
use UnexpectedValueException;

/**
 * A page title on some wiki in the family, split into project, namespace and base title, e.g.
 * "Category:!eo:Foo" -> (this wiki's "eo" project, NS_CATEGORY, 'Foo'), with its page ID and display forms.
 * Its project gives its wiki and language. LocalProjectTitle adds what needs this wiki's own data.
 */
abstract class ProjectTitle {

	protected function __construct(
		public readonly Project $project,
		public readonly int $namespace,
		/** DB key without namespace and "!xx:" prefix */
		public readonly string $baseTitle,
	) {
	}

	/**
	 * A LocalProjectTitle or RemoteProjectTitle, depending on $project's wiki.
	 *
	 * @param Project $project
	 * @param int $namespace
	 * @param string $baseTitle DB key without namespace and "!xx:" prefix
	 * @param string $namespaceText Namespace name on the title's own wiki
	 * @param int $id Page ID on the title's own wiki
	 */
	public static function make(
		Project $project, int $namespace, string $baseTitle, string $namespaceText, int $id
	): self {
		return $project->isLocal()
			? new LocalProjectTitle( $project, $namespace, $baseTitle, $namespaceText, $id )
			: new RemoteProjectTitle( $project, $namespace, $baseTitle, $namespaceText, $id );
	}

	/**
	 * Creates a title from a hermes_tags table row. A page whose translation project no longer exists is a
	 * base page again, with the "!xx:" prefix in its (now invalid) title, as it would be parsed on its wiki.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 */
	public static function newFromRow( $row ): self {
		$baseTitle = $row->ht_base_title;
		$project = $row->ht_language !== null ? ProjectStore::getProject( $row->ht_language, $row->ht_wiki ) : null;
		if ( $project === null || $project->isBase ) {
			if ( $row->ht_language !== null ) {
				$baseTitle = "!{$row->ht_language}:$baseTitle";
			}
			// Every wiki adds its base project before it can store tags.
			$project = ProjectStore::getBaseProject( $row->ht_wiki )
				?? throw new UnexpectedValueException( "hermes_tags has pages of unknown wiki \"{$row->ht_wiki}\"" );
		}
		return self::make(
			$project, (int)$row->ht_namespace_id, $baseTitle, $row->ht_namespace_text, (int)$row->ht_page_id
		);
	}

	/** The page ID, or 0 if the page doesn't exist. */
	abstract public function getId(): int;

	/** The namespace name on the title's own wiki, or '' for the main namespace. */
	abstract public function getNamespaceText(): string;

	/** Whether $other is the same page, on the same wiki. */
	public function isSamePage( ProjectTitle $other ): bool {
		return $this->project->wiki === $other->project->wiki && $this->getId() === $other->getId();
	}

	/**
	 * Whether this is in an invalid Hermes title format: titles looking like "!...:" must be
	 * "!xx:", where "xx" is a registered translation project.
	 */
	public function isInvalid(): bool {
		return $this->project->isBase && ProjectPrefix::isReserved( $this->baseTitle );
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
	 * @param string|null $section Appended as "#section", if given
	 */
	public function getDisplayTitle( bool $withNamespace = false, ?string $section = null ): string {
		$nsText = $withNamespace ? $this->getLocalizedNamespaceText() : '';
		return strtr( $this->joinTitle( $nsText, '', $section ), '_', ' ' );
	}

	/**
	 * The real, physical title as it's actually stored on its own wiki: namespace and "!xx:"
	 * translation project prefix included.
	 */
	public function getRealTitle(): string {
		return strtr( $this->joinTitle( $this->getNamespaceText(), $this->project->getPrefix() ), ' ', '_' );
	}

	/**
	 * The URL of this title (or its section $section) on its own wiki, from its project's URL template
	 * (so possibly protocol-relative), or null if that's unknown.
	 */
	public function getUrl( ?string $section = null ): ?string {
		$template = $this->project->urlTemplate;
		if ( $template === null ) {
			return null;
		}

		$path = strtr( $this->joinTitle( $this->getNamespaceText(), $this->project->getPrefix() ), ' ', '_' );
		$fragment = $section !== null ? '#' . Sanitizer::escapeIdForLink( $section ) : '';
		return str_replace( '$1', wfUrlencode( $path ), $template ) . $fragment;
	}

	/** "ns:", $projectPrefix, the base title and "#section", with empty parts left out. */
	private function joinTitle( string $nsText, string $projectPrefix = '', ?string $section = null ): string {
		$prefix = $nsText !== '' ? "{$nsText}:" : '';
		$suffix = $section !== null ? "#{$section}" : '';
		return "{$prefix}{$projectPrefix}{$this->baseTitle}{$suffix}";
	}
}
