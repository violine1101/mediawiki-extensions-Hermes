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
}
