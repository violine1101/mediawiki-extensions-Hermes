<?php

namespace MediaWiki\Extension\Hermes\Tests\Auth;

use MediaWiki\Extension\Hermes\Auth\ReservedUsernamePreAuthenticationProvider;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Auth\ReservedUsernamePreAuthenticationProvider
 */
class ReservedUsernamePreAuthenticationProviderTest extends MediaWikiIntegrationTestCase {

	/** @dataProvider provideNames */
	public function testTestUserForCreation( string $name, bool $reserved ) {
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $name, 'creatable' )
			?? $this->getServiceContainer()->getUserFactory()->newFromName( $name, 'valid' );
		$this->assertNotNull( $user, $name );

		$status = ( new ReservedUsernamePreAuthenticationProvider() )->testUserForCreation( $user, false );
		$this->assertSame( !$reserved, $status->isGood(), $name );
	}

	public static function provideNames(): array {
		return [
			'project prefix' => [ '!eo:Foo', true ],
			'escape prefix' => [ '!:Foo', true ],
			'unregistered code' => [ '!zz:Foo', true ],
			'bare exclamation mark' => [ '!Foo', false ],
			'exclamation mark, colon later' => [ '!Foo:Bar', true ],
			'plain' => [ 'Foo', false ],
			'colon elsewhere' => [ 'Foo:Bar', false ],
		];
	}
}
