<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;

class Tag {

	public string $name;
	public ?string $section;

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

		return $self;
	}

	/**
	 * Builds a list of tags from the arguments to the {{#hermes:}} function.
	 *
	 * @param string[] $args The arguments passed to the {{#hermes:}} function.
	 * @return Tag[] The list of tags.
	 * @throws InvalidTagNameException If an argument is not a valid tag.
	 */
	public static function fromArgs( array $args ): array {
		return array_map( self::fromArg( ... ), $args );
	}

	/**
	 * Builds a tag from a singular argument to the {{#hermes:}} function.
	 *
	 * @param string $arg An argument passed to the {{#hermes:}} function.
	 * @return Tag The tag.
	 * @throws InvalidTagNameException If the argument is not a valid tag.
	 */
	public static function fromArg( string $arg ): Tag {
		$arg = trim( $arg );
		$arg = explode( '#', $arg, 2 );

		$self = new Tag();
		$self->name = self::normalizeName( $arg[ 0 ] );
		$self->section = $arg[ 1 ] ?? null;

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

		return $self;
	}

	/**
	 * Normalizes a tag name.
	 *
	 * @param string $name The non-normalized name
	 * @return string The normalized name: lowercase, trimmed, no spaces.
	 * @throws InvalidTagNameException If the argument is not a valid tag.
	 */
	private static function normalizeName( string $name ): string {
		$name = strtolower( trim( $name ) );
		$name = str_replace( ' ', '_', $name );

		// allowed chars: digits, ascii chars, and the special chars _ / . :
		$nameRegex = "/^[\w\d\/_.:]+$/";

		if ( preg_match( $nameRegex, $name ) ) {
			return $name;
		}
		throw new InvalidTagNameException( $name );
	}
}
