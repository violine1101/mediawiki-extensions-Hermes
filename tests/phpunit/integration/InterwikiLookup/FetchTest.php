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

	public function testResolvesRegisteredLanguage() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerWiki( 'dewiki', 'https://de.example.org/wiki/$1' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = new InterwikiLookup( $inner );

		$iw = $lookup->fetch( 'de' );

		$this->assertNotFalse( $iw );
		$this->assertNotNull( $iw );
		$this->assertSame( 'https://de.example.org/wiki/Foo', $iw->getURL( 'Foo' ) );
	}

	public function testResolvesRegisteredProjectLanguage() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = new InterwikiLookup( $inner );

		$iw = $lookup->fetch( 'eo' );

		$this->assertNotFalse( $iw );
		$this->assertNotNull( $iw );
		// The "!xx:" prefix must be baked into the URL, not doubled up and not missing.
		$this->assertSame( 'https://translate.example.org/wiki/!eo:Foo', $iw->getURL( 'Foo' ) );
	}

	public function testResolvesRegisteredProjectLanguageForNamespacedPage() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$this->registerWiki( 'trwiki', 'https://translate.example.org/wiki/$1' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = new InterwikiLookup( $inner );

		$iw = $lookup->fetch( 'eo' );

		// Title::getLocalURL() passes getURL() the namespace and title already joined into one
		// string (e.g. "Category:Foo"); the "!xx:" prefix must land after the namespace, not
		// before it - "Category:!eo:Foo", not "!eo:Category:Foo".
		$this->assertSame( 'https://translate.example.org/wiki/Category:!eo:Foo', $iw->getURL( 'Category:Foo' ) );
	}

	public function testProjectLanguageUsesHostWikisNamespaceConvention() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerProjectLanguage( 'dewiki', 'eo' );
		$this->registerWiki( 'dewiki', 'https://de.example.org/wiki/$1' );

		$inner = $this->createNoOpMock( IInterwikiLookup::class );
		$lookup = new InterwikiLookup( $inner );

		$iw = $lookup->fetch( 'eo' );

		// "Kategorie" is German for NS_CATEGORY. "eo" is a project language of "dewiki" here,
		// so its namespace must resolve against German namespace names - not this (English)
		// wiki's own - since that's the convention the page was actually tagged under.
		$this->assertSame( 'https://de.example.org/wiki/Kategorie:!eo:Foo', $iw->getURL( 'Kategorie:Foo' ) );
	}

	public function testDefersForUnregisteredPrefix() {
		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'unregistered_prefix' )->willReturn( false );

		$lookup = new InterwikiLookup( $inner );

		$this->assertFalse( $lookup->fetch( 'unregistered_prefix' ) );
	}

	public function testDefersWhenWikiNotConfigured() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$inner = $this->createMock( IInterwikiLookup::class );
		$inner->method( 'fetch' )->with( 'de' )->willReturn( false );

		// "dewiki" is registered for "de", but not present in hermes_wikis.
		$lookup = new InterwikiLookup( $inner );

		$this->assertFalse( $lookup->fetch( 'de' ) );
	}
}
