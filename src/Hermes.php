<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

class Hermes {

	private const VIRTUAL_DOMAIN = 'virtual-hermes';

	/** Namespaces without translation projects: no per-language content, or no project fallback */
	public const NON_PROJECT_NAMESPACES = [ NS_FILE, NS_MEDIA, NS_FILE_TALK, NS_SPECIAL ];

	/** Namespaces where links to a missing "!xx:" page go to the base page instead of a red link */
	public const LINK_FALLBACK_NAMESPACES = [ NS_USER, NS_USER_TALK, NS_TEMPLATE, NS_TEMPLATE_TALK ];

	public static function isProjectNamespace( int $namespace ): bool {
		return !in_array( $namespace, self::NON_PROJECT_NAMESPACES, true );
	}

	public static function hasLinkFallback( int $namespace ): bool {
		return in_array( $namespace, self::LINK_FALLBACK_NAMESPACES, true );
	}

	public static function getDB( int $mode = DB_REPLICA ): IDatabase {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $mode === DB_PRIMARY
			? $lbFactory->getPrimaryDatabase( self::VIRTUAL_DOMAIN )
			: $lbFactory->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}
}
