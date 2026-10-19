<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\WikiMap\WikiMap;

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
	 * Creates a PageInfo for the target of an interwiki-prefixed title. For example,
	 * for an interwiki link "xx:Category:Foo", $prefix is "xx" and $title is "Category:Foo".
	 *
	 * $prefix determines both the translation project (if any) and which wiki's namespace naming
	 * to parse $title's namespace against: namespace names are locale-dependent, and $title can
	 * name a page on a wiki with a different language than the one currently running - one whose
	 * own base language, and so own namespace naming, can differ from this wiki's.
	 *
	 * @param string $prefix The interwiki prefix (language code)
	 * @param string $title The virtual page title
	 * @return PageInfo
	 */
	public static function fromInterwiki( string $prefix, string $title ): PageInfo {
		$entry = LanguageStore::getLanguageEntry( $prefix );
		$hostLanguageCode = $entry !== null ? LanguageStore::getBaseLanguageForWiki( $entry[ 'wiki' ] ) : null;

		$services = MediaWikiServices::getInstance();
		$language = $hostLanguageCode !== null
			? $services->getLanguageFactory()->getLanguage( $hostLanguageCode )
			: $services->getLanguageFactory()->getLanguage( 'en' );

		$parser = new TitleParser(
			$language,
			$services->getInterwikiLookup(),
			$services->getNamespaceInfo(),
			$services->getMainConfig()->get( MainConfigNames::LocalInterwikis )
		);

		$namespace = NS_MAIN;
		$namespaceText = '';
		$section = null;
		try {
			$parsedTitle = $parser->parseTitle( $title );

			$namespace = $parsedTitle->getNamespace();
			$namespaceText = $language->getFormattedNsText( $namespace );

			$fragment = $parsedTitle->getFragment();
			$section = $fragment !== '' ? strtr( $fragment, ' ', '_' ) : null;

			$baseTitle = $parsedTitle->getDBkey();
		} catch ( MalformedTitleException ) {
			$baseTitle = strtr( $title, ' ', '_' );
		}

		$project = $entry !== null && !$entry[ 'isBase' ] ? $prefix : null;

		return new PageInfo( ProjectTitle::makeTitleOnWiki(
			$entry[ 'wiki' ] ?? null, $namespace, $baseTitle, $project, $section, $namespaceText
		) );
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
	 * Builds this page's interlanguage link.
	 */
	public function getInterlanguageLink(): string {
		return "{$this->title->getLanguageCode()}:{$this->title->getVirtualTitle()}";
	}

	/**
	 * Builds an HTML interlanguage link to the page.
	 * Falls back to plain (escaped) text if that doesn't resolve to a valid title.
	 */
	public function buildLink(): string {
		$displayTitle = $this->title->getDisplayTitle( true );
		$target = Title::newFromText( $this->getInterlanguageLink() );
		if ( $target === null ) {
			return htmlspecialchars( $displayTitle );
		}

		return MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $target, $displayTitle );
	}
}
