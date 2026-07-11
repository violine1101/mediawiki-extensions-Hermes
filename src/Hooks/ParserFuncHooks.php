<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class ParserFuncHooks implements LinksUpdateCompleteHook, PageDeleteCompleteHook, ParserFirstCallInitHook {

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'hermes', [ self::class, 'renderHermes' ] );
		return true;
	}

	/**
	 * {{#hermes:tag1|tag2|...}}
	 */
	public static function renderHermes( Parser $parser, string ...$args ): string {
		$tags = self::parseTagList( $args );
		$parser->getOutput()->setPageProperty( 'hermes_tags', implode( '|', $tags ) );
		return '';
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		$page = PageInfo::fromLocalPage( $linksUpdate->getTitle() );
		$store = new TagStore();

		$output = $linksUpdate->getParserOutput();
		$tagString = $output->getPageProperty( 'hermes_tags' );
		if ( $tagString === null || $tagString === '' ) {
			return true;
		}
		$tags = explode( '|', $tagString );

		$store->setTagsForPage( $page, $tags );
		return true;
	}

	private static function parseTagList( array $rawArgs ): array {
		$tags = [];
		foreach ( $rawArgs as $arg ) {
			// TODO: support for {{#hermes:tag1|tag2=#section|tag3}}
			$tag = trim( $arg );
			// TODO: check for legal/illegal characters, and throw errors if necessary
			if ( $tag !== '' ) {
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	/** @inheritDoc */
	public function onPageDeleteComplete(
		ProperPageIdentity $page,
		Authority $deleter,
		string $reason,
		int $pageID,
		RevisionRecord $deletedRev,
		ManualLogEntry $logEntry,
		int $archivedRevisionCount
	) {
		$page = PageInfo::fromLocalPage( $page );
		$store = new TagStore();
		$store->deleteTagsForPage( $page );
		return true;
	}
}
