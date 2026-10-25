<?php

namespace MediaWiki\Extension\Hermes\Tests\HermesLogFormatter;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\HermesLogFormatter
 */
class GetMessageKeyTest extends HermesIntegrationTestCase {

	private function format( string $action, string $wiki ): string {
		$entry = new ManualLogEntry( 'hermes', $action );
		$entry->setPerformer( $this->getTestUser()->getUser() );
		$entry->setTarget( SpecialPage::getTitleFor( 'HermesProjects' ) );
		$entry->setParameters( [ '4::language' => 'eo', '5::wiki' => $wiki ] );
		$formatter = $this->getServiceContainer()->getLogFormatterFactory()->newFromEntry( $entry );
		$context = new RequestContext();
		$context->setLanguage( 'qqx' );
		$formatter->setContext( $context );
		return $formatter->getPlainActionText();
	}

	public static function provideActions() {
		return [ [ 'add-project' ], [ 'activate' ], [ 'deactivate' ] ];
	}

	/** @dataProvider provideActions */
	public function testNamesOtherWiki( string $action ) {
		$text = $this->format( $action, 'otherwiki' );

		$this->assertStringContainsString( "(logentry-hermes-$action:", $text );
		$this->assertStringContainsString( 'otherwiki', $text );
	}

	/** @dataProvider provideActions */
	public function testOmitsLocalWiki( string $action ) {
		$text = $this->format( $action, WikiMap::getCurrentWikiId() );

		$this->assertStringContainsString( "(logentry-hermes-$action-local:", $text );
	}
}
