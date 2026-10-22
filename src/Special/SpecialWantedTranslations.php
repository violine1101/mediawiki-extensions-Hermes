<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Deferred\LinksUpdate\PageLinksTable;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Linker\LinksMigration;
use MediaWiki\Page\LinkBatchFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Special:WantedTranslations: wanted "!xx:" pages (see WantedTranslationsPage), by number of links.
 */
class SpecialWantedTranslations extends WantedTranslationsPage {

	public function __construct(
		IConnectionProvider $dbProvider,
		LinkBatchFactory $linkBatchFactory,
		private readonly LinksMigration $linksMigration,
	) {
		parent::__construct( 'WantedTranslations' );
		$this->setDatabaseProvider( $dbProvider );
		$this->setLinkBatchFactory( $linkBatchFactory );
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		[ $namespace, $title ] = $this->linksMigration->getTitleFields( 'pagelinks' );
		$links = $this->linksMigration->getQueryInfo( 'pagelinks', 'pagelinks' );

		return [
			'tables' => [ ...$links['tables'], 'target' => 'page' ],
			'fields' => [ 'namespace' => $namespace, 'title' => $title, 'value' => 'COUNT(*)' ],
			'conds' => [
				'target.page_namespace' => null,
				// Links to missing "!xx:" pages there go to the base page instead (see LinkHooks).
				$dbr->expr(
					$namespace, '!=', [ ...Hermes::NON_PROJECT_NAMESPACES, ...Hermes::LINK_FALLBACK_NAMESPACES ]
				),
				$this->inProject( $dbr, $title ),
			],
			'options' => [ 'GROUP BY' => [ $namespace, $title ] ],
			'join_conds' => [
				...$links['joins'],
				'target' => [ 'LEFT JOIN', [ "target.page_namespace = $namespace", "target.page_title = $title" ] ],
			],
		];
	}

	/** @inheritDoc */
	protected function getRecacheDB() {
		return $this->getDatabaseProvider()->getReplicaDatabase( PageLinksTable::VIRTUAL_DOMAIN, 'vslow' );
	}
}
