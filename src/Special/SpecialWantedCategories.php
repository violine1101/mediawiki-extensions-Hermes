<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWiki\Specials\SpecialWantedCategories as CoreSpecialWantedCategories;

/**
 * Special:WantedCategories without translation-project categories. Replaces core's class via
 * WantedPagesHooks::onSpecialPage_initList(), since core's has no query hook.
 */
class SpecialWantedCategories extends CoreSpecialWantedCategories {

	/** @inheritDoc */
	public function getQueryInfo() {
		$query = parent::getQueryInfo();
		$query['conds'][] = ProjectPrefix::notProjectSqlCondition(
			$this->getDatabaseProvider()->getReplicaDatabase(), 'lt_namespace', 'lt_title'
		);
		return $query;
	}
}
