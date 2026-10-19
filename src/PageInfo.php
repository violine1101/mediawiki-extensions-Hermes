<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;

/**
 * A page somewhere in the wiki family, as stored in hermes_tags: its wiki, page ID and title.
 */
class PageInfo {

	/**
	 * @param ProjectTitle $title
	 * @param ?int $id The page ID; looked up on first use for local pages if null
	 */
	public function __construct(
		public readonly ProjectTitle $title,
		private ?int $id = null,
	) {
	}

	/** $id must be given where the page can't be looked up, e.g. once it's deleted. */
	public static function fromLocalPage( LinkTarget|PageReference $page, ?int $id = null ): PageInfo {
		return new PageInfo( ProjectTitle::newFromLinkTarget( $page ), $id );
	}

	/**
	 * Creates a PageInfo from a hermes_tags table row.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return PageInfo The resulting page info object.
	 */
	public static function fromRow( $row ): PageInfo {
		$title = ProjectTitle::makeTitleOnWiki(
			$row->ht_wiki,
			(int)$row->ht_namespace_id,
			$row->ht_base_title,
			$row->ht_translation_project,
			$row->ht_section,
			$row->ht_namespace_text
		);
		return new PageInfo( $title, (int)$row->ht_page_id );
	}

	/** The page ID, or 0 if the page doesn't exist. Only known for local pages and database rows. */
	public function getId(): int {
		$this->id ??= $this->title->toStoredTitle()->getArticleID();
		return $this->id;
	}

	/**
	 * Builds an HTML link to the page, or just its (escaped) title if its wiki has no
	 * registered URL.
	 */
	public function buildLink(): string {
		$displayTitle = $this->title->getDisplayTitle( true );
		$url = $this->title->getUrl();
		if ( $url === null ) {
			return htmlspecialchars( $displayTitle );
		}

		$languageName = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageName( $this->title->getLanguageCode(), RequestContext::getMain()->getLanguage()->getCode() );
		return Html::element( 'a', [
			'href' => $url,
			'class' => 'extiw',
			'title' => wfMessage( 'interlanguage-link-title', $displayTitle, $languageName )->text(),
		], $displayTitle );
	}
}
