<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\ProjectTitle;
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
		$unescaped = ProjectTitle::unescape( $title->getText() );
		if ( $unescaped !== null ) {
			$baseTitle = Title::makeTitleSafe( $ns, $unescaped );
			if ( $baseTitle ) {
				$revRecord = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $baseTitle );
			}
			return;
		}

		if ( $contextTitle === null || ProjectTitle::isReserved( $title->getText() ) ) {
			return;
		}

		$project = ProjectTitle::newFromLinkTarget( $contextTitle )->project;
		if ( $project->isBase ) {
			return;
		}

		$projectTitle = ProjectTitle::newFromLinkTarget( $title )->withProject( $project )->toTitle();
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

		$project = ProjectTitle::newFromLinkTarget( $page )->project;
		if ( $project->isBase ) {
			return;
		}

		$output = $parser->getOutput();
		foreach ( $output->getLinkList( ParserOutputLinkTypes::TEMPLATE ) as [ 'link' => $link ] ) {
			if ( !ProjectTitle::isReserved( $link->getText() ) ) {
				$projectTitle = ProjectTitle::newFromLinkTarget( $link )->withProject( $project )->toTitle();
				$output->addExistenceDependency( $projectTitle );
			}
		}
	}
}
