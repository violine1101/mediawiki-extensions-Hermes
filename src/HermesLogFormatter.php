<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Logging\LogFormatter;
use MediaWiki\WikiMap\WikiMap;

/**
 * Formats the "hermes" log of Special:HermesProjects, naming the affected wiki only if it isn't this one.
 */
class HermesLogFormatter extends LogFormatter {

	/** @inheritDoc */
	protected function getMessageKey() {
		$wiki = $this->entry->getParameters()[ '5::wiki' ] ?? null;
		return parent::getMessageKey() . ( $wiki === WikiMap::getCurrentWikiId() ? '-local' : '' );
	}
}
