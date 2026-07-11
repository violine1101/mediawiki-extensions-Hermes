<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\Decorators\InterwikiLookup;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Interwiki\InterwikiLookup as IInterwikiLookup;
use MediaWikiIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Decorators\InterwikiLookup
 */
class InterwikiLookupTest extends MediaWikiIntegrationTestCase {

	private static function newLookup( IInterwikiLookup $inner, array $wikis ): InterwikiLookup {
		return new InterwikiLookup( $inner, $wikis );
	}

	public function testFetchResolvesRegisteredLanguageWithoutConsultingInner() {
		LanguageStore::setLanguage( 'dewiki', 'de' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = self::newLookup( $inner, [ 'dewiki' => 'https://de.example.org/wiki/$1' ] );

		$iw = $lookup->fetch( 'de' );

		$this->assertNotFalse( $iw );
		$this->assertNotNull( $iw );
		$this->assertSame( 'https://de.example.org/wiki/Foo', $iw->getURL( 'Foo' ) );
	}

	public function testFetchDefersToInnerForUnregisteredPrefix() {
		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'unregistered_prefix' )->willReturn( false );

		$lookup = self::newLookup( $inner, [] );

		$this->assertFalse( $lookup->fetch( 'unregistered_prefix' ) );
	}

	public function testFetchDefersToInnerWhenWikiIsNotConfigured() {
		LanguageStore::setLanguage( 'dewiki', 'de' );

		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'de' )->willReturn( false );

		// "dewiki" is registered for "de", but not present in $wgHermesWikis.
		$lookup = self::newLookup( $inner, [] );

		$this->assertFalse( $lookup->fetch( 'de' ) );
	}

	public function testIsValidInterwikiTrueForRegisteredLanguage() {
		LanguageStore::setLanguage( 'frwiki', 'fr' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = self::newLookup( $inner, [ 'frwiki' => 'https://fr.example.org/wiki/$1' ] );

		$this->assertTrue( $lookup->isValidInterwiki( 'fr' ) );
	}
}
