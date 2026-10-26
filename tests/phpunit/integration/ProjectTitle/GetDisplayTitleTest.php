<?php

namespace MediaWiki\Extension\Hermes\Tests\ProjectTitle;

use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getDisplayTitle
 * @covers \MediaWiki\Extension\Hermes\ProjectTitle::getLocalizedNamespaceText
 */
class GetDisplayTitleTest extends HermesIntegrationTestCase {

	public function testUsesSpaces() {
		$title = self::localTitle( NS_USER_TALK, 'Foo_Bar', null, 'User_talk' );

		$this->assertSame( 'User talk:Foo Bar#Some Section', $title->getDisplayTitle( true, 'Some_Section' ) );
	}

	public function testTranslatesNamespaceForProjectPage() {
		$title = self::localTitle( NS_TALK, 'Foo_Bar', 'eo', 'Talk' );

		// "Diskuto" is Esperanto for "Talk".
		$this->assertSame( 'Diskuto:Foo Bar', $title->getDisplayTitle( true ) );
	}

	public function testWithoutNamespace() {
		$title = self::localTitle( NS_TALK, 'Foo_Bar', 'eo', 'Talk' );

		$this->assertSame( 'Foo Bar', $title->getDisplayTitle() );
	}

	public function testUsesGenderedUserNamespaceForProjectPage() {
		$user = $this->getMutableTestUser()->getUser();
		$optionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$optionsManager->setOption( $user, 'gender', 'female' );
		$optionsManager->saveOptions( $user );

		$title = self::localTitle( NS_USER, $user->getName() . '/Sub', 'eo', 'User' );

		// "Uzantino" is the female form of Esperanto "Uzanto" ("User").
		$this->assertSame( "Uzantino:{$user->getName()}/Sub", $title->getDisplayTitle( true ) );
		$this->assertSame( 'Uzantino', $title->getLocalizedNamespaceText() );
	}

	public function testUnknownGenderUsesDefaultUserNamespace() {
		$user = $this->getMutableTestUser()->getUser();

		$title = self::localTitle( NS_USER_TALK, $user->getName(), 'eo', 'User_talk' );

		$this->assertSame( 'Uzanto-Diskuto', $title->getLocalizedNamespaceText() );
	}
}
