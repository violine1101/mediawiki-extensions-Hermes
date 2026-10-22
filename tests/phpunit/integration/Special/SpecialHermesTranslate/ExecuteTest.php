<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesTranslate;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialHermesTranslate
 */
class ExecuteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerProjectLanguage( 'dewiki', 'es' );
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'fr' );
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
		$this->insertPage( 'Foo' );
	}

	/** @return array{0: string, 1: ?string, 2: string} HTML, redirect target and page title */
	private function execute( string $par, array $query = [] ): array {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesTranslate' );
		[ $html, $response ] = ( new SpecialPageExecutor() )
			->executeSpecialPage( $page, $par, new FauxRequest( $query ) );
		return [ $html, $response->getHeader( 'Location' ), $page->getOutput()->getPageTitle() ];
	}

	public function testRedirectsToEdit() {
		[ , $location ] = $this->execute( 'Foo', [ 'language' => 'eo', 'translation' => 'fooo bar' ] );

		$this->assertSame(
			Title::newFromText( '!eo:Fooo_bar' )->getFullURL( [ 'action' => 'edit', 'hermes-preload' => 'Foo' ] ),
			$location
		);
	}

	public function testExistingTranslation() {
		$this->insertPage( '!eo:Fooo' );

		[ $html, $location ] = $this->execute( 'Foo', [ 'language' => 'eo', 'translation' => 'Fooo' ] );

		$this->assertNull( $location );
		$this->assertStringContainsString( '(hermestranslate-exists: ', $html );
		$this->assertStringContainsString( "name='translation'", $html );
	}

	public static function provideInvalidTranslation() {
		return [ 'empty' => [ ' ' ], 'invalid' => [ 'a[b' ], 'reserved' => [ '!fr:Foo' ] ];
	}

	/** @dataProvider provideInvalidTranslation */
	public function testInvalidTranslation( string $translation ) {
		[ $html, $location ] = $this->execute( 'Foo', [ 'language' => 'eo', 'translation' => $translation ] );

		$this->assertNull( $location );
		$this->assertStringContainsString( '(hermestranslate-invalid-title: ', $html );
	}

	public function testForm() {
		[ $html, $location, $pageTitle ] = $this->execute( 'Foo' );

		$this->assertNull( $location );
		$this->assertSame( '(hermestranslate-title: Foo)', $pageTitle );
		$this->assertStringNotContainsString( 'oo-ui-fieldsetLayout-header', $html );
		// Hidden: the sub-page names it.
		$this->assertStringContainsString( 'name="page" type="hidden" value="Foo"', $html );
		$this->assertMatchesRegularExpression( "/name='translation' value='Foo'/", $html );
		$this->assertStringContainsString(
			"<option value='' selected='selected'>(hermestranslate-language-placeholder)</option>", $html
		);
		// Only this wiki's translation projects.
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertSame( [ '', 'eo', 'fr' ], $options[ 1 ] );
	}

	public function testPageFieldWithoutSubPage() {
		[ $html, , $pageTitle ] = $this->execute( '', [ 'page' => 'Foo' ] );

		$this->assertMatchesRegularExpression( "/name='page' value='Foo'/", $html );
		$this->assertSame( '(hermestranslate-title: Foo)', $pageTitle );

		[ $html, , $pageTitle ] = $this->execute( '' );

		$this->assertMatchesRegularExpression( "/name='page' value=''/", $html );
		$this->assertSame( '(hermestranslate)', $pageTitle );
	}

	public function testPreselectsLanguage() {
		[ $html ] = $this->execute( 'Foo', [ 'language' => 'fr' ] );

		$this->assertStringContainsString( "<option value='fr' selected='selected'>", $html );
	}

	public function testOnlyUntranslatedProjects() {
		// One translation with the same title, so it doesn't need tags.
		$this->insertPage( '!eo:Foo' );

		[ $html, $location ] = $this->execute( 'Foo', [ 'language' => 'eo', 'translation' => 'Fooo' ] );

		$this->assertNull( $location );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertSame( [ '', 'fr' ], $options[ 1 ] );
		$this->assertStringContainsString( "<option value='' selected='selected'>", $html );
	}

	public function testFallbackIsNoTranslation() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->insertPage( '!eo:Fooo' );
		$this->insertPage( '!fr:Fooo' );
		TagStore::setTagsForPage( $this->makePageInfo( $wiki, 'Foo' ), Tag::fromArgs( [ 'foo', 'bar' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $wiki, '!eo:Fooo' ), Tag::fromArgs( [ 'foo' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $wiki, '!fr:Fooo' ), Tag::fromArgs( [ 'bar' ] ) );

		[ $html ] = $this->execute( 'Foo' );

		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertSame( [ '', 'fr' ], $options[ 1 ] );
	}

	public function testAllTranslated() {
		$this->insertPage( '!eo:Foo' );
		$this->insertPage( '!fr:Foo' );

		[ $html ] = $this->execute( 'Foo' );

		$this->assertStringContainsString( '(hermestranslate-all-translated: Foo)', $html );
	}

	public function testLanguageFromPrefix() {
		[ $html ] = $this->execute( '!fr:Foo' );

		$this->assertStringContainsString( 'name="page" type="hidden" value="Foo"', $html );
		$this->assertStringContainsString( "<option value='fr' selected='selected'>", $html );
	}

	public function testOtherWikisLanguage() {
		[ $html, $location ] = $this->execute( 'Foo', [ 'language' => 'es', 'translation' => 'Foo' ] );

		$this->assertNull( $location );
		$this->assertStringContainsString( "name='translation'", $html );
	}

	public static function provideNoBasePage() {
		return [
			'missing' => [ 'Bar' ],
			'invalid' => [ '<>' ],
			'namespace without projects' => [ 'File:Foo.png' ],
		];
	}

	/** @dataProvider provideNoBasePage */
	public function testNoBasePage( string $par ) {
		[ $html, $location ] = $this->execute( $par, [ 'language' => 'eo', 'translation' => 'Foo' ] );

		$this->assertNull( $location );
		$this->assertStringContainsString( '(hermestranslate-no-base: ', $html );
	}

	public function testNoProjects() {
		Hermes::getDB( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'hermes_languages' )
			->where( [ 'hl_base' => 0 ] )
			->caller( __METHOD__ )
			->execute();
		LanguageStore::clearCacheForTesting();

		[ $html ] = $this->execute( 'Foo' );

		$this->assertStringContainsString( '(hermestranslate-no-projects)', $html );
		$this->assertStringNotContainsString( "name='translation'", $html );
	}
}
