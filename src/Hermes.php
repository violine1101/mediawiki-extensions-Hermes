<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;

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

	/**
	 * SQL condition excluding translation-project titles ("!xx:Foo", or any "!...:" title reserved for them),
	 * for queries listing link targets.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string|int $namespace Namespace field, or a fixed namespace
	 * @param string $title Title field
	 * @return IExpression|null Null if a fixed $namespace has no translation projects
	 */
	public static function notProjectTitle(
		IReadableDatabase $dbr, string|int $namespace, string $title
	): ?IExpression {
		$notReserved = $dbr->expr(
			$title, IExpression::NOT_LIKE, new LikeValue( '!', $dbr->anyString(), ':', $dbr->anyString() )
		);
		if ( is_int( $namespace ) ) {
			return self::isProjectNamespace( $namespace ) ? $notReserved : null;
		}
		return $notReserved->or( $namespace, '=', self::NON_PROJECT_NAMESPACES );
	}

	/** A language's autonym, capitalized as in the interlanguage links box; '' if unknown. */
	public static function getAutonym( string $code ): string {
		$services = MediaWikiServices::getInstance();
		// Without locale-specific ucfirst overrides, like core (T294695).
		return $services->getLanguageFactory()->getLanguage( 'en' )
			->ucfirst( $services->getLanguageNameUtils()->getLanguageName( $code ) );
	}

	public static function getDB( int $mode = DB_REPLICA ): IDatabase {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $mode === DB_PRIMARY
			? $lbFactory->getPrimaryDatabase( self::VIRTUAL_DOMAIN )
			: $lbFactory->getReplicaDatabase( self::VIRTUAL_DOMAIN );
	}
}
