<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialHermesTags;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialHermesTags::execute
 * @covers \MediaWiki\Extension\Hermes\Special\HermesTagsPager
 */
class ExecuteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->activateLocalBaseProject();
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerBaseProject( 'fr', 'frwiki' );
	}

	private function execute( array $query = [], ?string $par = null ): string {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'HermesTags' );
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage( $page, $par, new FauxRequest( $query ) );
		return $html;
	}

	private function setTags( string $wiki, string $title, array $args ): void {
		TagStore::setTagsForPage( $this->makePageInfo( $wiki, $title ), Tag::fromArgs( $args ) );
	}

	/**
	 * @return array<string, array<string, string>> tag => language => its pages, comma-separated;
	 *   each line (one priority) is marked with "*" if italic, "+" if bold; conflict cells get a "!" before
	 *   the language.
	 */
	private static function table( string $html ): array {
		preg_match( '/<tbody>(.*)<\/tbody>/s', $html, $body );
		preg_match_all( '/<tr>(.*?)<\/tr>/s', $body[ 1 ] ?? '', $rows );
		$table = [];
		foreach ( $rows[ 1 ] as $row ) {
			preg_match( '/TablePager_col_ht_tag">(.*?)<\/td>/s', $row, $tag );
			preg_match_all(
				'/<td class="TablePager_col_project-([^ "]+)( ext-hermes-tag-conflict| ext-hermes-inactive)?"[^>]*>'
					. '(.*?)<\/td>/s',
				$row,
				$cells,
				PREG_SET_ORDER
			);
			$tagName = strip_tags( $tag[ 1 ] );
			$table[ $tagName ] = [];
			foreach ( $cells as [ , $language, $conflict, $cell ] ) {
				preg_match_all( '/<li>(.*?)<\/li>/s', $cell, $pages );
				if ( $pages[ 1 ] ) {
					$mark = $conflict === ' ext-hermes-tag-conflict' ? '!' : '';
					$table[ $tagName ][ $mark . $language ] = implode( ', ', array_map(
						static fn ( $line ) => ( str_contains( $line, '<i>' ) ? '*' : '' )
							. ( str_contains( $line, '<b>' ) ? '+' : '' )
							. trim( preg_replace( '/\s+/', ' ', strip_tags( $line ) ) ),
						$pages[ 1 ]
					) );
				}
			}
		}
		return $table;
	}

	/** @return string[] */
	private function listedTags( array $query = [], ?string $par = null ): array {
		return array_keys( self::table( $this->execute( $query, $par ) ) );
	}

	public function testListsTagsByLanguage() {
		$this->setTags( 'dewiki', 'Golem_de', [ 'golem' ] );
		$this->setTags( 'frwiki', 'Golem_fr', [ 'golem', 'other' ] );

		$this->assertSame(
			[ 'golem' => [ 'de' => 'Golem de', 'fr' => 'Golem fr' ], 'other' => [ 'fr' => '*Golem fr' ] ],
			self::table( $this->execute() )
		);
	}

	public function testOneColumnPerLanguage() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );

		preg_match_all( '/<th[^>]*>(.*?)<\/th>/s', $this->execute(), $headers );
		$this->assertSame(
			[ '(hermestags-header-tag)', 'Deutsch (de)', 'English (en)', 'Français (fr)' ],
			array_map( static fn ( $header ) => trim( strip_tags( $header ) ), $headers[ 1 ] )
		);
	}

	public function testSortsByLanguage() {
		$this->setTags( 'dewiki', 'C', [ 'a' ] );
		$this->setTags( 'dewiki', 'A', [ 'b' ] );
		$this->setTags( 'frwiki', 'B', [ 'c' ] );
		$this->setTags( 'dewiki', 'B', [ 'd' ] );

		// Tags without a page in that language come last.
		$this->assertSame( [ 'b', 'd', 'a', 'c' ], $this->listedTags( [ 'sort' => 'project-de' ] ) );
		$this->assertSame( [ 'c', 'a', 'd', 'b' ], $this->listedTags( [ 'sort' => 'project-de', 'desc' => '1' ] ) );
	}

	public function testPagesWhenSortedByLanguage() {
		foreach ( [ 'C', 'A', 'E', 'B', 'D' ] as $i => $title ) {
			$this->setTags( 'dewiki', $title, [ "tag$i" ] );
		}

		$tags = [];
		$query = [ 'sort' => 'project-de', 'limit' => '2' ];
		for ( $page = 0; $page < 3; $page++ ) {
			$html = $this->execute( $query );
			$tags = array_merge( $tags, array_keys( self::table( $html ) ) );
			if ( !preg_match( "/href='[^']*offset=([^&']*)[^']*'[^>]*><span[^>]*oo-ui-icon-next/", $html, $next ) ) {
				break;
			}
			$query[ 'offset' ] = urldecode( html_entity_decode( $next[ 1 ] ) );
		}
		$this->assertSame( [ 'tag1', 'tag3', 'tag0', 'tag4', 'tag2' ], $tags );
	}

	public function testMarksConflicts() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'golem' ] );
		// Same tag, but a different priority or language: no conflict.
		$this->setTags( 'dewiki', 'C', [ 'other', 'golem' ] );
		$this->setTags( 'frwiki', 'D', [ 'golem' ] );
		$this->setTags( 'frwiki', 'E', [ 'other', 'golem' ] );
		$this->setTags( 'frwiki', 'F', [ 'golem' ] );
		// A tie behind a lower priority: links resolve to that instead.
		$this->setTags( 'frwiki', 'G', [ 'other_2', 'golem' ] );

		$this->assertSame(
			[ '!de' => '+A / B, *C', '!fr' => '+D / F, *E / G' ],
			self::table( $this->execute() )[ 'golem' ]
		);
	}

	public function testConflictAtLowestPriority() {
		$this->setTags( 'dewiki', 'A', [ 'other', 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'other_2', 'golem' ] );
		$this->setTags( 'dewiki', 'C', [ 'x', 'y', 'golem' ] );

		$this->assertSame( [ '!de' => '*+A / B, *C' ], self::table( $this->execute() )[ 'golem' ] );
		$this->assertSame( [ 'golem' ], $this->listedTags( [ 'conflicts' => '1' ] ) );
	}

	public function testNoConflictBehindLowerPriority() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'other', 'golem' ] );
		$this->setTags( 'dewiki', 'C', [ 'other_2', 'golem' ] );

		$this->assertSame( [], $this->listedTags( [ 'conflicts' => '1' ] ) );
	}

	public function testConflictsOnly() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'golem' ] );
		$this->setTags( 'frwiki', 'C', [ 'other' ] );

		$this->assertSame( [ 'golem' ], $this->listedTags( [ 'conflicts' => '1' ] ) );
	}

	public function testProjectPageConflictsOnlyWithinProject() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'eo', $wiki );
		$this->setTags( $wiki, '!eo:A', [ 'golem' ] );
		$this->setTags( $wiki, 'B', [ 'golem' ] );

		$this->assertSame( [ 'golem' => [ 'en' => 'B', 'eo' => 'A' ] ], self::table( $this->execute() ) );
		$this->assertSame( [], $this->listedTags( [ 'conflicts' => '1' ] ) );
	}

	public function testFiltersByLanguage() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );
		$this->setTags( 'frwiki', 'C', [ 'other' ] );

		$this->assertSame(
			[ 'golem' => [ 'de' => 'A' ] ],
			self::table( $this->execute( [ 'language' => [ 'de' ] ] ) )
		);
		$this->assertDoesNotMatchRegularExpression(
			'/TablePager_col_project-(en|fr)/',
			$this->execute( [ 'language' => [ 'de' ] ] )
		);
	}

	public function testFiltersByMultipleLanguages() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'other' ] );
		$this->setTags( WikiMap::getCurrentWikiId(), 'C', [ 'third' ] );

		$this->assertSame(
			[ 'golem' => [ 'de' => 'A' ], 'other' => [ 'fr' => 'B' ] ],
			self::table( $this->execute( [ 'language' => [ 'de', 'fr' ] ] ) )
		);
	}

	public function testIgnoresUnknownLanguages() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );

		$this->assertSame(
			[ 'golem' => [ 'de' => 'A' ] ],
			self::table( $this->execute( [ 'language' => [ 'xx' ] ] ) )
		);
	}

	public function testFiltersByTagName() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'iron_golem' ] );
		$this->setTags( 'dewiki', 'C', [ 'creeper' ] );

		$this->assertSame( [ 'iron_golem' ], $this->listedTags( [ 'tag' => 'iron' ] ) );
		$this->assertSame( [ 'golem', 'iron_golem' ], $this->listedTags( [ 'tag' => 'olem' ] ) );
		$this->assertSame( [ 'iron_golem' ], $this->listedTags( [ 'tag' => ' Iron Gol' ] ) );
	}

	public function testOrdersByPriority() {
		$this->setTags( 'dewiki', 'A', [ 'x', 'y', 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'golem' ] );
		$this->setTags( 'dewiki', 'C', [ 'x2', 'golem' ] );

		$this->assertSame( [ 'de' => 'B, *C, *A' ], self::table( $this->execute() )[ 'golem' ] );
	}

	/**
	 * @return array<string, string[]> language code (or label, starting with "~" if inactive) => one cell per
	 *   priority, marked like in table(); conflict cells start with "!"
	 */
	private static function details( string $html ): array {
		preg_match_all(
			'/<tr( class="ext-hermes-inactive")?><th scope="row">(.*?)<\/th>(.*?)<\/tr>/s', $html, $rows, PREG_SET_ORDER
		);
		$details = [];
		foreach ( $rows as [ , $inactive, $label, $cells ] ) {
			$label = ( $inactive ? '~' : '' ) . strip_tags( $label );
			preg_match( '/^[^~].*\((\w+)\)$/', $label, $code );
			preg_match_all(
				'/<td( class="ext-hermes-tag-conflict")?[^>]*>(.*?)<\/td>/s', $cells, $tds, PREG_SET_ORDER
			);
			$details[ $code[ 1 ] ?? $label ] = array_map(
				static fn ( $td ) => ( $td[ 1 ] ? '!' : '' ) . ( str_contains( $td[ 2 ], '<i>' ) ? '*' : '' )
					. ( str_contains( $td[ 2 ], '<b>' ) ? '+' : '' )
					. preg_replace( '/ ?<br> ?/', '<br>', trim( strip_tags( $td[ 2 ], '<br>' ) ) ),
				$tds
			);
		}
		return $details;
	}

	public function testSubpageShowsLanguagesByPriority() {
		$this->setTags( 'frwiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'x', 'golem' ] );
		$this->setTags( 'dewiki', 'C', [ 'golem' ] );
		$this->setTags( 'dewiki', 'D', [ 'x2', 'y', 'golem' ] );
		$this->setTags( 'dewiki', 'E', [ 'x3', 'y2', 'golem' ] );
		$this->setTags( 'dewiki', 'F', [ 'golem_2' ] );

		$this->assertSame(
			[ 'de' => [ 'C', '*B', '*D<br>E' ], 'fr' => [ 'A', '', '' ] ],
			self::details( $this->execute( [], 'golem' ) )
		);
	}

	public function testSubpageHasNoForm() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );

		$html = $this->execute( [], 'golem' );
		$this->assertStringNotContainsString( '(hermestags-legend)', $html );
		$this->assertStringNotContainsString( 'TablePager', $html );
	}

	public function testSubpageHasPriorityHeader() {
		$this->setTags( 'dewiki', 'A', [ 'x', 'golem' ] );

		$this->assertStringContainsString(
			'<thead><tr><th>(hermestags-header-priority)</th>' .
				'<th scope="col">0</th><th scope="col">1</th></tr></thead>',
			$this->execute( [], 'golem' )
		);
	}

	public function testSubpageHasNoHeaderWithoutFallbacks() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'dewiki', 'B', [ 'golem' ] );

		$this->assertStringNotContainsString( '<thead>', $this->execute( [], 'golem' ) );
	}

	public function testSubpageIgnoresFilters() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );

		$this->assertSame(
			[ 'de' => [ 'A' ], 'fr' => [ 'B' ] ],
			self::details(
				$this->execute( [ 'tag' => 'other', 'language' => [ 'fr' ], 'conflicts' => '1' ], 'golem' )
			)
		);
	}

	public function testSubpageNormalizesTag() {
		$this->setTags( 'dewiki', 'A', [ 'iron_golem' ] );

		$this->assertSame( [ 'de' => [ 'A' ] ], self::details( $this->execute( [], 'Iron Golem' ) ) );
	}

	public function testSubpageOfUnknownTag() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );

		$this->assertStringContainsString( '(hermestags-empty)', $this->execute( [], 'gol' ) );
	}

	public function testInvalidSubpage() {
		$this->assertStringContainsString( '(hermes-invalid-tag-name', $this->execute( [], 'not valid!' ) );
	}

	public function testLinksTagsToSubpage() {
		$this->setTags( 'dewiki', 'A', [ 'iron_golem' ] );

		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*Special:HermesTags\/iron_golem"[^>]*>iron_golem<\/a>/',
			$this->execute()
		);
	}

	public function testEmpty() {
		$this->assertStringContainsString( '(hermestags-empty)', $this->execute() );
	}

	public function testSkipsInactivePages() {
		$this->registerTranslationProject( 'de', WikiMap::getCurrentWikiId(), false );
		$this->setTags( 'dewiki', 'Golem_de', [ 'golem' ] );
		$this->setTags( WikiMap::getCurrentWikiId(), '!de:Golem', [ 'golem', 'other' ] );

		$this->assertSame( [ 'golem' => [ 'de' => 'Golem de' ] ], self::table( $this->execute() ) );
	}

	public function testShowsInactiveColumns() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'dewiki', 'Golem_de', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem', [ 'golem' ] );
		$this->setTags( $wiki, '!de:Golem_2', [ 'golem' ] );

		$html = $this->execute( [ 'tag' => '', 'inactive' => '1' ] );
		$key = ProjectStore::getProject( 'de', $wiki )->toId();
		$this->assertSame( [ 'golem' => [ 'de' => 'Golem de', $key => 'Golem / Golem 2' ] ], self::table( $html ) );
		$this->assertStringContainsString( "TablePager_col_project-$key ext-hermes-inactive", $html );
	}

	public function testFiltersByWiki() {
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( 'frwiki', 'B', [ 'golem' ] );
		$this->setTags( 'frwiki', 'C', [ 'other' ] );

		$this->assertSame(
			[ 'golem' => [ 'de' => 'A' ] ], self::table( $this->execute( [ 'wiki' => [ 'dewiki' ] ] ) )
		);
	}

	public function testSubpageShowsInactiveRows() {
		$wiki = WikiMap::getCurrentWikiId();
		$this->registerTranslationProject( 'de', $wiki, false );
		$this->setTags( 'dewiki', 'A', [ 'golem' ] );
		$this->setTags( $wiki, '!de:B', [ 'golem' ] );
		$this->setTags( $wiki, '!de:C', [ 'golem' ] );

		$this->assertSame(
			// Inactive pages don't conflict.
			[ 'de' => [ 'A' ], "~(hermes-inactive-project: Deutsch (de), $wiki)" => [ 'B<br>C' ] ],
			self::details( $this->execute( [], 'golem' ) )
		);
	}
}
