<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\TranslateHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\TranslateHooks
 */
class OnEditFormPreloadTextTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
		$this->insertPage( 'Foo', "Base text\n{{#hermes:foo}}" );
	}

	private function preload( string $title, string $source = 'Foo', string $param = TranslateHooks::PARAM ): string {
		RequestContext::getMain()->setRequest( new FauxRequest( [ $param => $source ] ) );
		$text = 'unchanged';
		( new TranslateHooks(
			$this->getServiceContainer()->getRevisionLookup(),
			$this->getServiceContainer()->getContentHandlerFactory()
		) )->onEditFormPreloadText( $text, Title::newFromText( $title ) );
		return $text;
	}

	public function testPreloadsRedirect() {
		$this->assertSame(
			'#REDIRECT [[!eo:Fooo]]', $this->preload( '!eo:Foo', '!eo:Fooo', TranslateHooks::REDIRECT_PARAM )
		);
	}

	public function testRedirectOnlyWithinProject() {
		$this->registerProjectLanguage( WikiMap::getCurrentWikiId(), 'fr' );

		$this->assertSame( 'unchanged', $this->preload( '!eo:Foo', 'Fooo', TranslateHooks::REDIRECT_PARAM ) );
		$this->assertSame( 'unchanged', $this->preload( '!eo:Foo', '!fr:Fooo', TranslateHooks::REDIRECT_PARAM ) );
		$this->assertSame( 'unchanged', $this->preload( 'Foo', 'Fooo', TranslateHooks::REDIRECT_PARAM ) );
	}

	public function testPreloadsBasePage() {
		$this->assertSame( "Base text\n{{#hermes:foo}}", $this->preload( '!eo:Foo' ) );
		$this->assertSame( "Base text\n{{#hermes:foo}}", $this->preload( '!eo:Fooo' ) );
	}

	public function testNeedsSource() {
		$this->assertSame( 'unchanged', $this->preload( '!eo:Foo', '' ) );
	}

	public function testNotAProjectPage() {
		$this->assertSame( 'unchanged', $this->preload( 'Bar' ) );
	}

	public function testMissingSource() {
		$this->assertSame( 'unchanged', $this->preload( '!eo:Bar', 'Bar' ) );
	}

	public function testSourceInProject() {
		$this->insertPage( '!eo:Baz', 'Translated' );

		$this->assertSame( 'unchanged', $this->preload( '!eo:Bar', '!eo:Baz' ) );
	}

	public function testThroughEditPage() {
		RequestContext::getMain()->setRequest( new FauxRequest( [ TranslateHooks::PARAM => 'Foo' ] ) );
		$content = $this->getServiceContainer()->getPreloadedContentBuilder()->getPreloadedContent(
			Title::newFromText( '!eo:Fooo' )->toPageIdentity(), $this->getTestUser()->getAuthority(), null, [], null
		);
		$this->assertSame( "Base text\n{{#hermes:foo}}", $content->serialize() );
	}
}
