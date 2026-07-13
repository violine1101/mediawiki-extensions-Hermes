<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProjectHooks;

use MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProjectHooks::onGetUserPermissionsErrors
 */
class OnGetUserPermissionsErrorsTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request self-registration, which
		// addProjectLanguage() alone doesn't trigger for the *current* wiki's base language.
		LanguageStore::init();
		LanguageStore::addProjectLanguage( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function checkResult( string $titleText, string $action = 'create' ) {
		$title = Title::newFromText( $titleText );
		$user = $this->getTestUser()->getUser();
		$result = null;
		( new TranslationProjectHooks() )->onGetUserPermissionsErrors( $title, $user, $action, $result );

		return $result;
	}

	private function isAllowed( string $titleText, string $action = 'create' ): bool {
		$result = null;
		$title = Title::newFromText( $titleText );
		$user = $this->getTestUser()->getUser();

		$hooks = new TranslationProjectHooks();
		return $hooks->onGetUserPermissionsErrors( $title, $user, $action, $result ) !== false;
	}

	public function testAllowsMainNamespace() {
		$this->assertTrue( $this->isAllowed( '!eo:Foo' ) );
	}

	public function testAllowsOtherNamespace() {
		$this->assertTrue( $this->isAllowed( 'Category:!eo:Foo' ) );
	}

	public function testDeniesUnregisteredLanguage() {
		$this->assertFalse( $this->isAllowed( '!zz:Foo' ) );
		$this->assertSame( 'hermes-invalid-project-title', $this->checkResult( '!zz:Foo' ) );
	}

	public function testDeniesMalformedTitle() {
		$this->assertFalse( $this->isAllowed( '!Foo' ) );
		$this->assertSame( 'hermes-invalid-project-title', $this->checkResult( '!Foo' ) );
	}

	public function testAllowsOrdinaryTitle() {
		$this->assertTrue( $this->isAllowed( 'Foo' ) );
	}

	public function testAllowsReadAction() {
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'read' ) );
	}

	public function testAllowsDeleteAction() {
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'delete' ) );
	}

	public function testDeniesMoveTargetAction() {
		$this->assertFalse( $this->isAllowed( '!zz:Foo', 'move-target' ) );
	}

	public function testAllowsEditAction() {
		// Editing an existing page doesn't need re-checking here: MediaWiki already checks
		// 'create' (in addition to 'edit') whenever the target page doesn't exist yet, so
		// 'create' alone is sufficient to gate new bang-title pages from ever being made.
		$this->assertTrue( $this->isAllowed( '!zz:Foo', 'edit' ) );
	}
}
