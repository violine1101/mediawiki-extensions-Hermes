<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;

/**
 * A title on another wiki, as stored in hermes_tags: its namespace name and page ID can't be looked up here.
 */
final class RemoteProjectTitle extends ProjectTitle {

	/**
	 * @param Project $project A project on another wiki
	 * @param int $namespace
	 * @param string $baseTitle DB key without namespace and "!xx:" prefix
	 * @param string $namespaceText
	 * @param int $id
	 */
	public function __construct(
		Project $project,
		int $namespace,
		string $baseTitle,
		private readonly string $namespaceText,
		private readonly int $id,
	) {
		if ( $project->isLocal() ) {
			throw new InvalidArgumentException( "Project {$project->code}@{$project->wiki} is on this wiki" );
		}
		parent::__construct( $project, $namespace, $baseTitle );
	}

	public function getId(): int {
		return $this->id;
	}

	public function getNamespaceText(): string {
		return $this->namespaceText;
	}
}
