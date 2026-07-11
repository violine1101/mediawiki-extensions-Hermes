<?php

namespace MediaWiki\Extension\Hermes;

class Tag {

	public string $id;
	public ?string $section;

	/**
	 * Creates a Tag from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return Tag The resulting tag object.
	 */
	public static function fromRow( $row ): Tag {
		$self = new Tag();

		$self->id = $row->ht_tag;
		$self->section = $row->ht_section;

		return $self;
	}
}
