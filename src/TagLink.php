<?php

namespace MediaWiki\Extension\Hermes;

/**
 * A page setting a tag, linking to the tag's section of the page if it has one.
 */
final class TagLink {

	public function __construct(
		public readonly PageInfo $page,
		public readonly Tag $tag,
	) {
	}

	/**
	 * Creates a TagLink from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return TagLink
	 */
	public static function fromRow( $row ): TagLink {
		return new TagLink( PageInfo::fromRow( $row ), Tag::fromRow( $row ) );
	}

	/** The URL of the page's section, or null if its wiki has no registered URL. */
	public function getUrl(): ?string {
		return $this->page->title->getUrl( $this->tag->section );
	}

	/** The page's title with namespace and "#section", as shown to readers. */
	public function getDisplayTitle(): string {
		return $this->page->title->getDisplayTitle( true, $this->tag->section );
	}
}
