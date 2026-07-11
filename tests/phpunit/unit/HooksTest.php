<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Hermes\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {
	public function testOnBeforePageDisplayVandalizeIsTrue() {
		$config = new HashConfig( [
			'VandalizeEachPage' => true
		] );
		$outputPageMock = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPageMock->method( 'getConfig' )
			->willReturn( $config );

		$outputPageMock->expects( $this->once() )
			->method( 'addHTML' )
			->with( '<p>Hermes was here</p>' );
		$outputPageMock->expects( $this->once() )
			->method( 'addModules' )
			->with( 'oojs-ui-core' );

		$skinMock = $this->getMockBuilder( Skin::class )
			->disableOriginalConstructor()
			->getMock();

		( new Hooks )->onBeforePageDisplay( $outputPageMock, $skinMock );
	}

	public function testOnBeforePageDisplayVandalizeFalse() {
		$config = new HashConfig( [
			'VandalizeEachPage' => false
		] );
		$outputPageMock = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$outputPageMock->method( 'getConfig' )
			->willReturn( $config );
		$outputPageMock->expects( $this->never() )
			->method( 'addHTML' );
		$outputPageMock->expects( $this->never() )
			->method( 'addModules' );
		$skinMock = $this->getMockBuilder( \Skin::class )
			->disableOriginalConstructor()
			->getMock();
		( new Hooks )->onBeforePageDisplay( $outputPageMock, $skinMock );
	}

}
