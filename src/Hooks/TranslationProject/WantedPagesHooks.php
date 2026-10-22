<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Special\SpecialWantedCategories;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\Specials\Hook\WantedPages__getQueryInfoHook;

/**
 * Keeps translation-project pages out of Special:WantedPages and Special:WantedCategories: every link on a
 * project page goes to a "!xx:" page, so they would otherwise list most base pages again per project.
 */
class WantedPagesHooks implements WantedPages__getQueryInfoHook, SpecialPage_initListHook {

	/** @inheritDoc */
	public function onWantedPages__getQueryInfo( $wantedPages, &$query ) {
		$query['conds'][] = Hermes::notProjectTitle(
			MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase(),
			$query['fields']['namespace'],
			$query['fields']['title']
		);
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		// It has no query hook.
		$list['Wantedcategories']['class'] = SpecialWantedCategories::class;
	}
}
