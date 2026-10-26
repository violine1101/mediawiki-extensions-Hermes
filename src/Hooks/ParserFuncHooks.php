<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidSectionException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\Special\TagFormatter;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagLink;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Hook\LinksUpdateCompleteHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use Wikimedia\HtmlArmor\HtmlArmor;

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
	 * {{#hermes:tag1|tag2|...|tag3=#section|tag4=#section|...}}
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
		} catch ( InvalidSectionException $e ) {
			return Html::errorBox( wfMessage( 'hermes-invalid-section' )->plaintextParams( $e->arg )->parse() );
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
		$page = LocalProjectTitle::fromTitle( $parser->getPage() );

		$conflicts = TagStore::findConflicts( $page, $tags );
		$linkRenderer = $parser->getLinkRenderer();
		$tagLink = static fn ( string $tag ) => $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'HermesTags', $tag ), new HtmlArmor( Html::element( 'code', [], $tag ) )
		);
		$externalLink = static fn ( TagLink $l ) => TagFormatter::externalLink( $l->title, $l->tag->section );
		$detailsLink = $linkRenderer->makeKnownLink(
			TagFormatter::detailsTitle( $page ), wfMessage( 'hermes-page-details' )->text()
		);
		$localConflicts = $conflicts[ $page->project->code ] ?? [];
		$foreignConflicts = $conflicts;
		unset( $foreignConflicts[ $page->project->code ] );

		if ( $localConflicts ) {
			$parser->addTrackingCategory( 'hermes-tag-conflict-category' );
			if ( $showWarnings ) {
				$conflictItems = [];
				foreach ( $localConflicts as $tag => $pages ) {
					$tagCode = $tagLink( $tag );
					$pages = array_filter( $pages, static fn ( TagLink $l ) => !$l->title->isSamePage( $page ) );
					$links = array_map( $externalLink, $pages );
					$conflictItems[] = Html::rawElement( 'li', [], "{$tagCode}: " . implode( ', ', $links ) );
				}
				$list = Html::rawElement( 'ul', [], implode( '', $conflictItems ) );
				$text .= Html::warningBox(
					wfMessage( 'hermes-tag-conflict-warning' )->rawParams( $detailsLink )->parse() . $list
				);
			}
		}

		if ( $foreignConflicts ) {
			$parser->addTrackingCategory( 'hermes-ambiguous-links-category' );
			if ( $showWarnings ) {
				$ambiguousItems = [];
				foreach ( $foreignConflicts as $lang => $langEntries ) {
					foreach ( $langEntries as $tag => $pages ) {
						$tagCode = $tagLink( $tag );
						$langName = htmlspecialchars( Hermes::getAutonym( $lang ) ?: $lang );
						$links = array_map( $externalLink, $pages );
						$ambiguousItems[] = Html::rawElement(
							'li', [], "{$tagCode} ({$langName}): " . implode( ', ', $links )
						);
					}
				}
				$list = Html::rawElement( 'ul', [], implode( '', $ambiguousItems ) );
				$text .= Html::warningBox(
					wfMessage( 'hermes-ambiguous-links-warning' )->rawParams( $detailsLink )->parse() . $list
				);
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

		$page = LocalProjectTitle::fromTitle( $linksUpdate->getTitle() );
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
		// The page is gone by now, so its ID can't be looked up.
		TagStore::deleteTagsForPage( LocalProjectTitle::fromTitle( $page, $pageID ) );

		return true;
	}
}
