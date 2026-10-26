<?php

namespace MediaWiki\Extension\Hermes;

/**
 * A link to the version of a page in another language, as shown in "In other languages".
 */
final class LanguageLink {

	public function __construct(
		public readonly PageInfo $page,
		public readonly string $url,
	) {
	}

	public function getLanguageCode(): string {
		return $this->page->title->project->code;
	}

	/** Whether the target is a translation project page rather than a page on a base wiki. */
	public function isProject(): bool {
		return !$this->page->title->project->isBase;
	}

	/** The target's title as shown to readers, e.g. "Kategorio:Foo". */
	public function getDisplayTitle(): string {
		return $this->page->title->getDisplayTitle( true );
	}

	/**
	 * Links to the other-language versions of $page, keyed by language code. Targets on wikis
	 * without a registered URL are left out.
	 *
	 * @param PageInfo $page
	 * @return array<string, LanguageLink>
	 */
	public static function forPage( PageInfo $page ): array {
		$links = [];
		foreach ( TagStore::getLinksForPage( $page ) as $lang => $tagLink ) {
			$url = $tagLink->page->title->getUrl();
			if ( $url !== null ) {
				$links[ $lang ] = new self( $tagLink->page, $url );
			}
		}
		return $links;
	}
}
