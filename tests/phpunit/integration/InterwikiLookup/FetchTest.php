<?php

namespace MediaWiki\Extension\Hermes\Tests\InterwikiLookup;

use MediaWiki\Extension\Hermes\Decorators\InterwikiLookup;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Interwiki\InterwikiLookup as IInterwikiLookup;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Decorators\InterwikiLookup::fetch
 */
class FetchTest extends HermesIntegrationTestCase {

	private static function newLookup( IInterwikiLookup $inner, array $wikis ): InterwikiLookup {
		return new InterwikiLookup( $inner, $wikis );
	}

	public function testResolvesRegisteredLanguage() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = self::newLookup( $inner, [ 'dewiki' => 'https://de.example.org/wiki/$1' ] );

		$iw = $lookup->fetch( 'de' );

		$this->assertNotFalse( $iw );
		$this->assertNotNull( $iw );
		$this->assertSame( 'https://de.example.org/wiki/Foo', $iw->getURL( 'Foo' ) );
	}

	public function testDefersForUnregisteredPrefix() {
		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'unregistered_prefix' )->willReturn( false );

		$lookup = self::newLookup( $inner, [] );

		$this->assertFalse( $lookup->fetch( 'unregistered_prefix' ) );
	}

	public function testDefersWhenWikiNotConfigured() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'de' )->willReturn( false );

		// "dewiki" is registered for "de", but not present in $wgHermesWikis.
		$lookup = self::newLookup( $inner, [] );

		$this->assertFalse( $lookup->fetch( 'de' ) );
	}
}
