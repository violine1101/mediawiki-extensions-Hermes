<?php

namespace MediaWiki\Extension\Hermes\Tests\InterwikiSync;

use MediaWiki\Extension\Hermes\InterwikiSync;
use MediaWiki\Extension\Hermes\Tests\HermesIntegrationTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Hermes\InterwikiSync
 */
class SyncTest extends HermesIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerBaseLanguage( 'dewiki', 'de', url: 'https://de.example.org/wiki/$1' );
		$this->registerBaseLanguage( 'frwiki', 'fr', url: 'https://fr.example.org/wiki/$1' );
	}

	private function getRows(): array {
		$rows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'iw_prefix', 'iw_url', 'iw_wikiid' ] )
			->from( 'interwiki' )
			->caller( __METHOD__ )
			->fetchResultSet();
		$result = [];
		foreach ( $rows as $row ) {
			$result[ $row->iw_prefix ] = [ $row->iw_url, $row->iw_wikiid ];
		}
		ksort( $result );
		return $result;
	}

	private function insertRow( string $prefix, string $url, string $wikiId ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'interwiki' )
			->row( [
				'iw_prefix' => $prefix,
				'iw_url' => $url,
				'iw_api' => '',
				'iw_wikiid' => $wikiId,
				'iw_local' => 0,
				'iw_trans' => 0,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public function testAddsOtherWikisBaseLanguages() {
		$this->registerProjectLanguage( 'dewiki', 'eo' );

		$this->assertSame(
			[ 'de' => InterwikiSync::ADDED, 'fr' => InterwikiSync::ADDED ],
			InterwikiSync::sync()
		);
		$this->assertSame( [
			'de' => [ 'https://de.example.org/wiki/$1', 'dewiki' ],
			'fr' => [ 'https://fr.example.org/wiki/$1', 'frwiki' ],
		], $this->getRows() );
		$this->assertSame(
			'https://de.example.org/wiki/Golem',
			$this->getServiceContainer()->getInterwikiLookup()->fetch( 'de' )->getURL( 'Golem' )
		);
	}

	public function testSecondRunChangesNothing() {
		InterwikiSync::sync();

		$this->assertSame( [], InterwikiSync::sync() );
	}

	public function testUpdatesAndRemovesOwnRows() {
		$this->insertRow( 'de', 'https://old.example.org/$1', 'dewiki' );
		$this->insertRow( 'xx', 'https://gone.example.org/$1', 'frwiki' );
		$this->insertRow( 'en', 'https://self.example.org/$1', WikiMap::getCurrentWikiId() );

		$this->assertSame( [
			'de' => InterwikiSync::UPDATED,
			'en' => InterwikiSync::REMOVED,
			'fr' => InterwikiSync::ADDED,
			'xx' => InterwikiSync::REMOVED,
		], InterwikiSync::sync() );
		$this->assertSame( [
			'de' => [ 'https://de.example.org/wiki/$1', 'dewiki' ],
			'fr' => [ 'https://fr.example.org/wiki/$1', 'frwiki' ],
		], $this->getRows() );
	}

	public function testLeavesOtherRowsAlone() {
		$this->insertRow( 'de', 'https://de.wikipedia.org/wiki/$1', '' );
		$this->insertRow( 'wikipedia', 'https://en.wikipedia.org/wiki/$1', '' );

		$this->assertSame(
			[ 'de' => InterwikiSync::CONFLICT, 'fr' => InterwikiSync::ADDED ],
			InterwikiSync::sync()
		);
		$this->assertSame( [ 'https://de.wikipedia.org/wiki/$1', '' ], $this->getRows()['de'] );
		$this->assertArrayHasKey( 'wikipedia', $this->getRows() );
	}

	public function testDryRunChangesNothing() {
		$this->assertSame(
			[ 'de' => InterwikiSync::ADDED, 'fr' => InterwikiSync::ADDED ],
			InterwikiSync::sync( true )
		);
		$this->assertSame( [], $this->getRows() );
	}
}
