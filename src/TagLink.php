<?php

namespace MediaWiki\Extension\Hermes;

class TagLink {

	public PageInfo $page;
	public Tag $tag;
	public int $order;

	/**
	 * Creates a TagLink from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return TagLink The resulting tag row object.
	 */
	public static function fromRow( $row ): TagLink {
		$self = new TagLink();

		$self->page = PageInfo::fromRow( $row );
		$self->tag = Tag::fromRow( $row );
		$self->order = $row->ht_order;

		return $self;
	}
}
