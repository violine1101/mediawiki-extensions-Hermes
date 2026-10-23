<?php

namespace MediaWiki\Extension\Hermes\Tests\DisplayLanguage;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\User;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\DisplayLanguage::forUser
 */
class ForUserTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'LanguageCode', 'en' );
	}

	private function userWithOptions( array $options ): User {
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		foreach ( $options as $name => $value ) {
			$manager->setOption( $user, $name, $value );
		}
		return $user;
	}

	public function testDefaultIsContentLanguage() {
		$user = $this->getTestUser()->getUser();

		$this->assertSame( 'eo', DisplayLanguage::forUser( $user, 'eo' ) );
	}

	public function testIgnoresCoreLanguageOption() {
		$user = $this->userWithOptions( [ 'language' => 'fr' ] );

		$this->assertSame( 'en', DisplayLanguage::forUser( $user, 'en' ) );
	}

	public function testSpoken() {
		$user = $this->userWithOptions( [
			DisplayLanguage::OPTION => DisplayLanguage::SPOKEN,
			DisplayLanguage::OPTION_PREFERRED => 'fr',
			DisplayLanguage::OPTION_SPOKEN => "de\neo",
		] );

		$this->assertSame( 'eo', DisplayLanguage::forUser( $user, 'eo' ) );
		$this->assertSame( 'fr', DisplayLanguage::forUser( $user, 'en' ) );
	}

	public function testExplicitVariant() {
		$this->overrideConfigValue( 'UsePigLatinVariant', true );
		$user = $this->userWithOptions( [ 'variant-en' => 'en-x-piglatin' ] );

		$this->assertSame( 'en-x-piglatin', DisplayLanguage::forUser( $user, 'en' ) );
	}

	public function testDefaultVariantDoesNotWinOverSpokenVariant() {
		$user = $this->userWithOptions( [
			DisplayLanguage::OPTION => DisplayLanguage::SPOKEN,
			DisplayLanguage::OPTION_SPOKEN => 'zh-hant',
			'variant-zh' => 'zh',
		] );

		$this->assertSame( 'zh-hant', DisplayLanguage::forUser( $user, 'zh' ) );
	}

	public function testIgnoresVariantForAnonymous() {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$this->assertSame( 'zh', DisplayLanguage::forUser( $user, 'zh' ) );
	}

	private function useRequest( array $params, array $headers = [] ): void {
		$request = new FauxRequest( $params );
		$request->setHeaders( $headers );
		RequestContext::getMain()->setRequest( $request );
		// Converters memoize the requested variant.
		$this->resetServices();
	}

	public function testRequestedVariant() {
		$this->overrideConfigValue( 'UsePigLatinVariant', true );
		$this->useRequest( [ 'variant' => 'en-x-piglatin' ] );
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$this->assertSame( 'en-x-piglatin', DisplayLanguage::forUser( $user, 'en' ) );
		$this->assertSame( 'eo', DisplayLanguage::forUser( $user, 'eo' ) );
	}

	public function testRequestedVariantWinsOverChosenVariant() {
		$this->useRequest( [ 'variant' => 'zh-tw' ] );
		$user = $this->userWithOptions( [ 'variant-zh' => 'zh-cn' ] );

		$this->assertSame( 'zh-tw', DisplayLanguage::forUser( $user, 'zh' ) );
	}

	public function testAcceptLanguageVariantForAnonymous() {
		$this->useRequest( [], [ 'Accept-Language' => 'zh-tw' ] );
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();

		$this->assertSame( 'zh-tw', DisplayLanguage::forUser( $user, 'zh' ) );
	}
}
