<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;

/**
 * The title syntax of translation projects: "!xx:Foo" for project pages, and "!:Foo" as an escape to
 * the base page "Foo".
 */
final class ProjectPrefix {

	/**
	 * Extracts a local title's translation project prefix.
	 *
	 * @param string $baseTitle The title without namespace, already normalized by core
	 *   (e.g. from Title::getText() or getDBkey())
	 * @return array{0: ?Project, 1: string} A tuple of (translation project, title in DB key form).
	 * 	 If the project is null, this article is not part of a translation project,
	 *   either because it doesn't have a "!xx:" prefix, or because this wiki has no translation project
	 *   in that language.
	 */
	public static function parse( string $baseTitle ): array {
		$baseTitle = strtr( $baseTitle, ' ', '_' );

		if ( preg_match( '/^!([a-z0-9-]+):(.+)$/', $baseTitle, $match ) ) {
			$project = ProjectStore::getProject( $match[ 1 ], WikiMap::getCurrentWikiId() );
			if ( $project !== null && !$project->isBase ) {
				return [ $project, $match[ 2 ] ];
			}
		}

		return [ null, $baseTitle ];
	}

	/** The title a "!:Foo" escape to the base page refers to ("Foo"), or null if $title isn't one. */
	public static function unescape( string $title ): ?string {
		return str_starts_with( $title, '!:' ) ? substr( $title, 2 ) : null;
	}

	/** Whether $title (without namespace) has the "!...:" shape reserved for translation projects. */
	public static function isReserved( string $title ): bool {
		return (bool)preg_match( '/^!.*:/', $title );
	}

	/**
	 * SQL condition for titles that aren't translation-project pages: not isReserved(), or in a namespace
	 * without projects.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $namespace Namespace field
	 * @param string $title Title field, without namespace
	 */
	public static function notProjectSqlCondition(
		IReadableDatabase $dbr, string $namespace, string $title
	): IExpression {
		$reserved = new LikeValue( '!', $dbr->anyString(), ':', $dbr->anyString() );
		return $dbr->expr( $title, IExpression::NOT_LIKE, $reserved )
			->or( $namespace, '=', Hermes::NON_PROJECT_NAMESPACES );
	}

	/**
	 * SQL condition for titles in the translation projects in one of $languages ("!xx:Foo").
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $title Title field, without namespace
	 * @param string[] $languages Not empty
	 */
	public static function projectSqlCondition( IReadableDatabase $dbr, string $title, array $languages ): IExpression {
		return $dbr->orExpr( array_map(
			static fn ( string $language ) => $dbr->expr(
				$title, IExpression::LIKE, new LikeValue( "!$language:", $dbr->anyString() )
			),
			$languages
		) );
	}
}
