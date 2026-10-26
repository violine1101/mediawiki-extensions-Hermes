<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use UnexpectedValueException;

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
	 * Creates a PageInfo from a hermes_tags table row. A page whose translation project no longer exists is a
	 * base page again, with the "!xx:" prefix in its (now invalid) title, as it would be parsed on its wiki.
	 *
	 * @param \stdClass $row Database row object. Assumed to have the relevant ht_ properties.
	 * @return PageInfo The resulting page info object.
	 */
	public static function fromRow( $row ): PageInfo {
		$baseTitle = $row->ht_base_title;
		$project = $row->ht_language !== null ? ProjectStore::getProject( $row->ht_language, $row->ht_wiki ) : null;
		if ( $project === null || $project->isBase ) {
			if ( $row->ht_language !== null ) {
				$baseTitle = "!{$row->ht_language}:$baseTitle";
			}
			// Every wiki adds its base project before it can store tags.
			$project = ProjectStore::getBaseProject( $row->ht_wiki )
				?? throw new UnexpectedValueException( "hermes_tags has pages of unknown wiki \"{$row->ht_wiki}\"" );
		}
		$title = ProjectTitle::makeTitle(
			$project,
			(int)$row->ht_namespace_id,
			$baseTitle,
			$row->ht_section,
			$row->ht_namespace_text
		);
		return new PageInfo( $title, (int)$row->ht_page_id );
	}

	/** The page ID, or 0 if the page doesn't exist. Only known for local pages and database rows. */
	public function getId(): int {
		$this->id ??= $this->title->toTitle()->getArticleID();
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
			->getLanguageName( $this->title->project->code, RequestContext::getMain()->getLanguage()->getCode() );
		return Html::element( 'a', [
			'href' => $url,
			'class' => 'extiw',
			'title' => wfMessage( 'interlanguage-link-title', $displayTitle, $languageName )->text(),
		], $displayTitle );
	}
}
