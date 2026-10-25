<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesProjects;

use MediaWiki\Extension\Hermes\ProjectStore;
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
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialHermesProjects
 * @covers \MediaWiki\Extension\Hermes\Special\TagQuery::countPages
 */
class ExecuteTest extends HermesIntegrationTestCase {

	private string $wiki;

	protected function setUp(): void {
		parent::setUp();
		$this->wiki = WikiMap::getCurrentWikiId();
		$this->activateLocalBaseProject();
		$this->registerBaseProject( 'dewiki', 'de', url: 'https://de.example/wiki/$1' );
	}

	private function execute( ?Authority $performer = null ): string {
		return $this->executeRequest( new FauxRequest(), $performer );
	}

	private function executeRequest( FauxRequest $request, ?Authority $performer = null ): string {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesProjects' );
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
		preg_match_all( "/name='add-project'.*?value=\"([^\"]*)\" name=\"wiki\"/s", $html, $forms );
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
			"/name='(activate|deactivate)-project' value='([^']*)'.*?value=\"([^\"]*)\" name=\"wiki\"/s",
			$html, $buttons, PREG_SET_ORDER
		);
		return array_map( static fn ( $m ) => "{$m[ 1 ]} {$m[ 2 ]}@{$m[ 3 ]}", $buttons );
	}

	/** @return string[] The table's rows, cells separated by " | ", highlighted (active) ones marked "*" */
	private static function rows( string $html ): array {
		preg_match_all( '/<tr([^>]*)>(.*?)<\/tr>/s', $html, $rows, PREG_SET_ORDER );
		return array_map( static function ( $row ) {
			preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/s', $row[ 2 ], $cells );
			return ( str_contains( $row[ 1 ], 'ext-hermes-active-project' ) ? '*' : '' )
				. implode( ' | ', array_map( static fn ( $cell ) => trim( strip_tags( $cell ) ), $cells[ 1 ] ) );
		}, $rows );
	}

	public function testListsProjects() {
		$this->registerTranslationProject( $this->wiki, 'fr' );
		$this->registerTranslationProject( $this->wiki, 'eo' );
		// Several tags on one page, and several pages: count pages, not tags.
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a', 'b' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'B' ), Tag::fromArgs( [ 'a' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!eo:C' ), Tag::fromArgs( [ 'a' ] ) );

		$this->assertSame( [
			'(hermesprojects-header-wiki) | (hermesprojects-header-language) | (hermesprojects-header-pages) | '
				. '(hermesprojects-header-active)',
			'*dewiki | Deutsch (de) | 2 | (hermesprojects-active-yes)',
			"*{$this->wiki} | English (en) | 0 | (hermesprojects-active-yes)",
			'*Esperanto (eo) | 1 | (hermesprojects-active-yes)',
			'*Français (fr) | 0 | (hermesprojects-active-yes)',
		], self::rows( $this->execute() ) );
	}

	public function testLinks() {
		$this->registerTranslationProject( 'dewiki', 'eo' );
		TagStore::setTagsForPage( $this->makePageInfo( 'dewiki', 'A' ), Tag::fromArgs( [ 'a' ] ) );

		$html = $this->execute();
		$this->assertMatchesRegularExpression( '/<a href="[^"]*Special:HermesPages\/de"[^>]*>1<\/a>/', $html );
		$this->assertStringContainsString(
			'<th><abbr title="(hermesprojects-header-active-tooltip)">(hermesprojects-header-active)</abbr></th>',
			$html
		);
		$this->assertStringContainsString(
			'<th scope="rowgroup" rowspan="2"><a href="https://de.example/wiki/" class="extiw">dewiki</a></th>', $html
		);
	}

	public function testNoEditingWithoutRight() {
		$html = $this->execute();
		$this->assertStringNotContainsString( 'name="add-project"', $html );

		$request = new FauxRequest( [ 'add-project' => 'eo' ], true );
		$request->setVal( 'wpEditToken', ( new CsrfTokenSet( $request ) )->getToken()->toString() );
		$this->executeRequest( $request );
		$this->assertArrayNotHasKey( 'eo', ProjectStore::getActiveProjects() );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testAddRow() {
		$this->registerTranslationProject( 'dewiki', 'es' );
		$this->registerTranslationProject( $this->wiki, 'eo' );

		$html = $this->execute( $this->getAdmin() );
		$this->assertMatchesRegularExpression(
			"/<th scope=\"rowgroup\" rowspan=\"3\">[^<]*<a [^>]*>{$this->wiki}<\/a><\/th>/", $html
		);
		$this->assertStringContainsString( '<th scope="rowgroup" rowspan="2">', $html );
		$this->assertMatchesRegularExpression(
			'/<tr><td colspan="4"><form [^>]*>.*name=.add-project.*<\/form><\/td><\/tr>\s*<\/tbody>/s', $html
		);
		$this->assertStringContainsString( 'oo-ui-dropdownInputWidget', $html );
		$this->assertSame( 2, preg_match_all( '/<tr class="[^"]*ext-hermes-base-project/', $html ) );
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
				$rows[] = [ 'hp_language' => $code, 'hp_wiki' => $this->wiki, 'hp_base' => 0, 'hp_active' => 0 ];
			}
		}
		$this->getDb()->newInsertQueryBuilder()->insertInto( 'hermes_projects' )->rows( $rows )
			->caller( __METHOD__ )->execute();
		ProjectStore::clearCacheForTesting();

		$html = $this->execute( $this->getAdmin() );
		$this->assertSame( [], self::addForms( $html ) );
		$this->assertMatchesRegularExpression(
			'/<th scope="rowgroup" rowspan="' . count( $names ) . '">[^<]*<a [^>]*>' . $this->wiki . '<\/a>/', $html
		);
	}

	public function testAddTranslationProject() {
		$html = $this->post( [ 'add-project' => 'eo' ] );

		$eo = ProjectStore::getActiveProjects()[ 'eo' ];
		$this->assertSame( $this->wiki, $eo->wiki );
		$this->assertFalse( $eo->isBase );
		$this->assertSame( [ 'add-project' ], $this->getLogActions() );
		preg_match_all( "/<option value='([^']*)'/", $html, $options );
		$this->assertNotContains( 'eo', $options[ 1 ] );
	}

	public function testAddUnknownLanguage() {
		$this->post( [ 'add-project' => 'xx-nope' ] );

		$this->assertArrayNotHasKey( 'xx-nope', ProjectStore::getActiveProjects() );
	}

	public function testAddLanguageWithProjectElsewhere() {
		$this->post( [ 'add-project' => 'de' ] );

		$this->assertTrue( ProjectStore::isLocalTranslationProject( 'de' ) );
		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testAddOwnBaseLanguage() {
		$this->post( [ 'add-project' => 'en' ] );

		$this->assertFalse( ProjectStore::isLocalTranslationProject( 'en' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testAddNeedsToken() {
		$html = $this->executeRequest(
			new FauxRequest( [ 'add-project' => 'eo', 'wpEditToken' => 'bogus' ], true ),
			$this->getAdmin()
		);

		$this->assertStringContainsString( '(sessionfailure)', $html );
		$this->assertArrayNotHasKey( 'eo', ProjectStore::getActiveProjects() );
	}

	public function testListsInactiveProjects() {
		$this->registerTranslationProject( $this->wiki, 'de', false );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!de:C' ), Tag::fromArgs( [ 'a' ] ) );

		$html = $this->execute();
		$this->assertSame( [
			'(hermesprojects-header-wiki) | (hermesprojects-header-language) | (hermesprojects-header-pages) | '
				. '(hermesprojects-header-active)',
			'*dewiki | Deutsch (de) | 0 | (hermesprojects-active-yes)',
			"*{$this->wiki} | English (en) | 0 | (hermesprojects-active-yes)",
			'Deutsch (de) | 1 | (hermesprojects-active-no)',
		], self::rows( $html ) );
		$this->assertSame( 1, preg_match_all( '/href="[^"]*Special:HermesPages\/de"/', $html ) );
		$key = ProjectStore::getProjects()[ 'de' ][ $this->wiki ]->toId();
		$this->assertMatchesRegularExpression( "/href=\"[^\"]*Special:HermesPages\\/$key\"[^>]*>1<\\/a>/", $html );
	}

	public function testGlobalAdminManagesEveryWiki() {
		$this->assertSame( [ $this->wiki ], self::addForms( $this->execute( $this->getAdmin() ) ) );
		$this->assertSame( [ 'dewiki', $this->wiki ], self::addForms( $this->execute( $this->getAdmin( true ) ) ) );

		$this->post( [ 'add-project' => 'eo', 'wiki' => 'dewiki' ], true );

		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [ 'add-project' ], $this->getLogActions() );
	}

	public function testLocalAdminOnlyManagesThisWiki() {
		$this->post( [ 'add-project' => 'eo', 'wiki' => 'dewiki' ] );

		$this->assertNull( ProjectStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testAddToUnregisteredWiki() {
		$this->post( [ 'add-project' => 'eo', 'wiki' => 'nowiki' ], true );

		$this->assertNull( ProjectStore::getWikiForLanguage( 'eo' ) );
	}

	public function testActionButtons() {
		$this->registerTranslationProject( $this->wiki, 'de', false );

		$html = $this->execute( $this->getAdmin( true ) );
		$this->assertSame(
			[ 'deactivate de@dewiki', "deactivate en@{$this->wiki}", "activate de@{$this->wiki}" ],
			self::activeButtons( $html )
		);
		$this->assertStringContainsString( '(hermesprojects-header-actions)', $html );

		foreach ( [ $this->execute( $this->getAdmin() ), $this->execute() ] as $html ) {
			$this->assertSame( [], self::activeButtons( $html ) );
			$this->assertStringNotContainsString( '(hermesprojects-header-actions)', $html );
		}
	}

	public function testActivate() {
		$this->registerTranslationProject( $this->wiki, 'de', false );

		$html = $this->post( [ 'activate-project' => 'de' ], true );

		$this->assertSame( $this->wiki, ProjectStore::getWikiForLanguage( 'de' ) );
		// It was active on dewiki before.
		$this->assertSame( [ 'deactivate', 'activate' ], $this->getLogActions() );
		$this->assertContains( "deactivate de@{$this->wiki}", self::activeButtons( $html ) );
		$this->assertContains( 'activate de@dewiki', self::activeButtons( $html ) );
	}

	public function testActivateOnOtherWiki() {
		$this->registerTranslationProject( 'frwiki', 'de', false );

		$this->post( [ 'activate-project' => 'de', 'wiki' => 'frwiki' ], true );

		$this->assertSame( 'frwiki', ProjectStore::getWikiForLanguage( 'de' ) );
	}

	public function testDeactivate() {
		$this->post( [ 'deactivate-project' => 'de', 'wiki' => 'dewiki' ], true );

		$this->assertNull( ProjectStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( [ 'deactivate' ], $this->getLogActions() );
		$this->assertSame( $this->wiki, ProjectStore::getWikiForLanguage( 'en' ) );
	}

	public function testDeactivateWhereInactive() {
		$this->registerTranslationProject( $this->wiki, 'de', false );

		$this->post( [ 'deactivate-project' => 'de' ], true );

		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( [], $this->getLogActions() );
	}

	public function testLocalAdminActionButtons() {
		$this->registerTranslationProject( $this->wiki, 'eo' );
		$this->registerTranslationProject( $this->wiki, 'de', false );

		$html = $this->execute( $this->getAdmin() );
		$this->assertSame( [ "deactivate eo@{$this->wiki}" ], self::activeButtons( $html ) );
		$this->assertStringContainsString( '(hermesprojects-header-actions)', $html );
	}

	public function testLocalAdminTogglesUniqueProject() {
		$this->registerTranslationProject( $this->wiki, 'eo' );

		$this->post( [ 'deactivate-project' => 'eo' ] );
		$this->assertNull( ProjectStore::getWikiForLanguage( 'eo' ) );

		$this->post( [ 'activate-project' => 'eo' ] );
		$this->assertSame( $this->wiki, ProjectStore::getWikiForLanguage( 'eo' ) );
		$this->assertSame( [ 'deactivate', 'activate' ], $this->getLogActions() );
	}

	/** Base languages, and languages other wikis have too, affect other wikis. */
	public function testActivationNeedsGlobalRight() {
		$this->registerTranslationProject( $this->wiki, 'de', false );

		$this->post( [ 'activate-project' => 'de' ] );
		$this->post( [ 'deactivate-project' => 'en' ] );

		$this->assertSame( 'dewiki', ProjectStore::getWikiForLanguage( 'de' ) );
		$this->assertSame( $this->wiki, ProjectStore::getWikiForLanguage( 'en' ) );
		$this->assertSame( [], $this->getLogActions() );
	}
}
