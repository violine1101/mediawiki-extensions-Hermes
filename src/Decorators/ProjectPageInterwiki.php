<?php

namespace MediaWiki\Extension\Hermes\Decorators;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Interwiki\Interwiki;

// TODO: update doc

/**
 * An Interwiki entry for a translation project language prefix.
 *
 * Core's Title::getLocalURL() calls Interwiki::getURL() with the target's namespace and title
 * already joined into one string (e.g. "Category:Foo"), and Interwiki::getURL() only ever does
 * a one-shot "$1" substitution with that whole string.
 *
 * This function patches that behavior to insert the translation project prefix in the right place
 * (e.g. "Category:!xx:Foo" rather than "!xx:Category:Foo")
 */
class ProjectPageInterwiki extends Interwiki {

	public function __construct( string $prefix, string $url ) {
		parent::__construct( $prefix, $url, '', '', 1 );
	}

	/** @inheritDoc */
	public function getURL( $title = null ) {
		if ( $title === null ) {
			return parent::getURL( null );
		}

		$page = PageInfo::fromInterwiki( $this->mPrefix, $title );

		return parent::getURL( $page->getRealTitle() );
	}
}
