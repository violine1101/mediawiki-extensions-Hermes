<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\TranslateHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tag;
use MediaWiki\Extension\Hermes\TagStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks::onSkinTemplateNavigation__Universal
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getLocalTranslationProjects
 */
class OnSkinTemplateNavigationUniversalTest extends HermesIntegrationTestCase {

	private string $wiki;

	protected function setUp(): void {
		parent::setUp();
		$this->wiki = WikiMap::getCurrentWikiId();
		$this->registerBaseProject( 'de', 'dewiki' );
		$this->registerTranslationProject( 'es', 'dewiki' );
		$this->registerTranslationProject( 'eo', $this->wiki );
		$this->registerTranslationProject( 'fr', $this->wiki );
		$this->insertPage( 'Foo' );
	}

	/** @return ?string The Translate action's URL */
	private function getAction( string $title ): ?string {
		$context = new RequestContext();
		$context->setTitle( Title::newFromText( $title ) );
		$skin = $this->getServiceContainer()->getSkinFactory()->makeSkin( 'fallback' );
		$skin->setContext( $context );
		$links = [ 'actions' => [] ];
		( new TranslateHooks(
			$this->getServiceContainer()->getRevisionLookup(),
			$this->getServiceContainer()->getContentHandlerFactory()
		) )
			->onSkinTemplateNavigation__Universal( $skin, $links );
		return $links['actions']['hermes-translate']['href'] ?? null;
	}

	public function testUntranslated() {
		$this->assertSame(
			Title::newFromText( 'Special:HermesTranslate/Foo' )->getLocalURL(), $this->getAction( 'Foo' )
		);
	}

	public function testPartlyTranslated() {
		$this->insertPage( '!eo:Foo' );

		$this->assertNotNull( $this->getAction( 'Foo' ) );
	}

	public function testTranslatedEverywhere() {
		// One translation with the same title, one linked by a tag.
		$this->insertPage( '!eo:Foo' );
		$this->insertPage( '!fr:Fou' );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, 'Foo' ), Tag::fromArgs( [ 'foo' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!fr:Fou' ), Tag::fromArgs( [ 'foo' ] ) );

		$this->assertNull( $this->getAction( 'Foo' ) );
	}

	public function testFallbackIsNoTranslation() {
		$this->insertPage( '!eo:Foo' );
		$this->insertPage( '!fr:Fou' );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, 'Foo' ), Tag::fromArgs( [ 'foo', 'bar' ] ) );
		TagStore::setTagsForPage( $this->makePageInfo( $this->wiki, '!fr:Fou' ), Tag::fromArgs( [ 'bar' ] ) );

		$this->assertNotNull( $this->getAction( 'Foo' ) );
	}

	public static function provideNoAction() {
		return [
			'translation' => [ '!eo:Bar', true ],
			'missing page' => [ 'Bar', false ],
			'namespace without projects' => [ 'File:Bar.png', true ],
		];
	}

	/** @dataProvider provideNoAction */
	public function testNoAction( string $title, bool $create ) {
		if ( $create ) {
			$this->insertPage( $title );
		}

		$this->assertNull( $this->getAction( $title ) );
	}

	public function testNoProjects() {
		Hermes::getDB( DB_PRIMARY )->newDeleteQueryBuilder()
			->deleteFrom( 'hermes_projects' )
			->where( [ 'hp_wiki' => $this->wiki, 'hp_base' => 0 ] )
			->caller( __METHOD__ )
			->execute();
		ProjectStore::clearCacheForTesting();

		$this->assertNull( $this->getAction( 'Foo' ) );
	}
}
