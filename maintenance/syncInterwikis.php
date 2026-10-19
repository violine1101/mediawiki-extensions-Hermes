<?php

namespace MediaWiki\Extension\Hermes\Maintenance;

use Maintenance;
use MediaWiki\Extension\Hermes\InterwikiSync;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Adds an interwiki prefix to this wiki for every other wiki in the family (see InterwikiSync).
 * Run it on each wiki after adding a wiki to the family.
 */
class SyncInterwikis extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Hermes' );
		$this->addDescription(
			'Adds an interwiki prefix for the base language of every other wiki registered with Hermes, ' .
				'and updates or removes the ones added before.'
		);
		$this->addOption( 'dry-run', 'Only show what would change.' );
	}

	public function execute() {
		$dryRun = $this->hasOption( 'dry-run' );
		$changes = InterwikiSync::sync( $dryRun );
		if ( !$changes ) {
			$this->output( "Interwiki prefixes are up to date.\n" );
			return;
		}

		foreach ( $changes as $prefix => $change ) {
			$line = match ( $change ) {
				InterwikiSync::ADDED => "Added \"$prefix\"",
				InterwikiSync::UPDATED => "Updated \"$prefix\"",
				InterwikiSync::REMOVED => "Removed \"$prefix\"",
				InterwikiSync::CONFLICT => "Skipped \"$prefix\": already used by an interwiki not added by Hermes",
			};
			$this->output( ( $dryRun ? '[dry run] ' : '' ) . "$line\n" );
		}
	}
}

$maintClass = SyncInterwikis::class;
require_once RUN_MAINTENANCE_IF_MAIN;
