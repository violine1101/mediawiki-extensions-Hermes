<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidSectionException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;

class Tag {

	/** @var string The normalized tag name. */
	public string $name;

	/**
	 * @var ?string The normalized name of the section this tag refers to,
	 *   or null if the tag applies to the page as a whole.
	 */
	public ?string $section;

	/**
	 * @var int This tag's fallback chain priority, relative to the page's other tags
	 *   regardless of section. Defaults to 0 if the sort order is unknown.
	 */
	public int $order = 0;

	/**
	 * Creates a Tag from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object; must have the relevant ht_ properties.
	 * @return Tag The resulting tag object.
	 */
	public static function fromRow( $row ): Tag {
		$self = new Tag();

		$self->name = $row->ht_tag;
		$self->section = $row->ht_section;
		$self->order = (int)$row->ht_order;

		return $self;
	}

	/**
	 * Builds a list of tags from the arguments to the {{#hermes:}} function, e.g.
	 * {{#hermes:tag1|tag2|tag3=#section|tag4=#section}}: an argument "tag=#section" sets that tag
	 * for the given section only.
	 *
	 * Assigns each tag's fallback chain order: the order they were listed in, regardless of section.
	 *
	 * @param string[] $args The arguments passed to the {{#hermes:}} function.
	 * @return Tag[] The list of tags.
	 * @throws InvalidTagNameException If an argument is not a valid tag.
	 * @throws InvalidSectionException If a "tag=#section" argument has no valid section reference.
	 * @throws DuplicateTagException If the same tag name is used more than once.
	 */
	public static function fromArgs( array $args ): array {
		$tags = [];
		foreach ( $args as $arg ) {
			$arg = trim( $arg );
			$section = null;
			$name = $arg;
			if ( str_contains( $arg, '=' ) ) {
				[ $name, $section ] = explode( '=', $arg, 2 );
				$section = trim( $section );
				if ( !str_starts_with( $section, '#' ) || self::normalizeWhitespace( substr( $section, 1 ) ) === '' ) {
					throw new InvalidSectionException( $arg );
				}
				$section = substr( $section, 1 );
			}
			$tags[] = self::fromArg( $name, $section );
		}

		$seenNames = [];
		foreach ( $tags as $order => $tag ) {
			if ( isset( $seenNames[ $tag->name ] ) ) {
				throw new DuplicateTagException( $tag->name );
			}
			$seenNames[ $tag->name ] = true;
			$tag->order = $order;
		}

		return $tags;
	}

	/**
	 * Builds a tag from a tag name and the section it's for, as given to the {{#hermes:}} function.
	 *
	 * @param string $name The non-normalized tag name.
	 * @param ?string $section The non-normalized section, or null for the page as a whole.
	 * @return Tag The tag, but without a meaningful sort order.
	 * @throws InvalidTagNameException If the name is not a valid tag.
	 */
	public static function fromArg( string $name, ?string $section = null ): Tag {
		$self = new Tag();
		$self->name = self::normalizeName( $name );
		$self->section = self::normalizeSection( $section );

		return $self;
	}

	/**
	 * Creates a Tag from its JSON-decoded representation, as produced by json_encode().
	 *
	 * @param \stdClass $obj The object returned by json_decode(). Assumed to be valid!
	 * @return Tag The tag.
	 */
	public static function fromJson( \stdClass $obj ): Tag {
		$self = new Tag();
		$self->name = $obj->name;
		$self->section = $obj->section ?? null;
		$self->order = $obj->order ?? 0;

		return $self;
	}

	/**
	 * Normalizes a tag name.
	 *
	 * @param string $name The non-normalized name
	 * @return string The normalized name: lowercase, trimmed, and underscores for whitespace.
	 * @throws InvalidTagNameException If the argument is not a valid tag.
	 */
	private static function normalizeName( string $name ): string {
		$name = strtolower( trim( $name ) );
		$name = self::normalizeWhitespace( $name );

		// allowed chars: digits, ascii chars, and the special chars _ / . :
		$nameRegex = "/^[\w\d\/.:]+$/";

		if ( preg_match( $nameRegex, $name ) ) {
			return $name;
		}
		throw new InvalidTagNameException( $name );
	}

	/**
	 * Normalizes a section name.
	 *
	 * @param ?string $section The non-normalized section, or null if there is none.
	 * @return ?string The normalized section: trimmed and underscores for whitespace.
	 *   Case is preserved, since HTML anchors are case-sensitive.
	 */
	private static function normalizeSection( ?string $section ): ?string {
		if ( $section === null ) {
			return null;
		}
		$section = self::normalizeWhitespace( $section );
		return $section === '' ? null : $section;
	}

	/**
	 * Collapses runs of whitespace and/or underscores into a single underscore,
	 * and trims leading/trailing underscores.
	 */
	private static function normalizeWhitespace( string $text ): string {
		$text = preg_replace( '/[\s_]+/', '_', $text ) ?? '';
		return trim( $text, '_' );
	}
}
