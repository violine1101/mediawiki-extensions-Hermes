<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWiki\Extension\Hermes\Special\SpecialWantedCategories;
use MediaWiki\Extension\Hermes\Special\SpecialWantedCategoryTranslations;
use MediaWiki\Extension\Hermes\Special\SpecialWantedTranslations;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;
use MediaWiki\SpecialPage\Hook\WgQueryPagesHook;
use MediaWiki\Specials\Hook\WantedPages__getQueryInfoHook;

/**
 * Keeps translation-project pages out of Special:WantedPages and Special:WantedCategories: every link on a
 * project page goes to a "!xx:" page, so they would otherwise list most base pages again per project.
 * Special:WantedTranslations and Special:WantedCategoryTranslations list the wanted ones instead.
 */
class WantedPagesHooks implements WantedPages__getQueryInfoHook, SpecialPage_initListHook, WgQueryPagesHook {

	/** @inheritDoc */
	public function onWantedPages__getQueryInfo( $wantedPages, &$query ) {
		$query['conds'][] = ProjectPrefix::notProjectSqlCondition(
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

	/**
	 * For updateSpecialPages.php.
	 *
	 * @inheritDoc
	 */
	public function onWgQueryPages( &$qp ) {
		$qp[] = [ SpecialWantedTranslations::class, 'WantedTranslations' ];
		$qp[] = [ SpecialWantedCategoryTranslations::class, 'WantedCategoryTranslations' ];
	}
}
