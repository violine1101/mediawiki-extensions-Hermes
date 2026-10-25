<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesLanguages;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Languages\LanguageNameUtils;
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
		$this->activateLocalBaseLanguage();
		$this->registerBaseLanguage( 'dewiki', 'de', url: 'https://de.example/wiki/$1' );
	}

	private function execute( ?Authority $performer = null ): string {
		return $this->executeRequest( new FauxRequest(), $performer );
	}

	private function executeRequest( FauxRequest $request, ?Authority $performer = null ): string {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesLanguages' );
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage( $page, null, $request, null, $performer );
		return $html;
	}

	/** Executes a POST request with a valid token as a local, or global, Hermes admin. */
	private function post( array $data, bool $global = false ): string {
		$request = new FauxRequest( $data, true );
		$request->setVal( 'wpEditToken', ( new CsrfTokenSet( $request ) )->getToken()->toString() );
		return $this->executeRequest( $request, $this->getAdmin( $global ) );
	}

	/** A user in a group of its own, independent of which groups the wiki gives the Hermes rights. */
	private function getAdmin( bool $global = false ): Authority {
		$this->setGroupPermissions( [
			'hermes-test-admin' => [ 'hermes-local-admin' => true, 'hermes-global-admin' => $global ],
		] );
		return $this->getTestUser( [ 'hermes-test-admin' ] )->getAuthority();
	}

	/** @return string[] Wikis with an add form */
	private static function addForms( string $html ): array {
		preg_match_all( "/name='add-language'.*?value=\"([^\"]*)\" name=\"wiki\"/s", $html, $forms );
		return $forms[ 1 ];
	}

	/** @return string[] Actions of the hermes log's entries */
	private function getLogActions(): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'log_action' )
			->from( 'logging' )
			->where( [ 'log_type' => 'hermes' ] )
			->orderBy( 'log_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/** @return string[] The (de)activate buttons, as "<action> <code>@<wiki>" */
	private static function activeButtons( string $html ): array {
		preg_match_all(
			"/name='(activate|deactivate)-language' value='([^']*)'.*?value=\"([^\"]*)\" name=\"wiki\"/s",
			$html, $buttons, PREG_SET_ORDER
		);
		return array_map( static fn ( $m ) => "{$m[ 1 ]} {$m[ 2 ]}@{$m[ 3 ]}", $buttons );
	}

	/** @return string[] The table's rows, cells separated by " | ", highlighted (active) ones marked "*" */
	private static function rows( string $html ): array {
		preg_match_all( '/<tr([^>]*)>(.*?)<\/tr>/s', $html, $rows, PREG_SET_ORDER );
		return array_map( static function ( $row ) {
			preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/s', $row[ 2 ], $cells );
			return ( str_contains( $row[ 1 ], 'ext-hermes-active-language' ) ? '*' : '' )
				. implode( ' | ', array_map( static fn ( $cell ) => trim( strip_tags( $cell ) ), $cells[ 1 ] ) );
		}, $rows );
	}

	public function testListsLanguages() {
		$this->registerProjectLanguage( $this->wiki, 'fr' );
		$this->registerProjectLanguage( $this->wiki, 'eo' );
		// Several tags on one page, and several pages: count pages, not tags.
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a', 'b' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'B' ), Tag::fromArgs( [ 'a' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!eo:C' ), Tag::fromArgs( [ 'a' ] ) );

		$this->assertSame( [
			'(hermeslanguages-header-wiki) | (hermeslanguages-header-language) | (hermeslanguages-header-pages) | '
				. '(hermeslanguages-header-active)',
			'*dewiki | Deutsch (de) | 2 | (hermeslanguages-active-yes)',
			"*{$this->wiki} | English (en) | 0 | (hermeslanguages-active-yes)",
			'*Esperanto (eo) | 1 | (hermeslanguages-active-yes)',
			'*Français (fr) | 0 | (hermeslanguages-active-yes)',
		], self::rows( $this->execute() ) );
	}

	public function testLinks() {
		$this->registerProjectLanguage( 'dewiki', 'eo' );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a' ] ) );

		$html = $this->execute();
		$this->assertMatchesRegularExpression( '/<a href="[^"]*Special:HermesPages\/de"[^>]*>1<\/a>/', $html );
		$this->assertStringContainsString(
			'<th><abbr title="(hermeslanguages-header-active-tooltip)">(hermeslanguages-header-active)</abbr></th>',
			$html
		);
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
		$this->assertArrayNotHasKey( 'eo', LanguageStore::getActiveLanguages() );
		$this->assertSame( [], $this->getLogActions() );
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
			'/<tr><td colspan="4"><form [^>]*>.*name=.add-language.*<\/form><\/td><\/tr>\s*<\/tbody>/s', $html
		);
		$this->assertStringContainsString( 'oo-ui-dropdownInputWidget', $html );
		$this->assertSame( 2, preg_match_all( '/<tr class="[^"]*ext-hermes-base-language/', $html ) );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertContains( 'fr', $options[ 1 ] );
		$this->assertContains( '', $options[ 1 ] );
		$this->assertContains( 'de', $options[ 1 ] );
		$this->assertContains( 'es', $options[ 1 ] );
		foreach ( [ 'en', 'eo', 'xx-nope' ] as $taken ) {
			$this->assertNotContains( $taken, $options[ 1 ] );
		}
	}

	public function testNoAddRowWithoutAddableLanguages() {
		$names = $this->getServiceContainer()->getLanguageNameUtils()
			->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::SUPPORTED );
		$rows = [];
		foreach ( array_keys( $names ) as $code ) {
			if ( $code !== 'en' ) {
				$rows[] = [ 'hl_language' => $code, 'hl_wiki' => $this->wiki, 'hl_base' => 0, 'hl_active' => 0 ];
			}
		}
		$this->getDb()->newInsertQueryBuilder()->insertInto( 'hermes_languages' )->rows( $rows )
			->caller( __METHOD__ )->execute();
		LanguageStore::clearCacheForTesting();

		$html = $this->execute( $this->getAdmin() );
		$this->assertSame( [], self::addForms( $html ) );
		$this->assertMatchesRegularExpression(
			'/<th scope="rowgroup" rowspan="' . count( $names ) . '">[^<]*<a [^>]*>' . $this->wiki . '<\/a>/', $html
		);
	}

	public function testAddProjectLanguage() {
		$html = $this->post( [ 'add-language' => 'eo' ] );

		$eo = LanguageStore::getActiveLanguages()[ 'eo' ];
		$this->assertSame( $this->wiki, $eo->wiki );
		$this->assertFalse( $eo->isBase );
		$this->assertSame( [ 'add-project' ], $this->getLogActions() );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertNotContains( 'eo', $options[ 1 ] );
	}

	public function testAddUnknownLanguage() {
		$this->post( [ 'add-language' => 'xx-nope' ] );

		$this->assertArrayNotHasKey( 'xx-nope', LanguageStore::getActiveLanguages() );
	}

	public function testAddLanguageRegisteredElsewhere() {
		$this->post( [ 'add-language' => 'de' ] );

		$this->assertTrue( LanguageStore::isLocalProjectLanguage( 'de' ) );
		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testAddOwnBaseLanguage() {
		$this->post( [ 'add-language' => 'en' ] );

		$this->assertFalse( LanguageStore::isLocalProjectLanguage( 'en' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testAddNeedsToken() {
		$html = $this->executeRequest(
			new FauxRequest( [ 'add-language' => 'eo', 'wpEditToken' => 'bogus' ], true ),
			$this->getAdmin()
		);

		$this->assertStringContainsString( '(sessionfailure)', $html );
		$this->assertArrayNotHasKey( 'eo', LanguageStore::getActiveLanguages() );
	}

	public function testListsInactiveRegistrations() {
		$this->registerProjectLanguage( $this->wiki, 'de', false );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!de:C' ), Tag::fromArgs( [ 'a' ] ) );

		$html = $this->execute();
		$this->assertSame( [
			'(hermeslanguages-header-wiki) | (hermeslanguages-header-language) | (hermeslanguages-header-pages) | '
				. '(hermeslanguages-header-active)',
			'*dewiki | Deutsch (de) | 0 | (hermeslanguages-active-yes)',
			"*{$this->wiki} | English (en) | 0 | (hermeslanguages-active-yes)",
			'Deutsch (de) | 1 | (hermeslanguages-active-no)',
		], self::rows( $html ) );
		$this->assertSame( 1, preg_match_all( '/href="[^"]*Special:HermesPages\/de"/', $html ) );
		$key = LanguageStore::getLanguages()[ 'de' ][ $this->wiki ]->toId();
		$this->assertMatchesRegularExpression( "/href=\"[^\"]*Special:HermesPages\\/$key\"[^>]*>1<\\/a>/", $html );
	}

	public function testGlobalAdminManagesEveryWiki() {
		$this->assertSame( [ $this->wiki ], self::addForms( $this->execute( $this->getAdmin() ) ) );
		$this->assertSame( [ 'dewiki', $this->wiki ], self::addForms( $this->execute( $this->getAdmin( true ) ) ) );

		$this->post( [ 'add-language' => 'eo', 'wiki' => 'dewiki' ], true );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [ 'add-project' ], $this->getLogActions() );
	}

	public function testLocalAdminOnlyManagesThisWiki() {
		$this->post( [ 'add-language' => 'eo', 'wiki' => 'dewiki' ] );

		$this->assertNull( LanguageStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testAddToUnregisteredWiki() {
		$this->post( [ 'add-language' => 'eo', 'wiki' => 'nowiki' ], true );

		$this->assertNull( LanguageStore::getWikiForLanguage( 'eo' ) );
	}

	public function testActionButtons() {
		$this->registerProjectLanguage( $this->wiki, 'de', false );

		$html = $this->execute( $this->getAdmin( true ) );
		$this->assertSame(
			[ 'deactivate de@dewiki', "deactivate en@{$this->wiki}", "activate de@{$this->wiki}" ],
			self::activeButtons( $html )
		);
		$this->assertStringContainsString( '(hermeslanguages-header-actions)', $html );

		foreach ( [ $this->execute( $this->getAdmin() ), $this->execute() ] as $html ) {
			$this->assertSame( [], self::activeButtons( $html ) );
			$this->assertStringNotContainsString( '(hermeslanguages-header-actions)', $html );
		}
	}

	public function testActivate() {
		$this->registerProjectLanguage( $this->wiki, 'de', false );

		$html = $this->post( [ 'activate-language' => 'de' ], true );

		$this->assertSame( $this->wiki, LanguageStore::getWikiForLanguage( 'de' ) );
		// It was active on dewiki before.
		$this->assertSame( [ 'deactivate', 'activate' ], $this->getLogActions() );
		$this->assertContains( "deactivate de@{$this->wiki}", self::activeButtons( $html ) );
		$this->assertContains( 'activate de@dewiki', self::activeButtons( $html ) );
	}

	public function testActivateOnOtherWiki() {
		$this->registerProjectLanguage( 'frwiki', 'de', false );

		$this->post( [ 'activate-language' => 'de', 'wiki' => 'frwiki' ], true );

		$this->assertSame( 'frwiki', LanguageStore::getWikiForLanguage( 'de' ) );
	}

	public function testDeactivate() {
		$this->post( [ 'deactivate-language' => 'de', 'wiki' => 'dewiki' ], true );

		$this->assertNull( LanguageStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( [ 'deactivate' ], $this->getLogActions() );
		$this->assertSame( $this->wiki, LanguageStore::getWikiForLanguage( 'en' ) );
	}

	public function testDeactivateWhereInactive() {
		$this->registerProjectLanguage( $this->wiki, 'de', false );

		$this->post( [ 'deactivate-language' => 'de' ], true );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testLocalAdminActionButtons() {
		$this->registerProjectLanguage( $this->wiki, 'eo' );
		$this->registerProjectLanguage( $this->wiki, 'de', false );

		$html = $this->execute( $this->getAdmin() );
		$this->assertSame( [ "deactivate eo@{$this->wiki}" ], self::activeButtons( $html ) );
		$this->assertStringContainsString( '(hermeslanguages-header-actions)', $html );
	}

	public function testLocalAdminTogglesUniqueProject() {
		$this->registerProjectLanguage( $this->wiki, 'eo' );

		$this->post( [ 'deactivate-language' => 'eo' ] );
		$this->assertNull( LanguageStore::getWikiForLanguage( 'eo' ) );

		$this->post( [ 'activate-language' => 'eo' ] );
		$this->assertSame( $this->wiki, LanguageStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [ 'deactivate', 'activate' ], $this->getLogActions() );
	}

	/** Base languages, and languages other wikis have too, affect other wikis. */
	public function testActivationNeedsGlobalRight() {
		$this->registerProjectLanguage( $this->wiki, 'de', false );

		$this->post( [ 'activate-language' => 'de' ] );
		$this->post( [ 'deactivate-language' => 'en' ] );

		$this->assertSame( 'dewiki', LanguageStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( $this->wiki, LanguageStore::getWikiForLanguage( 'en' ) );
		$this->assertSame( [], $this->getLogActions() );
	}
}
