<?php

namespace MediaWiki\Extension\Hermes\Maintenance;

use Maintenance;
use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\WikiMap\WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Adds a translation project to the current wiki in the shared hermes_projects table. The wiki's
 * base project doesn't need this script - it's set directly from $wgLanguageCode and mirrored into
 * the table automatically on first use.
 */
class AddTranslationProject extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Hermes' );
		$this->addDescription(
			'Adds a translation project to this wiki in the shared hermes_projects table.'
		);
		$this->addOption( 'language', 'Language code for this translation project.', true, true );
	}

	public function execute() {
		$language = $this->getOption( 'language' );
		$wiki = WikiMap::getCurrentWikiId();

		try {
			$isNew = ProjectStore::addTranslationProject( $wiki, $language );
		} catch ( LanguageConflictException $e ) {
			$this->fatalError( $e->getMessage() );
		}

		$activeWiki = ProjectStore::getWikiForLanguage( $language );
		if ( $isNew && $activeWiki !== $wiki ) {
			$this->output( "Added translation project \"$language\" to this wiki ($wiki), inactive, as the " .
				"project on $activeWiki is active.\n" );
		} elseif ( $isNew ) {
			$this->output( "Added translation project \"$language\" to this wiki ($wiki).\n" );
		} else {
			$this->output( "This wiki ($wiki) already has the translation project \"$language\".\n" );
		}
	}
}

$maintClass = AddTranslationProject::class;
require_once RUN_MAINTENANCE_IF_MAIN;
