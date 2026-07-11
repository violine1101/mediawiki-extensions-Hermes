<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Page\PageReference;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

class PageInfo {

	public string $wiki;
	public int $id;
	public string $title;
	public string $language;
	public ?string $section = null;

	public static function fromLocalPage( PageReference $page ): PageInfo {
		$self = new PageInfo();
		$title = Title::newFromPageReference( $page );

		$self->wiki = WikiMap::getCurrentWikiId();
		$title->assertWiki( PageReference::LOCAL );

		$self->id = $title->getArticleID();
		$self->title = $title->getPrefixedDBkey();
		// TODO: This should depend on namespace (or start of base title) later
		$self->language = $title->getPageLanguage()->getCode();

		return $self;
	}

	/**
	 * Creates a PageInfo from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return PageInfo The resulting page info object.
	 */
	public static function fromRow( $row ): PageInfo {
		$self = new PageInfo();

		$self->wiki = $row->ht_wiki;
		$self->id = $row->ht_page_id;
		$self->title = $row->ht_page_title;
		$self->language = $row->ht_language;
		$self->section = $row->ht_section;

		return $self;
	}
}
