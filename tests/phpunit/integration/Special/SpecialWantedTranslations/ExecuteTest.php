<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\SpecialWantedTranslations;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\WantedTranslationsPage
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialWantedTranslations
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialWantedCategoryTranslations
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::projectSqlCondition
 */
class ExecuteTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [ MainConfigNames::MiserMode => false ] );
		$this->registerBaseProject( 'dewiki', 'de' );
		$this->registerTranslationProject( 'dewiki', 'es' );
		$this->registerTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
		$this->insertPage( 'Foo' );
	}

	private function execute( string $page, ?string $par = null, array $query = [] ): string {
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage(
			$this->getServiceContainer()->getSpecialPageFactory()->getPage( $page ), $par, new FauxRequest( $query )
		);
		return $html;
	}

	/** @return string[] The listed rows, as text */
	private function rows( string $page, ?string $par = null, array $query = [] ): array {
		preg_match_all( '/<li>(.*?)<\/li>/s', $this->execute( $page, $par, $query ), $rows );
		return array_map( static fn ( $row ) => trim( strip_tags( $row ) ), $rows[ 1 ] );
	}

	public function testListsWantedTranslations() {
		$this->insertPage( '!eo:Bar' );
		$this->insertPage( 'Template:Baz' );
		// Not existing ones, ones of another wiki's project, or in namespaces falling back to the base page.
		$this->insertPage(
			'A', '[[!eo:Foo]] [[!eo:Missing]] [[!eo:Bar]] [[Template:!eo:Baz]] [[!es:Foo]] [[Missing]]'
		);
		$this->insertPage( 'B', '[[!eo:Foo]]' );

		$this->assertSame( [
			'Esperanto (eo)(colon-separator)Foo(en)(word-separator)(parentheses: (nlinks: 2))',
			'Esperanto (eo)(colon-separator)Missing(word-separator)(parentheses: (nlinks: 1))',
		], $this->rows( 'WantedTranslations' ) );
	}

	public function testLinksToTranslate() {
		$this->insertPage( 'A', '[[!eo:Foo]]' );

		$this->assertMatchesRegularExpression(
			'/<a href="[^"]*Special:HermesTranslate\/Foo&amp;language=eo" class="new"[^>]*>Foo<\/a>/',
			$this->execute( 'WantedTranslations' )
		);
	}

	public function testListsWantedCategoryTranslations() {
		$this->insertPage( 'A', '[[Category:!eo:Golemoj]] [[Category:Foo]]' );
		$this->insertPage( 'B', '[[Category:!eo:Golemoj]]' );

		$this->assertSame(
			[ 'Esperanto (eo)(colon-separator)Golemoj(word-separator)(parentheses: (nmembers: 2))' ],
			$this->rows( 'WantedCategoryTranslations' )
		);
	}

	public static function provideProjectFilter() {
		return [
			'sub-page' => [ 'fr', [] ],
			'query' => [ null, [ 'language' => 'fr' ] ],
			'query over sub-page' => [ 'eo', [ 'language' => 'fr' ] ],
		];
	}

	/** @dataProvider provideProjectFilter */
	public function testProjectFilter( ?string $par, array $query ) {
		$this->registerTranslationProject( WikiMap::getCurrentWikiId(), 'fr' );
		$this->insertPage( 'A', '[[!eo:Foo]] [[!fr:Foo]] [[Category:!eo:Foo]] [[Category:!fr:Foo]]' );

		// Without the project, which the filter shows.
		$this->assertSame(
			[ 'Foo(en)(word-separator)(parentheses: (nlinks: 1))' ], $this->rows( 'WantedTranslations', $par, $query )
		);
		$this->assertCount( 1, $this->rows( 'WantedCategoryTranslations', $par, $query ) );
		$this->assertStringContainsString(
			"<option value='fr' selected='selected'>", $this->execute( 'WantedTranslations', $par, $query )
		);
	}

	public function testIgnoresOtherProjectsInFilter() {
		$this->insertPage( 'A', '[[!eo:Foo]]' );

		$this->assertCount( 1, $this->rows( 'WantedTranslations', 'es' ) );
	}

	public function testFiltersCachedResults() {
		$this->registerTranslationProject( WikiMap::getCurrentWikiId(), 'fr' );
		$this->insertPage( 'A', '[[!eo:Foo]] [[!fr:Foo]]' );
		$this->overrideConfigValues( [ MainConfigNames::MiserMode => true ] );
		$this->getServiceContainer()->getSpecialPageFactory()->getPage( 'WantedTranslations' )->recache( false );
		$this->insertPage( 'B', '[[!fr:Bar]]' );

		$this->assertSame(
			[ 'Foo(en)(word-separator)(parentheses: (nlinks: 1))' ], $this->rows( 'WantedTranslations', 'fr' )
		);
	}

	public function testNoProjects() {
		$this->insertPage( 'A', '[[!es:Foo]] [[Category:!es:Foo]]' );

		$this->assertSame( [], $this->rows( 'WantedTranslations' ) );
		$this->assertSame( [], $this->rows( 'WantedCategoryTranslations' ) );
	}
}
