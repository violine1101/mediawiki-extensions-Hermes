<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::fromInterwiki
 */
class FromInterwikiTest extends HermesIntegrationTestCase {

	public function testUnregisteredPrefixMainNamespace() {
		$info = PageInfo::fromInterwiki( 'xx', 'Foo' );

		$this->assertSame( NS_MAIN, $info->title->namespace );
		$this->assertSame( '', $info->title->getNamespaceText() );
		$this->assertSame( 'Foo', $info->title->baseTitle );
		$this->assertNull( $info->title->project );
	}

	public function testUnregisteredPrefixNamespacedTitle() {
		$info = PageInfo::fromInterwiki( 'xx', 'Category:Foo' );

		$this->assertSame( NS_CATEGORY, $info->title->namespace );
		$this->assertSame( 'Category', $info->title->getNamespaceText() );
		$this->assertSame( 'Foo', $info->title->baseTitle );
	}

	public function testUnparseableTitleFallsBackToLiteralText() {
		// "|" is not a legal title character, so parsing fails; fromInterwiki() falls back to
		// treating the whole string as the (unsplit) title rather than erroring.
		$info = PageInfo::fromInterwiki( 'xx', 'Invalid|Title' );

		$this->assertSame( NS_MAIN, $info->title->namespace );
		$this->assertSame( '', $info->title->getNamespaceText() );
		$this->assertSame( 'Invalid|Title', $info->title->baseTitle );
		$this->assertNull( $info->title->project );
	}

	public function testNormalizesUnderscoreRunsInTitle() {
		$info = PageInfo::fromInterwiki( 'xx', 'Foo___Bar' );

		$this->assertSame( 'Foo_Bar', $info->title->baseTitle );
	}

	public function testExtractsSection() {
		$info = PageInfo::fromInterwiki( 'xx', 'Category:Foo#Some_Section' );

		$this->assertSame( 'Foo', $info->title->baseTitle );
		$this->assertSame( 'Some_Section', $info->title->section );
	}

	public function testWithoutSectionIsNull() {
		$info = PageInfo::fromInterwiki( 'xx', 'Foo' );

		$this->assertNull( $info->title->section );
	}

	public function testBaseLanguagePrefixDoesNotSetTranslationProject() {
		$this->registerBaseLanguage( 'dewiki', 'de' );

		$info = PageInfo::fromInterwiki( 'de', 'Foo' );

		$this->assertNull( $info->title->project );
	}

	public function testSetsWikiOfPrefix() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );

		$this->assertSame( 'trwiki', PageInfo::fromInterwiki( 'eo', 'Foo' )->title->wiki );
		$this->assertNull( PageInfo::fromInterwiki( 'zz', 'Foo' )->title->wiki );
	}

	public function testProjectLanguagePrefixSetsTranslationProject() {
		$this->registerProjectLanguage( 'trwiki', 'eo' );

		$info = PageInfo::fromInterwiki( 'eo', 'Foo' );

		$this->assertSame( 'eo', $info->title->project );
	}

	public function testDuplicateProjectLanguagePrefix() {
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'he' );

		$info = PageInfo::fromInterwiki( 'eo', '!he:Foo' );

		$this->assertSame( 'eo', $info->title->project );
		$this->assertSame( '!he:Foo', $info->title->baseTitle );
	}

	public function testUsesHostWikisNamespaceConvention() {
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerProjectLanguage( 'dewiki', 'eo' );

		// "Kategorie" is German for NS_CATEGORY - recognized here because "eo" is a project
		// language of "dewiki", so the namespace is resolved against German namespace names,
		// not this (English) test wiki's own.
		$info = PageInfo::fromInterwiki( 'eo', 'Kategorie:Foo' );

		$this->assertSame( NS_CATEGORY, $info->title->namespace );
		$this->assertSame( 'Kategorie', $info->title->getNamespaceText() );
		$this->assertSame( 'Foo', $info->title->baseTitle );
	}

	public function testWithoutHostBaseLanguageUsesCurrentWikiNamespaces() {
		// "trwiki" never registered its own base language, so getBaseLanguageForWiki() returns
		// null and namespace parsing falls back English.
		$this->registerProjectLanguage( 'trwiki', 'eo' );

		$info = PageInfo::fromInterwiki( 'eo', 'Category:Foo' );

		$this->assertSame( NS_CATEGORY, $info->title->namespace );
		$this->assertSame( 'Category', $info->title->getNamespaceText() );
	}
}
