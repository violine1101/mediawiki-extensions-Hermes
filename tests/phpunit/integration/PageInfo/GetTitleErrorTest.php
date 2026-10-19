<?php

namespace MediaWiki\Extension\Hermes\Tests\PageInfo;

use MediaWiki\Extension\Hermes\PageInfo;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\MainConfigNames;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\PageInfo::getTitleError
 */
class GetTitleErrorTest extends HermesIntegrationTestCase {

	private function makePage( ?string $project, string $title ): PageInfo {
		$page = new PageInfo();
		$page->translationProject = $project;
		$page->title = $title;
		return $page;
	}

	public function testValidTitle() {
		$this->assertNull( $this->makePage( 'eo', 'Foo' )->getTitleError() );
		$this->assertNull( $this->makePage( null, 'Foo' )->getTitleError() );
	}

	public function testInvalidProjectTitle() {
		$this->assertSame( 'hermes-invalid-project-title', $this->makePage( null, '!zz:Foo' )->getTitleError() );
	}

	public function testCapitalizationError() {
		$this->overrideConfigValue( MainConfigNames::CapitalLinks, true );

		$this->assertSame( 'hermes-title-not-capitalized', $this->makePage( 'eo', 'foo' )->getTitleError() );
	}
}
