<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Parser\Sanitizer;

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
	 *   referring to the same section (if any). Defaults to 0 if the sort order is unknown.
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
	 * Builds a list of tags from the arguments to the {{#hermes:}} function.
	 *
	 * Assigns each tag's fallback chain order: tags are grouped by section,
	 * then ordered in the order they were listed.
	 *
	 * @param string[] $args The arguments passed to the {{#hermes:}} function.
	 * @return Tag[] The list of tags.
	 * @throws InvalidTagNameException If an argument is not a valid tag.
	 * @throws DuplicateTagException If the same tag name is used more than once.
	 */
	public static function fromArgs( array $args ): array {
		$tags = array_map( self::fromArg( ... ), $args );

		$seenNames = [];
		$orderBySection = [];
		foreach ( $tags as $tag ) {
			if ( isset( $seenNames[ $tag->name ] ) ) {
				throw new DuplicateTagException( $tag->name );
			}
			$seenNames[ $tag->name ] = true;

			$sectionKey = $tag->section ?? '';
			$tag->order = $orderBySection[ $sectionKey ] ?? 0;
			$orderBySection[ $sectionKey ] = $tag->order + 1;
		}

		return $tags;
	}

	/**
	 * Builds a tag from a singular argument to the {{#hermes:}} function.
	 *
	 * @param string $arg An argument passed to the {{#hermes:}} function.
	 * @return Tag The tag, but without a meaningful sort order.
	 * @throws InvalidTagNameException If the argument is not a valid tag.
	 */
	public static function fromArg( string $arg ): Tag {
		$arg = trim( $arg );
		$arg = explode( '#', $arg, 2 );

		$self = new Tag();
		$self->name = self::normalizeName( $arg[ 0 ] );
		$self->section = self::normalizeSection( $arg[ 1 ] ?? null );

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
	 * @return string The normalized name: lowercase, trimmed, and
	 *   whitespace/underscore runs collapsed to a single space.
	 * @throws InvalidTagNameException If the argument is not a valid tag.
	 */
	private static function normalizeName( string $name ): string {
		$name = strtolower( trim( $name ) );
		$name = Sanitizer::normalizeSectionNameWhitespace( $name );

		// allowed chars: digits, ascii chars, spaces, and the special chars / . :
		$nameRegex = "/^[\w\d\/.: ]+$/";

		if ( preg_match( $nameRegex, $name ) ) {
			return $name;
		}
		throw new InvalidTagNameException( $name );
	}

	/**
	 * Normalizes a tag's section value.
	 *
	 * @param ?string $section The non-normalized section, or null if there is none.
	 * @return ?string The normalized section: trimmed, whitespace/underscore runs collapsed
	 *   to a single space. Case is preserved, since HTML anchors are case-sensitive.
	 */
	private static function normalizeSection( ?string $section ): ?string {
		if ( $section === null ) {
			return null;
		}
		$section = Sanitizer::normalizeSectionNameWhitespace( $section );
		return $section === '' ? null : $section;
	}
}
