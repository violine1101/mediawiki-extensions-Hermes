<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Deferred\LinksUpdate\CategoryLinksTable;
use MediaWiki\Page\LinkBatchFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Special:WantedCategoryTranslations: wanted "Category:!xx:" categories (see WantedTranslationsPage), by
 * number of members.
 */
class SpecialWantedCategoryTranslations extends WantedTranslationsPage {

	public function __construct( IConnectionProvider $dbProvider, LinkBatchFactory $linkBatchFactory ) {
		parent::__construct( 'WantedCategoryTranslations' );
		$this->setDatabaseProvider( $dbProvider );
		$this->setLinkBatchFactory( $linkBatchFactory );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		return [
			'tables' => [ 'categorylinks', 'linktarget', 'target' => 'page' ],
			'fields' => [ 'namespace' => NS_CATEGORY, 'title' => 'lt_title', 'value' => 'COUNT(*)' ],
			'conds' => [ 'target.page_title' => null, $this->inProject( $dbr, 'lt_title' ) ],
			'options' => [ 'GROUP BY' => 'lt_title' ],
			'join_conds' => [
				'linktarget' => [ 'JOIN', 'cl_target_id = lt_id' ],
				'target' => [ 'LEFT JOIN', [ 'target.page_title = lt_title', 'target.page_namespace' => NS_CATEGORY ] ],
			],
		];
	}

	/** @inheritDoc */
	protected function showNamespace(): bool {
		return false;
	}

	/**
	 * Members rather than links, like Special:WantedCategories.
	 *
	 * @inheritDoc
	 */
	protected function makeWlhLink( $title, $result ) {
		return $this->msg( 'nmembers' )->numParams( $result->value )->escaped();
	}

	/** @inheritDoc */
	protected function getRecacheDB() {
		return $this->getDatabaseProvider()->getReplicaDatabase( CategoryLinksTable::VIRTUAL_DOMAIN, 'vslow' );
	}
}
