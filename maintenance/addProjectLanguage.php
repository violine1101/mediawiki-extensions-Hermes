<?php

namespace MediaWiki\Extension\Hermes\Maintenance;

use Maintenance;
use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\WikiMap\WikiMap;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Registers an additional translation-project language for the current wiki in the shared
 * hermes_languages table. The wiki's base language doesn't need this script - it's set
 * directly from $wgLanguageCode and mirrored into the table automatically on first use.
 */
class AddProjectLanguage extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Hermes' );
		$this->addDescription(
			'Registers an additional translation project language on this wiki in the shared ' .
				'hermes_languages table.'
		);
		$this->addOption( 'language', 'Language code for this translation project.', true, true );
	}

	public function execute() {
		$language = $this->getOption( 'language' );
		$wiki = WikiMap::getCurrentWikiId();

		try {
			$isNew = LanguageStore::addProjectLanguage( $wiki, $language );
		} catch ( LanguageConflictException $e ) {
			$this->fatalError( $e->getMessage() );
		}

		if ( $isNew ) {
			$this->output( "Registered translation project \"$language\" on this wiki ($wiki).\n" );
		} else {
			$this->output( "Translation project \"$language\" is already registered on this wiki ($wiki).\n" );
		}
	}
}

$maintClass = AddProjectLanguage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
