<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\WantedPagesHooks;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\WikiMap\WikiMap;
use SpecialPageExecutor;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\WantedPagesHooks
 * @covers \MediaWiki\Extension\Hermes\Special\SpecialWantedCategories
 * @covers \MediaWiki\Extension\Hermes\Hermes::notProjectTitle
 */
class WantedPagesTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::MiserMode => false,
			MainConfigNames::WantedPagesThreshold => 1,
		] );
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function execute( string $page ): string {
		[ $html ] = ( new SpecialPageExecutor() )->executeSpecialPage(
			$this->getServiceContainer()->getSpecialPageFactory()->getPage( $page ), null, new FauxRequest()
		);
		return $html;
	}

	public function testHidesProjectPages() {
		$this->insertPage( 'Linking', '[[Wanted]] [[!eo:Wanted]] [[!zz:Reserved]] [[!Kung]] [[Talk:!eo:Wanted]]' );

		$html = $this->execute( 'Wantedpages' );

		$this->assertStringContainsString( '>Wanted</a>', $html );
		$this->assertStringContainsString( '>!Kung</a>', $html );
		$this->assertStringNotContainsString( '!eo:Wanted', $html );
		$this->assertStringNotContainsString( '!zz:Reserved', $html );
	}

	public function testKeepsNamespacesWithoutProjects() {
		$this->insertPage( 'Linking', '[[File:!eo:Wanted.png]] [[:File:!eo:Linked.png]]' );

		$this->assertStringContainsString( 'File:!eo:Linked.png', $this->execute( 'Wantedpages' ) );
	}

	public function testHidesProjectCategories() {
		$this->insertPage( 'Categorized', '[[Category:Wanted]] [[Category:!eo:Wanted]]' );

		$html = $this->execute( 'Wantedcategories' );

		$this->assertStringContainsString( '>Wanted</a>', $html );
		$this->assertStringNotContainsString( '!eo:Wanted', $html );
	}
}
