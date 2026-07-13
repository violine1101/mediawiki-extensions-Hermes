<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\Decorators\InterwikiLookup;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Interwiki\InterwikiLookup as IInterwikiLookup;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\LanguageLinksHook;

class LanguageLinkHooks implements LanguageLinksHook, MediaWikiServicesHook {

	/** @inheritDoc */
	public function onLanguageLinks( $title, &$links, &$linkFlags ) {
		if ( !$title->canExist() || !$title->exists() ) {
			return;
		}

		$page = PageInfo::fromLocalPage( $title );
		$tagLinks = TagStore::getLinksForPage( $page );
		if ( !$tagLinks ) {
			return;
		}

		$existingLangs = [];
		foreach ( $links as $link ) {
			[ $lang ] = explode( ':', $link, 2 );
			$existingLangs[ $lang ] = true;
		}

		foreach ( $tagLinks as $lang => $tagLink ) {
			if ( isset( $existingLangs[ $lang ] ) ) {
				// Still respect manually created [[xx:Foo]] links.
				continue;
			}
			$section = $tagLink->tag->section !== null ? '#' . $tagLink->tag->section : '';
			$links[] = "{$tagLink->page->language}:{$tagLink->page->fullTitle}{$section}";
		}
	}

	/**
	 * Wraps the core InterwikiLookup service, so that prefixes registered via LanguageStore
	 * resolve against the wikis registered in WikiStore.
	 *
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'InterwikiLookup',
			static function ( IInterwikiLookup $service, MediaWikiServices $services ) {
				return new InterwikiLookup( $service );
			}
		);
	}
}
