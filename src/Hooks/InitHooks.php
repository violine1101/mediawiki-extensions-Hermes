<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use Wikimedia\Rdbms\IMaintainableDatabase;

class InitHooks implements LoadExtensionSchemaUpdatesHook, UnitTestsAfterDatabaseSetupHook, BeforeInitializeHook {

	// Any table would do here; this only needs to detect whether Hermes has *any* schema yet.
	private const SENTINEL_TABLE = 'hermes_tags';

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ) {
		// Ensure this wiki's base language is registered.
		LanguageStore::init();
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$type = $updater->getDB()->getType();
		$sqlDir = __DIR__ . "/../../sql/{$type}";

		// Register Hermes database schema on new install.
		// Hermes isn't deployed anywhere yet, so beyond this, there's no upgrade path to worry about.
		$updater->addExtensionUpdateOnVirtualDomain(
			[ 'virtual-hermes', 'addTable', self::SENTINEL_TABLE, "{$sqlDir}/tables-generated.sql", true ]
		);
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
		if ( $database->tableExists( self::SENTINEL_TABLE, __METHOD__ ) ) {
			return;
		}

		$type = $database->getType();
		$database->sourceFile( __DIR__ . "/../../sql/{$type}/tables-generated.sql" );
	}
}
