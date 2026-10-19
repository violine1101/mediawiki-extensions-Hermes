<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getDisplayTitle
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getLocalizedNamespaceText
 */
class GetDisplayTitleTest extends HermesIntegrationTestCase {

	public function testUsesSpaces() {
		$title = ProjectTitle::makeTitle( NS_USER_TALK, 'Foo_Bar', null, 'Some_Section', 'User_talk' );

		$this->assertSame( 'User talk:Foo Bar#Some Section', $title->getDisplayTitle( true ) );
	}

	public function testTranslatesNamespaceForProjectPage() {
		$title = ProjectTitle::makeTitle( NS_TALK, 'Foo_Bar', 'eo', null, 'Talk' );

		// "Diskuto" is Esperanto for "Talk".
		$this->assertSame( 'Diskuto:Foo Bar', $title->getDisplayTitle( true ) );
	}

	public function testWithoutNamespace() {
		$title = ProjectTitle::makeTitle( NS_TALK, 'Foo_Bar', 'eo', null, 'Talk' );

		$this->assertSame( 'Foo Bar', $title->getDisplayTitle() );
	}

	public function testUsesGenderedUserNamespaceForProjectPage() {
		$user = $this->getMutableTestUser()->getUser();
		$optionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$optionsManager->setOption( $user, 'gender', 'female' );
		$optionsManager->saveOptions( $user );

		$title = ProjectTitle::makeTitle( NS_USER, $user->getName() . '/Sub', 'eo', null, 'User' );

		// "Uzantino" is the female form of Esperanto "Uzanto" ("User").
		$this->assertSame( "Uzantino:{$user->getName()}/Sub", $title->getDisplayTitle( true ) );
		$this->assertSame( 'Uzantino', $title->getLocalizedNamespaceText() );
	}

	public function testUnknownGenderUsesDefaultUserNamespace() {
		$user = $this->getMutableTestUser()->getUser();

		$title = ProjectTitle::makeTitle( NS_USER_TALK, $user->getName(), 'eo', null, 'User_talk' );

		$this->assertSame( 'Uzanto-Diskuto', $title->getLocalizedNamespaceText() );
	}
}
