<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectPageInterwiki;

use MediaWiki\Extension\Hermes\Decorators\ProjectPageInterwiki;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Decorators\ProjectPageInterwiki::getURL
 */
class GetURLTest extends HermesIntegrationTestCase {

	public function testMainNamespaceTitle() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		$this->assertSame( 'https://translate.example.org/wiki/!eo:Foo', $iw->getURL( 'Foo' ) );
	}

	public function testNamespacedTitle() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		// The "!xx:" prefix lands after the namespace, not before it.
		$this->assertSame( 'https://translate.example.org/wiki/Category:!eo:Foo', $iw->getURL( 'Category:Foo' ) );
	}

	public function testNoTitle() {
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		$this->assertSame( 'https://translate.example.org/wiki/$1', $iw->getURL() );
	}

	public function testUnparseableTitleFallsBackToLiteralText() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		// "|" is not a legal title character, so parsing fails; getURL() falls back to treating
		// the whole string as the (unsplit) base title rather than erroring.
		$this->assertSame( 'https://translate.example.org/wiki/!eo:Invalid%7CTitle', $iw->getURL( 'Invalid|Title' ) );
	}

	public function testMultiWordTitleUsesUnderscoresNotPlusSigns() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		// Interwiki::getURL() is always called with dbkey (underscore) form, per how
		// Title::getLocalURL() builds it - wfUrlencode() alone doesn't turn "_" into anything,
		// but it would turn a literal space into "+", which isn't the conventional MediaWiki
		// URL form.
		$this->assertSame( 'https://translate.example.org/wiki/!eo:Iron_Golem', $iw->getURL( 'Iron_Golem' ) );
	}

	public function testUsesHostWikisNamespaceConvention() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerProjectLanguage( 'dewiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://de.example.org/wiki/$1' );

		// "Kategorie" is German for NS_CATEGORY - recognized here because "eo" is a project
		// language of "dewiki", so it's parsed against German namespace names, not this
		// (English) test wiki's own.
		$this->assertSame( 'https://de.example.org/wiki/Kategorie:!eo:Foo', $iw->getURL( 'Kategorie:Foo' ) );
	}

	public function testWithoutHostBaseLanguageUsesCurrentWikiNamespaces() {
		// "trwiki" never registered its own base language, so namespace parsing falls back to
		// English. "Kategorie" isn't recognized, so the whole string is treated as an unsplit
		// base title instead.
		$this->registerProjectLanguage( 'trwiki', 'eo' );
		$iw = new ProjectPageInterwiki( 'eo', 'https://translate.example.org/wiki/$1' );

		$this->assertSame( 'https://translate.example.org/wiki/!eo:Kategorie:Foo', $iw->getURL( 'Kategorie:Foo' ) );
	}
}
