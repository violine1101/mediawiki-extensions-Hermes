<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class InitHooks implements LoadExtensionSchemaUpdatesHook {

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		// TODO: ensure that the table is added to the correct database
		// i.e. the virtual database rather than the local one
		$type = $updater->getDB()->getType();
		$updater->addExtensionTable(
			'hermes_tags',
			__DIR__ . "/../sql/{$type}/tables-generated.sql"
		);
	}
}
