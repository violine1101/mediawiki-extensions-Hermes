<?php

namespace MediaWiki\Extension\Hermes\Tests\TranslationProject\TalkPageHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\Hooks\TranslationProject\TalkPageHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\TranslationProject\TalkPageHooks
 */
class NewMessagesTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Simulates InitHooks::onBeforeInitialize()'s real-request base project setup, which
		// addTranslationProject() alone doesn't trigger for the *current* wiki.
		ProjectStore::init();
		ProjectStore::addTranslationProject( 'eo', WikiMap::getCurrentWikiId() );
	}

	private function hasNewMessages( User $user ): bool {
		$manager = $this->getServiceContainer()->getTalkPageNotificationManager();
		$manager->clearInstanceCache( $user );
		return $manager->userHasNewMessages( $user );
	}

	public function testEditNotifiesUser() {
		$recipient = $this->getMutableTestUser()->getUser();
		$author = $this->getTestSysop()->getUser();

		$this->editPage( "User talk:!eo:{$recipient->getName()}", 'Hello', '', NS_MAIN, $author );

		$this->assertTrue( $this->hasNewMessages( $recipient ) );
	}

	public function testOwnEditDoesNotNotify() {
		$user = $this->getMutableTestUser()->getUser();

		$this->editPage( "User talk:!eo:{$user->getName()}", 'Note to self', '', NS_MAIN, $user );

		$this->assertFalse( $this->hasNewMessages( $user ) );
	}

	public function testViewingClearsNotification() {
		$recipient = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( "User talk:!eo:{$recipient->getName()}" );
		$this->editPage( $title, 'Hello', '', NS_MAIN, $this->getTestSysop()->getUser() );
		$this->assertTrue( $this->hasNewMessages( $recipient ) );

		$context = new RequestContext();
		$context->setUser( $recipient );
		$context->setTitle( $title );
		$text = '';
		( new TalkPageHooks() )->onOutputPageBeforeHTML( $context->getOutput(), $text );
		$this->runDeferredUpdates();

		$this->assertFalse( $this->hasNewMessages( $recipient ) );
	}
}
