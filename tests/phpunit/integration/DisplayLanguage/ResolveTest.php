<?php

namespace MediaWiki\Extension\Hermes\Tests\DisplayLanguage;

use MediaWiki\Extension\Hermes\DisplayLanguage;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\DisplayLanguage::resolve
 * @covers \MediaWiki\Extension\Hermes\DisplayLanguage::findVariant
 */
class ResolveTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'LanguageCode', 'en' );
	}

	public static function provideResolve(): array {
		$none = [];
		return [
			'default' => [ DisplayLanguage::DEFAULT, 'eo', 'fr', [], $none, 'eo' ],
			'default, variant' => [ DisplayLanguage::DEFAULT, 'zh', '', [], [ 'zh' => 'zh-tw' ], 'zh-tw' ],
			'base' => [ DisplayLanguage::BASE, 'eo', 'fr', [], $none, 'en' ],
			'base, variant' => [ DisplayLanguage::BASE, 'eo', '', [], [ 'en' => 'en-x-piglatin' ], 'en-x-piglatin' ],
			'spoken, preferred is content language' => [ DisplayLanguage::SPOKEN, 'eo', 'eo', [], $none, 'eo' ],
			'spoken, speaks content language' => [ DisplayLanguage::SPOKEN, 'eo', 'fr', [ 'eo' ], $none, 'eo' ],
			'spoken, falls back to preferred' => [ DisplayLanguage::SPOKEN, 'eo', 'fr', [ 'de' ], $none, 'fr' ],
			'spoken, preferred variant' => [ DisplayLanguage::SPOKEN, 'eo', 'zh', [], [ 'zh' => 'zh-tw' ], 'zh-tw' ],
			'spoken, preferred not chosen' => [ DisplayLanguage::SPOKEN, 'eo', '', [ 'de' ], $none, 'en' ],
			'spoken, invalid preferred' => [ DisplayLanguage::SPOKEN, 'eo', 'not a language', [], $none, 'en' ],
			'spoken, speaks a variant' => [ DisplayLanguage::SPOKEN, 'zh', 'fr', [ 'zh-hant' ], $none, 'zh-hant' ],
			'spoken, chosen variant' => [ DisplayLanguage::SPOKEN, 'zh', 'zh-hk', [], [ 'zh' => 'zh-cn' ], 'zh-cn' ],
		];
	}

	/** @dataProvider provideResolve */
	public function testResolve(
		string $mode, string $content, string $preferred, array $spoken, array $variants, string $expected
	) {
		$variantOf = static fn ( string $code ) => $variants[ $code ] ?? null;

		$this->assertSame( $expected, DisplayLanguage::resolve( $mode, $content, $preferred, $spoken, $variantOf ) );
	}
}
