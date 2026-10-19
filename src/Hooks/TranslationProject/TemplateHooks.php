<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Parser\Hook\ParserAfterParseHook;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\Title;

/**
 * On translation-project pages, transclusions like `{{foo}}` use the "!xx:Foo" version if it
 * exists, and silently fall back to "Foo" otherwise.
 *
 * `{{!:Foo}}` always uses the base "Foo" version, on any page.
 */
class TemplateHooks implements
	BeforeParserFetchTemplateRevisionRecordHook,
	ParserAfterParseHook
{

	/** @inheritDoc */
	public function onBeforeParserFetchTemplateRevisionRecord( $contextTitle, $title, &$skip, &$revRecord ) {
		$ns = $title->getNamespace();

		// Make sure that {{!:Foo}} always resolves to {{Template:Foo}}
		$unescaped = PageInfo::unescapeTitle( $title->getText() );
		if ( $unescaped !== null ) {
			$baseTitle = Title::makeTitleSafe( $ns, $unescaped );
			if ( $baseTitle ) {
				$revRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $baseTitle );
			}
			return;
		}

		if ( $contextTitle === null || PageInfo::isReservedTitle( $title->getText() ) ) {
			return;
		}

		$context = PageInfo::fromLocalPage( Title::newFromLinkTarget( $contextTitle ) );
		if ( $context->translationProject === null ) {
			return;
		}

		$projectTitle = PageInfo::fromLocalPage( $title )->withProject( $context->translationProject )->toTitle();
		if ( $projectTitle->exists() ) {
			$revRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $projectTitle );
		}
	}

	/**
	 * Makes the parser cache depend on the "!xx:" versions of all transcluded templates, so
	 * creating one replaces the fallback.
	 *
	 * @inheritDoc
	 */
	public function onParserAfterParse( $parser, &$text, $stripState ) {
		$page = $parser->getPage();
		if ( $page === null ) {
			return;
		}

		$context = PageInfo::fromLocalPage( $page );
		if ( $context->translationProject === null ) {
			return;
		}

		$output = $parser->getOutput();
		foreach ( $output->getLinkList( ParserOutputLinkTypes::TEMPLATE ) as [ 'link' => $link ] ) {
			if ( !PageInfo::isReservedTitle( $link->getText() ) ) {
				$projectTitle = PageInfo::fromLocalPage( $link )->withProject( $context->translationProject )->toTitle();
				$output->addExistenceDependency( $projectTitle );
			}
		}
	}
}
