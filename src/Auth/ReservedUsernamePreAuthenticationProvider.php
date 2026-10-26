<?php

namespace MediaWiki\Extension\Hermes\Auth;

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Extension\Hermes\ProjectPrefix;
use StatusValue;

/** Rejects account names with the "!...:" shape reserved for translation projects (like page titles). */
class ReservedUsernamePreAuthenticationProvider extends AbstractPreAuthenticationProvider {

	/** @inheritDoc */
	public function testUserForCreation( $user, $autocreate, array $options = [] ) {
		return self::isReserved( $user->getName() )
			? StatusValue::newFatal( 'hermes-reserved-username' )
			: StatusValue::newGood();
	}

	public static function isReserved( string $username ): bool {
		return ProjectPrefix::isReserved( $username );
	}
}
