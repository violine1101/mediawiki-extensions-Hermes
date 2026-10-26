<?php

namespace MediaWiki\Extension\Hermes\Tests\Special\TagFormatter;

use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Extension\Hermes\Special\TagFormatter;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Language\Language;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Special\TagFormatter::externalLink
 */
class ExternalLinkTest extends HermesIntegrationTestCase {

	private function language( string $code ): Language {
		return $this->getServiceContainer()->getLanguageFactory()->getLanguage( $code );
	}

	public function testWithoutUrlGivesPlainText() {
		$project = new Project( 'de', 'dewiki', true, true, null );
		$title = ProjectTitle::make( $project, NS_MAIN, 'Foo_<b>', '', 1 );

		$this->assertSame( 'Foo &lt;b&gt;', TagFormatter::externalLink( $title, $this->language( 'en' ) ) );
	}

	public function testProjectPageTarget() {
		$this->registerTranslationProject( 'eo', 'trwiki', url: 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::make( self::getProject( 'eo', 'trwiki' ), NS_MAIN, 'BuildLinkProjectTarget', '', 1 );

		$this->assertSame(
			'<a href="https://translate.example.org/wiki/!eo:BuildLinkProjectTarget" class="extiw" '
				. 'title="BuildLinkProjectTarget – Esperanto">BuildLinkProjectTarget</a>',
			TagFormatter::externalLink( $title, $this->language( 'en' ) )
		);
	}

	public function testNamespacedProjectPageTarget() {
		$this->registerTranslationProject( 'eo', 'trwiki', url: 'https://translate.example.org/wiki/$1' );

		$title = ProjectTitle::make(
			self::getProject( 'eo', 'trwiki' ), NS_CATEGORY, 'BuildLinkNamespacedProjectTarget', 'Category', 1
		);

		// The "!xx:" prefix must land after the namespace, not before it. The link text uses the
		// Esperanto namespace name, the URL the wiki's own.
		$this->assertSame(
			'<a href="https://translate.example.org/wiki/Category:!eo:BuildLinkNamespacedProjectTarget" '
				. 'class="extiw" title="Kategorio:BuildLinkNamespacedProjectTarget – Esperanto">'
				. 'Kategorio:BuildLinkNamespacedProjectTarget</a>',
			TagFormatter::externalLink( $title, $this->language( 'en' ) )
		);
	}

	public function testSection() {
		$this->registerBaseProject( 'de', 'dewiki', url: 'https://de.example.org/wiki/$1' );

		$title = ProjectTitle::make( self::getProject( null, 'dewiki' ), NS_MAIN, 'Golem', '', 1 );

		$this->assertSame(
			'<a href="https://de.example.org/wiki/Golem#Eisengolem" class="extiw" '
				. 'title="Golem#Eisengolem – German">Golem#Eisengolem</a>',
			TagFormatter::externalLink( $title, $this->language( 'en' ), 'Eisengolem' )
		);
	}

	public function testTooltipInGivenLanguage() {
		$this->registerBaseProject( 'de', 'dewiki', url: 'https://de.example.org/wiki/$1' );

		$title = ProjectTitle::make( self::getProject( null, 'dewiki' ), NS_MAIN, 'Golem', '', 1 );

		$this->assertStringContainsString(
			'title="Golem – Deutsch"', TagFormatter::externalLink( $title, $this->language( 'de' ) )
		);
	}
}
