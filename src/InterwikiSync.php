<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Keeps this wiki's interwiki table in line with the other wikis in the family: one prefix per
 * other wiki's base language (e.g. "de"), so [[de:Golem]] links to Golem there. Translation
 * projects get none, since an interwiki URL can't put "!xx:" after the namespace.
 *
 * Rows whose iw_wikiid is a registered Hermes wiki count as Hermes's own: those are updated and
 * removed as needed. Other rows are never touched; a prefix they already use is a conflict.
 * Interlanguage links are not wanted from these prefixes, so wikis should set
 * $wgInterwikiMagic = false.
 */
class InterwikiSync {

	public const ADDED = 'added';
	public const UPDATED = 'updated';
	public const REMOVED = 'removed';
	public const CONFLICT = 'conflict';

	/**
	 * @param bool $dryRun Only report what would change.
	 * @return array<string, string> What changed (one of the constants above), keyed by prefix.
	 */
	public static function sync( bool $dryRun = false ): array {
		$targets = [];
		foreach ( ProjectStore::getActiveBaseProjects() as $code => $project ) {
			if ( !$project->isLocal() && $project->urlTemplate !== null ) {
				$targets[ $code ] = [ 'iw_url' => $project->urlTemplate, 'iw_wikiid' => $project->wiki ];
			}
		}

		$services = MediaWikiServices::getInstance();
		$dbw = $services->getConnectionProvider()->getPrimaryDatabase();
		$hermesWikis = ProjectStore::getWikiIds();
		$conds = [ 'iw_wikiid' => $hermesWikis ];
		if ( $targets ) {
			$conds = $dbw->orExpr( [ 'iw_prefix' => array_keys( $targets ), 'iw_wikiid' => $hermesWikis ] );
		}
		$rows = $dbw->newSelectQueryBuilder()
			->select( [ 'iw_prefix', 'iw_url', 'iw_wikiid' ] )
			->from( 'interwiki' )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

		$changes = [];
		$missing = $targets;
		foreach ( $rows as $row ) {
			$target = $targets[ $row->iw_prefix ] ?? null;
			unset( $missing[ $row->iw_prefix ] );
			if ( !in_array( $row->iw_wikiid, $hermesWikis, true ) ) {
				$changes[ $row->iw_prefix ] = self::CONFLICT;
			} elseif ( $target === null ) {
				$changes[ $row->iw_prefix ] = self::REMOVED;
			} elseif ( $target['iw_url'] !== $row->iw_url || $target['iw_wikiid'] !== $row->iw_wikiid ) {
				$changes[ $row->iw_prefix ] = self::UPDATED;
			}
		}
		$changes += array_fill_keys( array_keys( $missing ), self::ADDED );

		if ( !$dryRun ) {
			foreach ( $changes as $prefix => $change ) {
				self::apply( $dbw, $prefix, $change, $targets[ $prefix ] ?? null );
				$services->getInterwikiLookup()->invalidateCache( $prefix );
			}
		}

		ksort( $changes );
		return $changes;
	}

	private static function apply( IDatabase $dbw, string $prefix, string $change, ?array $target ): void {
		if ( $change === self::ADDED ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'interwiki' )
				->row( [ 'iw_prefix' => $prefix, 'iw_api' => '', 'iw_local' => 1, 'iw_trans' => 0 ] + $target )
				->caller( __METHOD__ )
				->execute();
		} elseif ( $change === self::UPDATED ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'interwiki' )
				->set( $target )
				->where( [ 'iw_prefix' => $prefix ] )
				->caller( __METHOD__ )
				->execute();
		} elseif ( $change === self::REMOVED ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'interwiki' )
				->where( [ 'iw_prefix' => $prefix ] )
				->caller( __METHOD__ )
				->execute();
		}
	}
}
