<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\WikiMap\WikiMap;
use stdClass;

/**
 * A language registered on a wiki of the family (a hermes_languages row): the wiki's base language or one of
 * its translation projects, and whether it's active there.
 */
final class HermesLanguage {

	/**
	 * @param string $code Language code
	 * @param string $wiki Wiki ID
	 * @param bool $isBase Whether this is the wiki's base language, rather than a translation project
	 * @param bool $isActive Whether the language is active on this wiki (on at most one wiki per language)
	 * @param string|null $urlTemplate The wiki's URL template, with "$1" for the page title; null if unknown
	 */
	public function __construct(
		public readonly string $code,
		public readonly string $wiki,
		public readonly bool $isBase,
		public readonly bool $isActive,
		public readonly ?string $urlTemplate,
	) {
	}

	public static function fromRow( stdClass $row ): self {
		return new self(
			$row->hl_language, $row->hl_wiki, (bool)$row->hl_base, (bool)$row->hl_active, $row->hl_url
		);
	}

	public function toRow(): array {
		return [
			'hl_language' => $this->code,
			'hl_wiki' => $this->wiki,
			'hl_base' => (int)$this->isBase,
			'hl_active' => (int)$this->isActive,
			'hl_url' => $this->urlTemplate,
		];
	}

	public function isLocal(): bool {
		return $this->wiki === WikiMap::getCurrentWikiId();
	}

	public function isLocalProject(): bool {
		return !$this->isBase && $this->isLocal();
	}

	public function withActive( bool $isActive ): self {
		return new self( $this->code, $this->wiki, $this->isBase, $isActive, $this->urlTemplate );
	}

	public function withUrlTemplate( ?string $urlTemplate ): self {
		return new self( $this->code, $this->wiki, $this->isBase, $this->isActive, $urlTemplate );
	}
}
