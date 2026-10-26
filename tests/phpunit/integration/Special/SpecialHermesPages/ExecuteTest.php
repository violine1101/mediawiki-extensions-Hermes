<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesPages;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialHermesPages::execute
 * @covers \MediaWiki\Extension\Hermes\Special\HermesPagesPager
 */
class ExecuteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerBaseProject( 'fr', 'frwiki' );
	}

	private function execute( array $query = [], ?string $par = null ): string {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesPages' );
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage( $page, $par, new FauxRequest( $query ) );
		return $html;
	}

	private function setTags( string $wiki, string $title, array $args ): ProjectTitle {
		$page = $this->makePage( $wiki, $title );
		TagStore::setTagsForPage( $page, Tag::fromArgs( $args ) );
		return $page;
	}

	/**
	 * Cell texts of a table's rows; fallbacks are marked "*", link targets "[T]", conflicts "[C]", the page's
	 * own language "[O]", and line breaks are "; ".
	 */
	private static function rows( string $html ): array {
		preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/s', $html, $rows );
		return array_map( static function ( $row ) {
			preg_match_all( '/<t[hd]([^>]*)>(.*?)<\/t[hd]>/s', $row, $cells, PREG_SET_ORDER );
			return implode( ' | ', array_map(
				static fn ( $cell ) => ( str_contains( $cell[ 1 ], 'ext-hermes-link-target' ) ? '[T]' : '' )
					. ( str_contains( $cell[ 1 ], 'ext-hermes-own-project' ) ? '[O]' : '' )
					. ( str_contains( $cell[ 1 ], 'ext-hermes-tag-conflict' ) ? '[C]' : '' )
					. trim( preg_replace( '/[\s\x{a0}]+/u', ' ', strip_tags(
						preg_replace( [ '/<i>/', '/<br>/' ], [ '*', '; ' ], $cell[ 2 ] )
					) ) ),
				$cells
			) );
		}, $rows[ 1 ] );
	}

	/** @return string[] Rows of the list, without the header */
	private function list( array $query = [] ): array {
		preg_match( '/<tbody>(.*)<\/tbody>/s', $this->execute( $query ), $body );
		return self::rows( $body[ 1 ] ?? '' );
	}

	public function testListsPagesByPriority() {
		$this->setTags( 'dewiki', 'Golem', [ 'golem', 'monster=#Mobs' ] );
		$this->setTags( 'frwiki', 'Creeper', [ 'creeper' ] );

		$this->assertSame(
			[ 'Creeper | Français (fr) | creeper | ', 'Golem | Deutsch (de) | golem | monster (#Mobs)' ],
			$this->list()
		);
		preg_match_all( '/<th[^>]*>(.*?)<\/th>/s', $this->execute(), $headers );
		$this->assertSame(
			[
				'(hermespages-header-page)', '(hermespages-header-language)',
				'(hermespages-header-priority: 0)', '(hermespages-header-priority: 1)',
			],
			array_map( static fn ( $header ) => trim( strip_tags( $header ) ), $headers[ 1 ] )
		);
	}

	public function testMarksConflicts() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'golem' ] );
		// A tie behind a lower priority (E): no conflict.
		$this->setTags( 'dewiki', 'C', [ 'x', 'monster' ] );
		$this->setTags( 'dewiki', 'D', [ 'y', 'monster' ] );
		$this->setTags( 'dewiki', 'E', [ 'monster' ] );

		$this->assertSame( [
			'A | Deutsch (de) | [C]golem | ',
			'B | Deutsch (de) | [C]golem | ',
			'C | Deutsch (de) | x | monster',
			'D | Deutsch (de) | y | monster',
			'E | Deutsch (de) | monster | ',
		], $this->list() );
		$this->assertSame( [ 'A', 'B' ], $this->listedTitles( [ 'conflicts' => '1' ] ) );
	}

	/** @return string[] Titles of the listed pages */
	private function listedTitles( array $query ): array {
		return array_map( static fn ( $row ) => explode( ' | ', $row )[ 0 ], $this->list( $query ) );
	}

	public function testSortsByEveryColumn() {
		$this->setTags( 'frwiki', 'A', [ 'zeta' ] );
		$this->setTags( 'dewiki', 'B', [ 'alpha', 'gamma' ] );
		$this->setTags( 'dewiki', 'C', [ 'beta', 'delta' ] );

		$this->assertSame( [ 'A', 'B', 'C' ], $this->listedTitles( [] ) );
		$this->assertSame( [ 'C', 'B', 'A' ], $this->listedTitles( [ 'sort' => 'ht_base_title', 'desc' => '1' ] ) );
		$this->assertSame( [ 'B', 'C', 'A' ], $this->listedTitles( [ 'sort' => 'language' ] ) );
		$this->assertSame( [ 'B', 'C', 'A' ], $this->listedTitles( [ 'sort' => 'priority-0' ] ) );
		// Pages without a tag of that priority come last.
		$this->assertSame( [ 'C', 'B', 'A' ], $this->listedTitles( [ 'sort' => 'priority-1' ] ) );
		$this->assertSame( [ 'A', 'B', 'C' ], $this->listedTitles( [ 'sort' => 'priority-1', 'desc' => '1' ] ) );
	}

	public function testLinksPagesAndDetails() {
		$this->setWikiUrl( 'dewiki', 'https://de.example/wiki/$1' );
		$this->setTags( 'dewiki', 'Golem', [ 'golem', 'monster=#Mobs' ] );

		$html = $this->execute();
		$this->assertStringContainsString( '<a href="https://de.example/wiki/Golem" class="extiw"', $html );
		$this->assertStringContainsString(
			'<a href="/wiki/Special:HermesPages/de/Golem" class="ext-hermes-details-link" ' .
				'title="(hermes-page-details)" aria-label="(hermes-page-details)"></a>',
			$html
		);
		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*Special:HermesTags\/golem"[^>]*>golem<\/a>/', $html
		);
		$this->assertStringContainsString(
			'(<a href="https://de.example/wiki/Golem#Mobs" class="extiw">#Mobs</a>)', $html
		);
	}

	public function testFiltersByTitle() {
		$this->setTags( 'dewiki', 'Iron_Golem', [ 'golem' ] );
		$this->setTags( 'dewiki', 'Creeper', [ 'creeper' ] );

		$this->assertSame( [ 'Iron Golem | Deutsch (de) | golem' ], $this->list( [ 'title-contains' => 'on Gol' ] ) );
	}

	public function testFiltersByLanguage() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );

		$this->assertSame( [ 'B | Français (fr) | golem' ], $this->list( [ 'language' => [ 'fr' ] ] ) );
	}

	public function testLanguageSubpagePreselectsLanguage() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );

		$html = $this->execute( [], 'fr' );
		$this->assertStringContainsString( '(hermespages-legend)', $html );
		$this->assertStringContainsString( "name='language[]' value='fr' id='ooui-php-", $html );
		$this->assertMatchesRegularExpression( "/value='fr' id='[^']*' checked='checked'/", $html );
		preg_match( '/<tbody>(.*)<\/tbody>/s', $html, $body );
		$this->assertSame( [ 'B | Français (fr) | golem' ], self::rows( $body[ 1 ] ) );
	}

	public function testLanguageSubpageWithSlash() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );

		preg_match( '/<tbody>(.*)<\/tbody>/s', $this->execute( [], 'fr/' ), $body );
		$this->assertSame( [ 'B | Français (fr) | golem' ], self::rows( $body[ 1 ] ) );
	}

	public function testLanguageSubpageYieldsToForm() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );

		preg_match( '/<tbody>(.*)<\/tbody>/s', $this->execute( [ 'language' => [ 'de' ] ], 'fr' ), $body );
		$this->assertSame( [ 'A | Deutsch (de) | golem' ], self::rows( $body[ 1 ] ) );
	}

	/** @return string[] Rows of a page's links table, with language names reduced to codes */
	private function links( string $par ): array {
		$html = $this->execute( [], $par );
		$this->assertStringNotContainsString( '(hermespages-legend)', $html );
		return array_map(
			static fn ( $row ) => preg_replace( '/^.*?\((\w+)\)( \||$)/', '$1$2', $row ),
			self::rows( $html )
		);
	}

	public function testDetailsShowLinks() {
		$this->setTags( 'dewiki', 'Golem', [ 'iron_golem', 'golem' ] );
		$this->setTags( 'frwiki', 'Golem_de_fer', [ 'iron_golem' ] );
		$this->setTags( 'frwiki', 'Golem', [ 'golem' ] );
		$this->setTags( 'frwiki', 'Golem_2', [ 'golem' ] );
		$this->registerBaseProject( 'es', 'eswiki' );
		$this->setTags( 'eswiki', 'Golem', [ 'other', 'golem' ] );
		// Same language as the page itself: not a language link, but a conflict.
		$this->setTags( 'dewiki', 'Eisengolem', [ 'iron_golem' ] );

		$this->assertSame(
			[
				'(hermespages-header-language) | iron_golem | golem',
				'de | [C]Eisengolem / Golem | [O]*Golem',
				'es |  | [T]*Golem',
				// Links resolve by iron_golem, so golem isn't ambiguous.
				'fr | [T]Golem de fer | Golem / Golem 2',
			],
			$this->links( 'de/Golem' )
		);
	}

	public function testDetailsListPrioritiesOnLines() {
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( 'frwiki', 'A', [ 'x', 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'y', 'golem' ] );
		$this->setTags( 'frwiki', 'C', [ 'x', 'y', 'golem' ] );

		$this->assertSame(
			[ '(hermespages-header-language) | golem', 'de | [O]Golem', 'fr | [T][C]*A / B ; *C' ],
			$this->links( 'de/Golem' )
		);
	}

	public function testDetailsWithoutLinks() {
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );

		$this->assertSame( [ '(hermespages-header-language) | golem', 'de | [O]Golem' ], $this->links( 'de/Golem' ) );
	}

	public function testDetailsOfNamespacedPage() {
		$this->registerNamespaces( 'dewiki', [ NS_CATEGORY => 'Kategorie' ] );
		TagStore::setTagsForPage(
			ProjectTitle::make( self::getProject( null, 'dewiki' ), NS_CATEGORY, 'Golems', 'Kategorie', 100 ),
			Tag::fromArgs( [ 'golems' ] )
		);
		$this->setTags( 'dewiki', 'Kategorie:Golems', [ 'other' ] );
		$this->setTags( 'frwiki', 'A', [ 'golems' ] );
		$this->setTags( 'frwiki', 'B', [ 'other' ] );

		$this->assertSame(
			[ '(hermespages-header-language) | golems', 'de | [O]Kategorie:Golems', 'fr | [T]A' ],
			$this->links( 'de/Kategorie:Golems' )
		);
	}

	public function testDetailsOfProjectPage() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'eo', $wiki );
		$this->setTags( $wiki, '!eo:Golem', [ 'golem' ] );
		$this->setTags( $wiki, 'Golem', [ 'other' ] );
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );

		$this->assertSame(
			[ '(hermespages-header-language) | golem', 'de | [T]A', 'eo | [O]Golem' ],
			$this->links( 'eo/Golem' )
		);
	}

	public function testDetailsNotFound() {
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );

		$this->assertStringContainsString( '(hermespages-notfound: fr/Golem)', $this->execute( [], 'fr/Golem' ) );
		$this->assertStringContainsString( '(hermespages-notfound: xx/Golem)', $this->execute( [], 'xx/Golem' ) );
		$this->assertStringContainsString( '(hermespages-notfound: xx)', $this->execute( [], 'xx' ) );
	}

	public function testSkipsInactivePages() {
		$this->registerTranslationProject( 'de', WikiMap::getCurrentWikiId(), false );
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( WikiMap::getCurrentWikiId(), '!de:Golem', [ 'golem' ] );

		$this->assertSame( [ 'Golem | Deutsch (de) | golem' ], $this->list() );
	}

	public function testShowsInactivePages() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem', [ 'golem' ] );

		$this->assertSame(
			[ 'Golem | Deutsch (de) | golem', "Golem | (hermes-inactive-project: Deutsch (de), $wiki) | golem" ],
			$this->list( [ 'title-contains' => '', 'inactive' => '1' ] )
		);
		$html = $this->execute( [ 'title-contains' => '', 'inactive' => '1' ] );
		$this->assertSame( 1, preg_match_all( '/<tr class="[^"]*\bext-hermes-inactive\b/', $html ) );
	}

	public function testFiltersByWiki() {
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( 'frwiki', 'Golem', [ 'golem' ] );

		$this->assertSame( [ 'Golem | Français (fr) | golem' ], $this->list( [ 'wiki' => [ 'frwiki' ] ] ) );
	}

	public function testProjectSubpage() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem', [ 'golem' ] );

		$html = $this->execute( [], ProjectStore::getProject( 'de', $wiki )->toId() );
		preg_match( '/<tbody>(.*)<\/tbody>/s', $html, $body );
		$this->assertSame(
			[ "Golem | (hermes-inactive-project: Deutsch (de), $wiki) | golem" ], self::rows( $body[ 1 ] )
		);
		$this->assertMatchesRegularExpression( '/name=.inactive.[^>]*checked|checked[^>]*name=.inactive./', $html );
	}

	public function testDetailsOfInactivePage() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'dewiki', 'Golem', [ 'golem' ] );
		$this->setTags( 'frwiki', 'Golem', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem', [ 'golem' ] );

		$this->assertSame(
			[
				'(hermespages-header-language) | golem',
				// Not linked: the page's own language.
				'de | Golem',
				"(hermes-inactive-project: Deutsch (de), $wiki) | [O]Golem",
				'fr | [T]Golem',
			],
			$this->links( ProjectStore::getProject( 'de', $wiki )->toId() . '/Golem' )
		);
	}

	public function testDetailsShowInactiveRows() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'frwiki', 'Golem', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem', [ 'golem' ] );

		$this->assertSame(
			[
				'(hermespages-header-language) | golem',
				"(hermes-inactive-project: Deutsch (de), $wiki) | Golem",
				'fr | [O]Golem',
			],
			$this->links( 'fr/Golem' )
		);
	}
}
