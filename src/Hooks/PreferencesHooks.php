<?php

namespace MediaWiki\Extension\Hermes\Hooks;

use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Preferences\Hook\PreferencesFormPreSaveHook;
use MediaWiki\User\Options\Hook\LoadUserOptionsHook;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserIdentity;

/**
 * Replaces core's interface-language preference with Hermes's display-language preferences (see
 * DisplayLanguage), and core's per-wiki "variant" preference with one "variant-<code>" preference per
 * language of the family: with preferences shared across wikis, "variant" would mean a different language
 * on each wiki.
 */
class PreferencesHooks implements
	GetPreferencesHook,
	PreferencesFormPreSaveHook,
	LoadUserOptionsHook,
	SaveUserOptionsHook
{

	private const SECTION = 'personal/i18n';

	public function __construct(
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly LanguageConverterFactory $languageConverterFactory,
	) {
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$languages = [];
		foreach ( $this->languageNameUtils->getLanguageNames(
			LanguageNameUtils::AUTONYMS, LanguageNameUtils::SUPPORTED
		) as $code => $name ) {
			$languages["$code - $name"] = $code;
		}
		ksort( $languages );

		$hideUnlessSpoken = [ '!==', DisplayLanguage::OPTION, DisplayLanguage::SPOKEN ];
		$ours = [
			DisplayLanguage::OPTION => [
				'type' => 'radio',
				'section' => self::SECTION,
				'label-message' => 'hermes-pref-displaylanguage',
				'options-messages' => [
					'hermes-pref-displaylanguage-default' => DisplayLanguage::DEFAULT,
					'hermes-pref-displaylanguage-base' => DisplayLanguage::BASE,
					'hermes-pref-displaylanguage-spoken' => DisplayLanguage::SPOKEN,
				],
			],
			DisplayLanguage::OPTION_PREFERRED => [
				'type' => 'select',
				'section' => self::SECTION,
				'label-message' => 'hermes-pref-displaylanguage-preferred',
				'options' => $languages,
				// The wiki's base language until chosen; not as the option's default, which (unlike a stored
				// choice) would mean a different language on each wiki. A set 'default' is never overridden
				// by the user's value, so that goes here too.
				'default' => DisplayLanguage::getPreferred(
					(string)$this->userOptionsManager->getOption( $user, DisplayLanguage::OPTION_PREFERRED )
				),
				'hide-if' => $hideUnlessSpoken,
			],
			DisplayLanguage::OPTION_SPOKEN => [
				'type' => 'orderedmultiselect',
				'section' => self::SECTION,
				'label-message' => 'hermes-pref-displaylanguage-spoken-label',
				'help-message' => 'hermes-pref-displaylanguage-spoken-help',
				'options' => $languages,
				'hide-if' => $hideUnlessSpoken,
			],
		];

		$variantLanguages = $this->languageConverterFactory->isConversionDisabled()
			? []
			: self::getLanguagesWithVariants();
		if ( $variantLanguages ) {
			unset( $preferences['variant'] );
		}
		// This wiki's languages, and those (or a variant of which) the user has saved as spoken. Others keep
		// their value (and core's 'api' field), as unsubmitted fields aren't saved.
		$shown = [ LanguageStore::getLocalBaseLanguage(), ...LanguageStore::getLocalProjectLanguages() ];
		$spoken = $this->getSavedSpokenLanguages( $user );
		foreach ( $variantLanguages as $code => $variants ) {
			if ( !in_array( $code, $shown, true ) && DisplayLanguage::findVariant( $code, $spoken ) === null ) {
				continue;
			}
			$options = [];
			foreach ( $variants as $variant ) {
				$options[ "$variant - " . $this->languageNameUtils->getLanguageName( $variant ) ] = $variant;
			}
			// Replaces core's own "variant-<code>" ('api' type) for languages other than the content language.
			unset( $preferences[ "variant-$code" ] );
			$ours[ "variant-$code" ] = [
				'type' => 'radio',
				'section' => self::SECTION,
				'label-message' => [ 'hermes-pref-variant', Hermes::getAutonym( $code ) . " ($code)" ],
				'options' => $options,
			];
		}

		self::replace( $preferences, 'language', $ours );
	}

	/**
	 * Keeps core's hidden "language" option, still used outside of requests (e.g. emails), close to the
	 * Hermes choice: the preferred language in "Languages I speak" mode, else the default. In "Languages I
	 * speak" mode, a listed variant also becomes the content variant of its language, unless one is chosen.
	 *
	 * @inheritDoc
	 */
	public function onPreferencesFormPreSave( $formData, $form, $user, &$result, $oldUserOptions ) {
		if ( !isset( $formData[ DisplayLanguage::OPTION ] ) ) {
			return;
		}
		$spokenMode = $formData[ DisplayLanguage::OPTION ] === DisplayLanguage::SPOKEN;
		$preferred = DisplayLanguage::getPreferred( $formData[ DisplayLanguage::OPTION_PREFERRED ] ?? '' );
		$this->userOptionsManager->setOption( $user, 'language', $spokenMode ? $preferred : null );
		if ( !$spokenMode ) {
			return;
		}

		$spoken = array_filter( [
			$preferred,
			...DisplayLanguage::parseSpoken( $formData[ DisplayLanguage::OPTION_SPOKEN ] ?? '' ),
		] );
		foreach ( array_keys( self::getLanguagesWithVariants() ) as $code ) {
			$option = "variant-$code";
			$variant = DisplayLanguage::findVariant( $code, $spoken );
			$default = $this->userOptionsManager->getDefaultOption( $option, $user );
			if ( $variant !== null && $variant !== $code
				&& ( $formData[ $option ] ?? $default ) === $default
			) {
				$this->userOptionsManager->setOption( $user, $option, $variant );
			}
		}
	}

	/** @inheritDoc */
	public function onLoadUserOptions( $user, array &$options ): void {
		$option = 'variant-' . LanguageStore::getLocalBaseLanguage();
		if ( isset( $options[ $option ] ) ) {
			$options['variant'] = $options[ $option ];
		}
	}

	/** @inheritDoc */
	public function onSaveUserOptions( $user, array &$modifiedOptions, array $originalOptions ) {
		if ( !array_key_exists( 'variant', $modifiedOptions ) ) {
			return;
		}
		$option = 'variant-' . LanguageStore::getLocalBaseLanguage();
		if ( isset( $originalOptions[ $option ] ) && !array_key_exists( $option, $modifiedOptions ) ) {
			$modifiedOptions[ $option ] = $modifiedOptions['variant'];
		}
		// Never stored itself: it's loaded from "variant-<content language>".
		$modifiedOptions['variant'] = null;
	}

	/** @return string[] The preferred and other spoken languages, if the user has saved that mode */
	private function getSavedSpokenLanguages( UserIdentity $user ): array {
		$options = $this->userOptionsManager;
		if ( $options->getOption( $user, DisplayLanguage::OPTION ) !== DisplayLanguage::SPOKEN ) {
			return [];
		}
		return [
			DisplayLanguage::getPreferred( (string)$options->getOption( $user, DisplayLanguage::OPTION_PREFERRED ) ),
			...DisplayLanguage::parseSpoken( $options->getOption( $user, DisplayLanguage::OPTION_SPOKEN ) ),
		];
	}

	/** @return array<string, string[]> The variants of each family language that has any, by language code */
	private static function getLanguagesWithVariants(): array {
		$codes = array_keys( LanguageStore::getLanguages() );
		sort( $codes );
		$variants = array_map( DisplayLanguage::getVariants( ... ), array_combine( $codes, $codes ) );
		return array_filter( $variants );
	}

	/** Puts $new in place of the preference $key (or at the end, if there is none) */
	private static function replace( array &$preferences, string $key, array $new ): void {
		$position = array_search( $key, array_keys( $preferences ), true );
		unset( $preferences[ $key ] );
		$preferences = $position === false
			? $preferences + $new
			: array_slice( $preferences, 0, $position, true ) + $new
				+ array_slice( $preferences, $position, null, true );
	}
}
