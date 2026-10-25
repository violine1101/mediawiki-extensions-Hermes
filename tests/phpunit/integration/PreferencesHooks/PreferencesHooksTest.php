<?php

namespace MediaWiki\Extension\Hermes\Tests\PreferencesHooks;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\Hooks\PreferencesHooks;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\PreferencesHooks
 */
class PreferencesHooksTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [ 'LanguageCode' => 'en', 'UsePigLatinVariant' => true ] );
		ProjectStore::init();
		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'zh' );
		ProjectStore::addTranslationProject( WikiMap::getCurrentWikiId(), 'eo' );
	}

	private function makeHooks(): PreferencesHooks {
		$services = $this->getServiceContainer();
		return new PreferencesHooks(
			$services->getLanguageNameUtils(),
			$services->getUserOptionsManager(),
			$services->getLanguageConverterFactory()
		);
	}

	public function testReplacesCoreLanguageAndVariantPreferences() {
		$preferences = [
			'realname' => [],
			'language' => [],
			'gender' => [],
			'variant' => [],
			'variant-zh' => [],
		];
		$this->makeHooks()->onGetPreferences( $this->getTestUser()->getUser(), $preferences );

		$this->assertSame( [
			'realname',
			DisplayLanguage::OPTION,
			DisplayLanguage::OPTION_PREFERRED,
			DisplayLanguage::OPTION_SPOKEN,
			'variant-en',
			'variant-zh',
			'gender',
		], array_keys( $preferences ) );
		$this->assertContains( 'zh-hant', $preferences['variant-zh']['options'] );
	}

	public static function provideVariantShownForOtherWiki(): array {
		return [
			'not spoken' => [ DisplayLanguage::DEFAULT, '', '', false ],
			'spoken' => [ DisplayLanguage::SPOKEN, '', "fr\nsr", true ],
			'variant spoken' => [ DisplayLanguage::SPOKEN, 'sr-el', '', true ],
			'listed, but not in spoken mode' => [ DisplayLanguage::DEFAULT, 'sr', 'sr', false ],
		];
	}

	/** @dataProvider provideVariantShownForOtherWiki */
	public function testVariantShownForOtherWikiOnlyIfSpoken(
		string $mode, string $preferred, string $spoken, bool $shown
	) {
		$this->registerBaseProject( 'otherwiki', 'sr' );
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		$manager->setOption( $user, DisplayLanguage::OPTION, $mode );
		$manager->setOption( $user, DisplayLanguage::OPTION_PREFERRED, $preferred );
		$manager->setOption( $user, DisplayLanguage::OPTION_SPOKEN, $spoken );
		$preferences = [];
		$this->makeHooks()->onGetPreferences( $user, $preferences );

		$this->assertArrayHasKey( 'variant-zh', $preferences );
		$this->assertSame( $shown, isset( $preferences['variant-sr'] ) );
	}

	public function testPreferredLanguagePreselectsBaseLanguage() {
		$preferences = [];
		$this->makeHooks()->onGetPreferences( $this->getTestUser()->getUser(), $preferences );

		$this->assertSame( 'en', $preferences[ DisplayLanguage::OPTION_PREFERRED ]['default'] );
		$this->assertNotContains( '', $preferences[ DisplayLanguage::OPTION_PREFERRED ]['options'] );
	}

	public function testPreferredLanguageShowsSavedChoice() {
		$user = $this->getTestUser()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()
			->setOption( $user, DisplayLanguage::OPTION_PREFERRED, 'fr' );
		$preferences = [];
		$this->makeHooks()->onGetPreferences( $user, $preferences );

		$this->assertSame( 'fr', $preferences[ DisplayLanguage::OPTION_PREFERRED ]['default'] );
	}

	public static function providePreSave(): array {
		return [
			'spoken sets preferred language' => [ DisplayLanguage::SPOKEN, 'fr', 'fr' ],
			'spoken without preferred uses base language' => [ DisplayLanguage::SPOKEN, '', 'en' ],
			'other modes reset' => [ DisplayLanguage::DEFAULT, 'fr', 'en' ],
		];
	}

	/** @dataProvider providePreSave */
	public function testPreSaveSyncsCoreLanguage( string $mode, string $preferred, string $expected ) {
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		$manager->setOption( $user, 'language', 'he' );

		$this->preSave( $user, [ DisplayLanguage::OPTION => $mode, DisplayLanguage::OPTION_PREFERRED => $preferred ] );

		$this->assertSame( $expected, $manager->getOption( $user, 'language' ) );
	}

	public function testPreSaveTakesVariantFromSpokenLanguages() {
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		$manager->setOption( $user, 'variant-en', 'en-x-piglatin' );

		$this->preSave( $user, [
			DisplayLanguage::OPTION => DisplayLanguage::SPOKEN,
			DisplayLanguage::OPTION_PREFERRED => 'zh-hant',
			DisplayLanguage::OPTION_SPOKEN => 'en',
			'variant-zh' => 'zh',
			'variant-en' => 'en-x-piglatin',
		] );

		$this->assertSame( 'zh-hant', $manager->getOption( $user, 'variant-zh' ) );
		$this->assertSame( 'en-x-piglatin', $manager->getOption( $user, 'variant-en' ) );
	}

	public function testPreSaveKeepsChosenVariant() {
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		$manager->setOption( $user, 'variant-zh', 'zh-cn' );

		$this->preSave( $user, [
			DisplayLanguage::OPTION => DisplayLanguage::SPOKEN,
			DisplayLanguage::OPTION_SPOKEN => 'zh-hant',
			'variant-zh' => 'zh-cn',
		] );

		$this->assertSame( 'zh-cn', $manager->getOption( $user, 'variant-zh' ) );
	}

	private function preSave( $user, array $formData ): void {
		$form = HTMLForm::factory( 'ooui', [], RequestContext::getMain() );
		$result = true;
		$this->makeHooks()->onPreferencesFormPreSave( $formData, $form, $user, $result, [] );
	}

	public function testLoadsVariantFromContentLanguageOption() {
		$options = [ 'variant' => 'zh-tw', 'variant-en' => 'en-x-piglatin' ];
		$this->makeHooks()->onLoadUserOptions( $this->getTestUser()->getUser(), $options );

		$this->assertSame( 'en-x-piglatin', $options['variant'] );
	}

	public function testSavesVariantAsContentLanguageOption() {
		$modified = [ 'variant' => 'en-x-piglatin' ];
		$this->makeHooks()->onSaveUserOptions( $this->getTestUser()->getUser(), $modified, [ 'variant-en' => 'en' ] );

		$this->assertSame( [ 'variant' => null, 'variant-en' => 'en-x-piglatin' ], $modified );
	}

	public function testVariantRoundTrip() {
		$user = $this->getTestUser()->getUser();
		$manager = $this->getServiceContainer()->getUserOptionsManager();
		$manager->setOption( $user, 'variant', 'en-x-piglatin' );
		$manager->saveOptions( $user );
		$manager->clearUserOptionsCache( $user );

		$this->assertSame( 'en-x-piglatin', $manager->getOption( $user, 'variant-en' ) );
		$this->assertSame( 'en-x-piglatin', $manager->getOption( $user, 'variant' ) );
	}
}
