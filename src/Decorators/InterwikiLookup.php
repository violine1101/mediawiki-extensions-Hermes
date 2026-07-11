<?php

namespace MediaWiki\Extension\Hermes\Decorators;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Interwiki\Interwiki;
use MediaWiki\Interwiki\InterwikiLookup as IInterwikiLookup;

/**
 * Decorates the core InterwikiLookup service, resolving prefixes registered via
 * LanguageStore::setLanguage() against the wikis configured in $wgHermesWikis,
 * before falling back to the wrapped lookup.
 * Registered as a service manipulator on "InterwikiLookup" by Hooks\LanguageLinksHooks.
 */
class InterwikiLookup implements IInterwikiLookup {

	private IInterwikiLookup $inner;

	/** @var array<string,string> Map of wiki ID => URL template, from $wgHermesWikis. */
	private array $wikis;

	/**
	 * @param IInterwikiLookup $inner
	 * @param array<string,string> $wikis Map of wiki ID => URL template, from $wgHermesWikis.
	 */
	public function __construct( IInterwikiLookup $inner, array $wikis ) {
		$this->inner = $inner;
		$this->wikis = $wikis;
	}

	private function getUrlTemplate( string $prefix ): ?string {
		$wiki = LanguageStore::getWikiForLanguage( $prefix );

		return $wiki === null ? null : ( $this->wikis[ $wiki ] ?? null );
	}

	/** @inheritDoc */
	public function isValidInterwiki( $prefix ) {
		if ( $prefix === null || $prefix === '' ) {
			return false;
		}

		return $this->getUrlTemplate( $prefix ) !== null || $this->inner->isValidInterwiki( $prefix );
	}

	/** @inheritDoc */
	public function fetch( $prefix ) {
		if ( $prefix === null || $prefix === '' ) {
			// Matches ClassicInterwikiLookup::fetch(): every local URL is resolved through
			// here with an empty prefix, so this must stay a cheap no-DB-query early return.
			return null;
		}

		$url = $this->getUrlTemplate( $prefix );
		if ( $url === null ) {
			return $this->inner->fetch( $prefix );
		}

		return new Interwiki( $prefix, $url, '', '', 1 );
	}

	/** @inheritDoc */
	public function getAllPrefixes( $local = null ) {
		return $this->inner->getAllPrefixes( $local );
	}

	/** @inheritDoc */
	public function invalidateCache( $prefix ) {
		$this->inner->invalidateCache( $prefix );
	}
}
