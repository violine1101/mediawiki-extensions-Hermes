<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class Hermes {

	private const VIRTUAL_DOMAIN = 'virtual-hermes';

	public static function getDB( int $mode = DB_REPLICA ): IDatabase {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $mode === DB_PRIMARY
			? $lbFactory->getPrimaryDatabase( self::VIRTUAL_DOMAIN )
			: $lbFactory->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}

	/**
	 * Collapses runs of whitespace and/or underscores into a single underscore,
	 * and trims leading/trailing underscores.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function normalizeWhitespace( string $text ): string {
		$text = preg_replace( '/[\s_]+/', '_', $text ) ?? '';
		return trim( $text, '_' );
	}

	/**
	 * Normalizes a section name.
	 *
	 * @param ?string $section The non-normalized section, or null if there is none.
	 * @return ?string The normalized section: trimmed and underscores for whitespace.
	 *   Case is preserved, since HTML anchors are case-sensitive.
	 */
	public static function normalizeSection( ?string $section ): ?string {
		if ( $section === null ) {
			return null;
		}
		$section = self::normalizeWhitespace( $section );
		return $section === '' ? null : $section;
	}
}
