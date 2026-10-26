<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use stdClass;

/**
 * A project of a wiki in the family (a hermes_projects row): its pages in one language, either the wiki's
 * base project or one of its translation projects, and whether it's active.
 */
final class Project {

	/**
	 * @param string $code Language code
	 * @param string $wiki Wiki ID
	 * @param bool $isBase Whether this is the wiki's base project, rather than a translation project
	 * @param bool $isActive Whether the project is active (at most one project per language is)
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
			$row->hp_language, $row->hp_wiki, (bool)$row->hp_base, (bool)$row->hp_active, $row->hp_url
		);
	}

	public function toRow(): array {
		return [
			'hp_language' => $this->code,
			'hp_wiki' => $this->wiki,
			'hp_base' => (int)$this->isBase,
			'hp_active' => (int)$this->isActive,
			'hp_url' => $this->urlTemplate,
		];
	}

	/**
	 * Identifies this project, e.g. in Special:HermesPages sub-pages: the language code if it's active, else
	 * "<code>@<wiki>", with the wiki ID in title form, as titles can't keep e.g. a trailing "_" of it.
	 */
	public function toId(): string {
		return $this->isActive ? $this->code : "{$this->code}@" . self::normalizeWiki( $this->wiki );
	}

	/** Whether $id identifies this project: as toId(), or as "<code>@<wiki>" if it's active. */
	public function hasId( string $id ): bool {
		if ( !str_contains( $id, '@' ) ) {
			return $this->isActive && $id === $this->code;
		}
		[ $code, $wiki ] = explode( '@', $id, 2 );
		return $code === $this->code && self::normalizeWiki( $wiki ) === self::normalizeWiki( $this->wiki );
	}

	/** The language code an ID (see toId()) is about. */
	public static function languageOfId( string $id ): string {
		return explode( '@', $id, 2 )[ 0 ];
	}

	public function isLocal(): bool {
		return $this->wiki === WikiMap::getCurrentWikiId();
	}

	public function isLocalTranslationProject(): bool {
		return !$this->isBase && $this->isLocal();
	}

	public function getLanguage(): Language {
		return MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( $this->code );
	}

	/** The title prefix of this project's pages: "!xx:" for a translation project, none for the base project. */
	public function getPrefix(): string {
		return $this->isBase ? '' : "!{$this->code}:";
	}

	/** $title (without namespace) as a title in this project, capitalized as $wgCapitalLinks demands. */
	public function addPrefix( string $title, int $namespace ): string {
		// Core's capitalization only sees the leading "!", so it has to happen here.
		return $this->getPrefix() . Title::capitalize( $title, $namespace );
	}

	public function withActive( bool $isActive ): self {
		return new self( $this->code, $this->wiki, $this->isBase, $isActive, $this->urlTemplate );
	}

	public function withUrlTemplate( ?string $urlTemplate ): self {
		return new self( $this->code, $this->wiki, $this->isBase, $this->isActive, $urlTemplate );
	}

	private static function normalizeWiki( string $wiki ): string {
		return trim( preg_replace( '/[ _]+/', '_', $wiki ), '_' );
	}
}
