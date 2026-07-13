<?php

namespace MediaWiki\Extension\Hermes\Tests\LanguageLinkHooks;

use MediaWiki\Extension\Hermes\Decorators\InterwikiLookup;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\Hooks\LanguageLinkHooks::onMediaWikiServices
 */
class OnMediaWikiServicesTest extends HermesIntegrationTestCase {

	public function testWrapsInterwikiLookup() {
		// This only confirms that the "InterwikiLookup" service manipulator wiring is
		// actually in place - the decorator's own resolution behavior is covered by
		// Tests\InterwikiLookup\* against the class directly.
		$lookup = $this->getServiceContainer()->getInterwikiLookup();

		$this->assertInstanceOf( InterwikiLookup::class, $lookup );
	}
}
