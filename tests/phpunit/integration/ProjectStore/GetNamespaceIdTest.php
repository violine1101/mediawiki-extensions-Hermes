<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectStore;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getNamespaceId
 * @covers \MediaWiki\Extension\Hermes\ProjectStore::getNamespaceText
 */
class GetNamespaceIdTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerNamespaces( 'dewiki', [ NS_MAIN => '', NS_CATEGORY => 'Kategorie' ] );
	}

	public function testKnownNamespace() {
		$this->assertSame( NS_CATEGORY, ProjectStore::getNamespaceId( 'dewiki', 'Kategorie' ) );
		$this->assertSame( 'Kategorie', ProjectStore::getNamespaceText( 'dewiki', NS_CATEGORY ) );
	}

	public function testUnknownNamespace() {
		$this->assertNull( ProjectStore::getNamespaceId( 'dewiki', 'Category' ) );
		$this->assertNull( ProjectStore::getNamespaceId( 'otherwiki', 'Kategorie' ) );
	}

	public function testUnknownNamespaceTextFallsBackToCanonical() {
		$this->assertSame( 'Template', ProjectStore::getNamespaceText( 'dewiki', NS_TEMPLATE ) );
		$this->assertSame( '', ProjectStore::getNamespaceText( 'otherwiki', NS_MAIN ) );
		$this->assertSame( '', ProjectStore::getNamespaceText( 'dewiki', 12345 ) );
	}
}
