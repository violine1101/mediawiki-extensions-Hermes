<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\CoreParserFunctions;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class ParserFuncHooks implements
	LinksUpdateCompleteHook,
	PageDeleteCompleteHook,
	ParserAfterParseHook,
	ParserFirstCallInitHook
{

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'hermes', [ self::class, 'renderHermes' ] );
		return true;
	}

	/**
	 * {{#hermes:tag1|tag2|tag3#section|...}}
	 */
	public static function renderHermes( Parser $parser, string ...$args ): string {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$showWarnings = $config->get( 'HermesWarnings' );
		$showDebugInfo = $config->get( 'HermesDebug' );

		$output = '';

		if ( $parser->getOutput()->getPageProperty( 'hermes_tags' ) !== null ) {
			$parser->addTrackingCategory( 'hermes-duplicate-call-category' );

			if ( $showWarnings ) {
				$output .= Html::warningBox( wfMessage( 'hermes-duplicate-call' )->parse() );
			}
		}

		try {
			$tags = Tag::fromArgs( $args );
		} catch ( InvalidTagNameException $e ) {
			return Html::errorBox( wfMessage( 'hermes-invalid-tag-name', $e->tagName )->parse() );
		} catch ( DuplicateTagException $e ) {
			return Html::errorBox( wfMessage( 'hermes-duplicate-tag-name', $e->tagName )->parse() );
		}

		$json = json_encode( $tags );
		$parser->getOutput()->setPageProperty( 'hermes_tags', $json );

		if ( $showDebugInfo ) {
			$output .= Html::noticeBox( '#hermes: ' . htmlspecialchars( $json ) );
		}

		return $output;
	}

	/** @inheritDoc */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$json = $parser->getOutput()->getPageProperty( 'hermes_tags' );
		if ( $json === null ) {
			return;
		}

		$showWarnings = MediaWikiServices::getInstance()->getMainConfig()->get( 'HermesWarnings' );
		$tags = array_map( Tag::fromJson( ... ), json_decode( $json ) );
		$page = PageInfo::fromLocalPage( $parser->getPage() );

		$conflicts = TagStore::findConflicts( $page, $tags );
		$localConflicts = $conflicts[ $page->language ] ?? [];
		$foreignConflicts = $conflicts;
		unset( $foreignConflicts[ $page->language ] );

		if ( $localConflicts ) {
			$parser->addTrackingCategory( 'hermes-tag-conflict-category' );
			if ( $showWarnings ) {
				$conflictItems = [];
				foreach ( $localConflicts as $tag => $pages ) {
					$tagCode = Html::element( 'code', [], $tag );
					$pages = array_filter(
						$pages,
						static fn ( PageInfo $p ) => $p->wiki !== $page->wiki || $p->id !== $page->id,
					);
					$links = array_map( static fn ( PageInfo $p ) => $p->buildLink(), $pages );
					$conflictItems[] = Html::rawElement( 'li', [], "{$tagCode}: " . implode( ', ', $links ) );
				}
				$list = Html::rawElement( 'ul', [], implode( '', $conflictItems ) );
				$text .= Html::warningBox( wfMessage( 'hermes-tag-conflict-warning' )->parse() . $list );
			}
		}

		if ( $foreignConflicts ) {
			$parser->addTrackingCategory( 'hermes-ambiguous-links-category' );
			if ( $showWarnings ) {
				$ambiguousItems = [];
				foreach ( $foreignConflicts as $lang => $langEntries ) {
					foreach ( $langEntries as $tag => $pages ) {
						$tagCode = Html::element( 'code', [], $tag );
						$langName = htmlspecialchars( CoreParserFunctions::language( $parser, $lang ) );
						$links = array_map( static fn ( PageInfo $p ) => $p->buildLink(), $pages );
						$ambiguousItems[] = Html::rawElement(
							'li', [], "{$tagCode} ({$langName}): " . implode( ', ', $links )
						);
					}
				}
				$list = Html::rawElement( 'ul', [], implode( '', $ambiguousItems ) );
				$text .= Html::warningBox( wfMessage( 'hermes-ambiguous-links-warning' )->parse() . $list );
			}
		}
	}

	/** @inheritDoc */
	public function onLinksUpdateComplete( $linksUpdate, $ticket ) {
		$output = $linksUpdate->getParserOutput();
		$json = $output->getPageProperty( 'hermes_tags' );
		if ( $json === null ) {
			return true;
		}

		$page = PageInfo::fromLocalPage( $linksUpdate->getTitle() );
		$tags = array_map( Tag::fromJson( ... ), json_decode( $json ) );
		TagStore::setTagsForPage( $page, $tags );

		return true;
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
		TagStore::deleteTagsForPage( $page );

		return true;
	}
}
