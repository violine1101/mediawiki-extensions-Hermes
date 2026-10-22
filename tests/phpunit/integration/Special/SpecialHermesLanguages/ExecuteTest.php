<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesLanguages;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialHermesLanguages
 * @covers \MediaWiki\Extension\Hermes\Special\TagQuery::countPages
 */
class ExecuteTest extends HermesIntegrationTestCase {

	private string $wiki;

	protected function setUp(): void {
		parent::setUp();
		$this->wiki = WikiMap::getCurrentWikiId();
		$this->registerBaseLanguage( 'dewiki', 'de' );
		$this->registerWiki( 'dewiki', 'https://de.example/wiki/$1' );
	}

	private function execute( ?Authority $performer = null ): string {
		return $this->executeRequest( new FauxRequest(), $performer );
	}

	private function executeRequest( FauxRequest $request, ?Authority $performer = null ): string {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesLanguages' );
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage( $page, null, $request, null, $performer );
		return $html;
	}

	/** Executes a POST request with a valid token as a Hermes admin. */
	private function post( array $data ): string {
		$request = new FauxRequest( $data, true );
		$request->setVal( 'wpEditToken', ( new CsrfTokenSet( $request ) )->getToken()->toString() );
		return $this->executeRequest( $request, $this->getAdmin() );
	}

	private function getAdmin(): Authority {
		$this->setGroupPermissions( 'sysop', 'hermes-add-translation-project', true );
		return $this->getTestSysop()->getAuthority();
	}

	/** @return string[] The table's rows, cells separated by " | " */
	private static function rows( string $html ): array {
		preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/s', $html, $rows );
		return array_map( static function ( $row ) {
			preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/s', $row, $cells );
			return implode( ' | ', array_map( static fn ( $cell ) => trim( strip_tags( $cell ) ), $cells[ 1 ] ) );
		}, $rows[ 1 ] );
	}

	public function testListsLanguages() {
		$this->registerProjectLanguage( $this->wiki, 'fr' );
		$this->registerProjectLanguage( $this->wiki, 'eo' );
		// Several tags on one page, and several pages: count pages, not tags.
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a', 'b' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'B' ), Tag::fromArgs( [ 'a' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!eo:C' ), Tag::fromArgs( [ 'a' ] ) );

		$this->assertSame( [
			'(hermeslanguages-header-wiki) | (hermeslanguages-header-language) | (hermeslanguages-header-pages)',
			'dewiki | Deutsch (de) | 2',
			"{$this->wiki} | English (en) | 0",
			'Esperanto (eo) | 1',
			'Français (fr) | 0',
		], self::rows( $this->execute() ) );
	}

	public function testLinks() {
		$this->registerProjectLanguage( 'dewiki', 'eo' );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a' ] ) );

		$html = $this->execute();
		$this->assertMatchesRegularExpression( '/<a href="[^"]*Special:HermesPages\/de"[^>]*>1<\/a>/', $html );
		$this->assertStringContainsString(
			'<th scope="rowgroup" rowspan="2"><a href="https://de.example/wiki/" class="extiw">dewiki</a></th>', $html
		);
	}

	public function testNoEditingWithoutRight() {
		$html = $this->execute();
		$this->assertStringNotContainsString( 'name="add-language"', $html );

		$request = new FauxRequest( [ 'add-language' => 'eo' ], true );
		$request->setVal( 'wpEditToken', ( new CsrfTokenSet( $request ) )->getToken()->toString() );
		$this->executeRequest( $request );
		$this->assertArrayNotHasKey( 'eo', LanguageStore::getLanguages() );
	}

	public function testAddRow() {
		$this->registerProjectLanguage( 'dewiki', 'es' );
		$this->registerProjectLanguage( $this->wiki, 'eo' );

		$html = $this->execute( $this->getAdmin() );
		$this->assertMatchesRegularExpression(
			"/<th scope=\"rowgroup\" rowspan=\"3\">[^<]*<a [^>]*>{$this->wiki}<\/a><\/th>/", $html
		);
		$this->assertStringContainsString( '<th scope="rowgroup" rowspan="2">', $html );
		$this->assertMatchesRegularExpression(
			'/<tr><td colspan="2"><form [^>]*>.*name=.add-language.*<\/form><\/td><\/tr>\s*<\/tbody>/s', $html
		);
		$this->assertStringContainsString( 'oo-ui-dropdownInputWidget', $html );
		$this->assertSame( 2, substr_count( $html, '<tr class="ext-hermes-base-language">' ) );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertContains( 'fr', $options[ 1 ] );
		$this->assertContains( '', $options[ 1 ] );
		foreach ( [ 'de', 'en', 'eo', 'es', 'xx-nope' ] as $taken ) {
			$this->assertNotContains( $taken, $options[ 1 ] );
		}
	}

	public function testAddProjectLanguage() {
		$html = $this->post( [ 'add-language' => 'eo' ] );

		$this->assertSame( [ 'wiki' => $this->wiki, 'isBase' => false ], LanguageStore::getLanguages()[ 'eo' ] );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertNotContains( 'eo', $options[ 1 ] );
	}

	public function testAddUnknownLanguage() {
		$this->post( [ 'add-language' => 'xx-nope' ] );

		$this->assertArrayNotHasKey( 'xx-nope', LanguageStore::getLanguages() );
	}

	public static function provideRegistered() {
		return [ 'base language' => [ 'de', 'dewiki' ], 'project' => [ 'es', 'dewiki' ] ];
	}

	/** @dataProvider provideRegistered */
	public function testAddRegisteredLanguage( string $language, string $wiki ) {
		$this->registerProjectLanguage( 'dewiki', 'es' );

		$this->post( [ 'add-language' => $language ] );

		$this->assertSame( $wiki, LanguageStore::getWikiForLanguage( $language ) );
	}

	public function testAddNeedsToken() {
		$html = $this->executeRequest(
			new FauxRequest( [ 'add-language' => 'eo', 'wpEditToken' => 'bogus' ], true ),
			$this->getAdmin()
		);

		$this->assertStringContainsString( '(sessionfailure)', $html );
		$this->assertArrayNotHasKey( 'eo', LanguageStore::getLanguages() );
	}
}
