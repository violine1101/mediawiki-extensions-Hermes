<?php

namespace MediaWiki\Extension\Hermes;

use MediaWiki\Language\ILanguageConverter;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;

/**
 * The interface language a user sees on a page, from their display-language preferences and the page's
 * content language (the wiki's base language, or a translation project's). Where a language has variants,
 * the interface follows the user's variant preference for it ("variant-<code>", see PreferencesHooks).
 */
class DisplayLanguage {

	public const OPTION = 'hermes-displaylanguage';
	public const OPTION_PREFERRED = 'hermes-displaylanguage-preferred';
	public const OPTION_SPOKEN = 'hermes-displaylanguage-spoken';

	/** The content language. */
	public const DEFAULT = 'default';
	/** The wiki's base language, also on its translation projects. */
	public const BASE = 'base';
	/**
	 * The content language if the user speaks it (or one of its variants), else their preferred language
	 * (by default the wiki's base language).
	 */
	public const SPOKEN = 'spoken';

	public static function forUser( UserIdentity $user, string $contentLanguage ): string {
		$lookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		// Only a requested (?variant=) or explicitly chosen variant: the default (the language's static default
		// variant) shouldn't win over a variant in the spoken languages.
		$variantOf = static function ( string $code ) use ( $user, $lookup ): ?string {
			if ( !self::getVariants( $code ) ) {
				return null;
			}
			$option = "variant-$code";
			$variant = $user->isRegistered() ? $lookup->getOption( $user, $option ) : null;
			$chosen = $variant !== $lookup->getDefaultOption( $option, $user ) ? $variant : null;
			return self::getConverter( $code )->getURLVariant() ?? self::validateVariant( $code, $chosen );
		};

		$language = self::resolve(
			(string)$lookup->getOption( $user, self::OPTION ),
			$contentLanguage,
			(string)$lookup->getOption( $user, self::OPTION_PREFERRED ),
			self::parseSpoken( $lookup->getOption( $user, self::OPTION_SPOKEN ) ),
			$variantOf
		);
		// A language with variants (only main codes have any) is shown in the variant core picks for its
		// content, e.g. also from the Accept-Language header or $wgDefaultLanguageVariant.
		return self::getVariants( $language )
			? self::getConverter( $language )->getPreferredVariant()
			: $language;
	}

	/**
	 * @param string $mode DEFAULT, BASE or SPOKEN
	 * @param string $contentLanguage A language of the current wiki
	 * @param string $preferred Preferred language code, or '' if not chosen yet
	 * @param string[] $spoken Other languages the user speaks
	 * @param callable $variantOf Returns the requested or chosen variant of a language code, or null
	 */
	public static function resolve(
		string $mode, string $contentLanguage, string $preferred, array $spoken, callable $variantOf
	): string {
		$withVariant = static fn ( string $code ) => $variantOf( $code ) ?? $code;
		if ( $mode === self::BASE ) {
			return $withVariant( LanguageStore::getLocalBaseLanguage() );
		}
		if ( $mode !== self::SPOKEN ) {
			return $withVariant( $contentLanguage );
		}

		$preferred = self::getPreferred( $preferred );
		$spokenVariant = self::findVariant( $contentLanguage, [ $preferred, ...$spoken ] );
		if ( $spokenVariant !== null ) {
			return $variantOf( $contentLanguage ) ?? $spokenVariant;
		}
		return $withVariant( $preferred );
	}

	/**
	 * @param string $language
	 * @param string[] $codes
	 * @return string|null The first of $codes that is $language or one of its variants
	 */
	public static function findVariant( string $language, array $codes ): ?string {
		$variants = self::getVariants( $language ) ?: [ $language ];
		foreach ( $codes as $code ) {
			if ( in_array( $code, $variants, true ) ) {
				return $code;
			}
		}
		return null;
	}

	/** @return string[] The variants of $language (including itself), or [] if it has none */
	public static function getVariants( string $language ): array {
		$converter = self::getConverter( $language );
		return $converter->hasVariants() ? $converter->getVariants() : [];
	}

	private static function getConverter( string $language ): ILanguageConverter {
		$services = MediaWikiServices::getInstance();
		return $services->getLanguageConverterFactory()
			->getLanguageConverter( $services->getLanguageFactory()->getLanguage( $language ) );
	}

	private static function validateVariant( string $language, mixed $variant ): ?string {
		return is_string( $variant ) && in_array( $variant, self::getVariants( $language ), true ) ? $variant : null;
	}

	/** The preferred language from its option value: the wiki's base language if not chosen (or invalid) */
	public static function getPreferred( string $value ): string {
		return self::isLanguage( $value ) ? $value : LanguageStore::getLocalBaseLanguage();
	}

	/** @return string[] Language codes, from the newline-separated option value */
	public static function parseSpoken( ?string $value ): array {
		return array_values( array_filter( explode( "\n", $value ?? '' ), self::isLanguage( ... ) ) );
	}

	public static function isLanguage( string $code ): bool {
		return MediaWikiServices::getInstance()->getLanguageNameUtils()->isSupportedLanguage( $code );
	}
}
