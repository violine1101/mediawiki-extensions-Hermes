<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;

/**
 * A title on this wiki, convertible to a core Title. Its namespace name and page ID are looked up on first use
 * unless given.
 */
final class LocalProjectTitle extends ProjectTitle {

	/**
	 * @param Project $project A project on this wiki
	 * @param int $namespace
	 * @param string $baseTitle DB key without namespace and "!xx:" prefix
	 * @param string|null $namespaceText
	 * @param int|null $id
	 */
	public function __construct(
		Project $project,
		int $namespace,
		string $baseTitle,
		private ?string $namespaceText = null,
		private ?int $id = null,
	) {
		if ( !$project->isLocal() ) {
			throw new InvalidArgumentException( "Project {$project->code}@{$project->wiki} is not on this wiki" );
		}
		parent::__construct( $project, $namespace, $baseTitle );
	}

	/**
	 * Parses a local title, ignoring its fragment; interwiki titles are rejected. "!xx:" prefixes are ignored in
	 * Hermes::NON_PROJECT_NAMESPACES. $id must be given where the page can't be looked up, e.g. once it's deleted.
	 */
	public static function fromTitle( LinkTarget|PageReference $page, ?int $id = null ): self {
		if ( $page instanceof PageReference ) {
			$page->assertWiki( PageReference::LOCAL );
		}
		if ( $page instanceof LinkTarget && $page->isExternal() ) {
			throw new InvalidArgumentException( "Interwiki title {$page->getInterwiki()}:{$page->getDBkey()}" );
		}

		$namespace = $page->getNamespace();
		[ $project, $baseTitle ] = Hermes::isProjectNamespace( $namespace )
			? ProjectPrefix::parse( $page->getDBkey() )
			: [ null, $page->getDBkey() ];
		return new self( $project ?? ProjectStore::getLocalBaseProject(), $namespace, $baseTitle, null, $id );
	}

	/** This title in $project, which must be on this wiki. */
	public function withProject( Project $project ): self {
		return new self( $project, $this->namespace, $this->baseTitle, $this->namespaceText );
	}

	/** This title's base page. */
	public function toBasePage(): self {
		return $this->project->isBase ? $this : $this->withProject( ProjectStore::getLocalBaseProject() );
	}

	/** The title, capitalized correctly even after a "!xx:" prefix, with $section (DB key form) as its fragment. */
	public function toTitle( ?string $section = null ): Title {
		$dbKey = $this->baseTitle;
		if ( !$this->project->isBase ) {
			$dbKey = $this->project->getPrefix() . Title::capitalize( $this->baseTitle, $this->namespace );
		}
		return Title::makeTitle( $this->namespace, $dbKey, $section ?? '' );
	}

	public function getId(): int {
		$this->id ??= $this->toTitle()->getArticleID();
		return $this->id;
	}

	public function getNamespaceText(): string {
		$this->namespaceText ??= $this->toTitle()->getNsText();
		return $this->namespaceText;
	}

	/** Whether $wgCapitalLinks requires a capital letter after the "!xx:" prefix that's missing. */
	public function hasCapitalizationError(): bool {
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
}
