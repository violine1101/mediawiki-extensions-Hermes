<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getDisplayTitle
 */
class GetDisplayTitleTest extends HermesIntegrationTestCase {

	private static function makeNamespacedPageInfo( int $namespace, string $namespaceText, string $title ): PageInfo {
		$page = new PageInfo();
		$page->namespace = $namespace;
		$page->namespaceText = $namespaceText;
		$page->title = $title;
		return $page;
	}

	public function testUsesSpaces() {
		$page = self::makeNamespacedPageInfo( NS_USER_TALK, 'User_talk', 'Foo_Bar' );
		$page->section = 'Some_Section';

		$this->assertSame( 'User talk:Foo Bar#Some Section', $page->getDisplayTitle( true ) );
	}

	public function testTranslatesNamespaceForProjectPage() {
		$page = self::makeNamespacedPageInfo( NS_TALK, 'Talk', 'Foo_Bar' );
		$page->translationProject = 'eo';

		// "Diskuto" is Esperanto for "Talk".
		$this->assertSame( 'Diskuto:Foo Bar', $page->getDisplayTitle( true ) );
		$this->assertSame( 'Talk:Foo_Bar', $page->getVirtualTitle() );
	}

	public function testWithoutNamespace() {
		$page = self::makeNamespacedPageInfo( NS_TALK, 'Talk', 'Foo_Bar' );
		$page->translationProject = 'eo';

		$this->assertSame( 'Foo Bar', $page->getDisplayTitle() );
	}

	public function testUsesGenderedUserNamespaceForProjectPage() {
		$user = $this->getMutableTestUser()->getUser();
		$optionsManager = $this->getServiceContainer()->getUserOptionsManager();
		$optionsManager->setOption( $user, 'gender', 'female' );
		$optionsManager->saveOptions( $user );

		$page = self::makeNamespacedPageInfo( NS_USER, 'User', $user->getName() . '/Sub' );
		$page->translationProject = 'eo';

		// "Uzantino" is the female form of Esperanto "Uzanto" ("User").
		$this->assertSame( "Uzantino:{$user->getName()}/Sub", $page->getDisplayTitle( true ) );
		$this->assertSame( 'Uzantino', $page->getLocalizedNamespaceText() );
	}

	public function testUnknownGenderUsesDefaultUserNamespace() {
		$user = $this->getMutableTestUser()->getUser();

		$page = self::makeNamespacedPageInfo( NS_USER_TALK, 'User_talk', $user->getName() );
		$page->translationProject = 'eo';

		$this->assertSame( 'Uzanto-Diskuto', $page->getLocalizedNamespaceText() );
	}
}
