<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use Wikimedia\Rdbms\IMaintainableDatabase;

class InitHooks implements LoadExtensionSchemaUpdatesHook, UnitTestsAfterDatabaseSetupHook {

	private const TABLES = [ 'hermes_tags', 'hermes_languages' ];

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$sqlDir = __DIR__ . "/../../sql/{$type}";

		foreach ( self::TABLES as $table ) {
			$updater->addExtensionUpdateOnVirtualDomain(
				[ 'virtual-hermes', 'addTable', $table, "{$sqlDir}/tables-generated.sql", true ]
			);
		}
	}

	/**
	 * Hermes's tables live on the "virtual-hermes" domain, which MediaWiki's test
	 * infrastructure doesn't know how to clone (it only clones tables that already exist on
	 * the local connection, per T384238). $wgVirtualDomainsMapping is forced empty for tests,
	 * so "virtual-hermes" resolves to this same local connection; create the tables here so
	 * LanguageStore/TagStore find them.
	 *
	 * @param IMaintainableDatabase $database
	 * @param string $prefix
	 */
	public function onUnitTestsAfterDatabaseSetup( $database, $prefix ) {
		if ( $database->tableExists( self::TABLES[0], __METHOD__ ) ) {
			return;
		}

		$type = $database->getType();
		$database->sourceFile( __DIR__ . "/../../sql/{$type}/tables-generated.sql" );
	}
}
