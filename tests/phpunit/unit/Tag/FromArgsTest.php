<?php

namespace MediaWiki\Extension\Hermes\Tests\Tag;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag::fromArgs
 */
class FromArgsTest extends MediaWikiUnitTestCase {

	public function testMapsAllArguments() {
		$tags = Tag::fromArgs( [ 'tag_a', 'tag_b#section' ] );

		$this->assertCount( 2, $tags );
		$this->assertSame( 'tag a', $tags[ 0 ]->name );
		$this->assertSame( 'tag b', $tags[ 1 ]->name );
		$this->assertSame( 'section', $tags[ 1 ]->section );
	}

	public function testThrowsOnInvalidArgument() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ 'valid_tag', 'invalid tag!' ] );
	}

	public function testPageLevelOrder() {
		$tags = Tag::fromArgs( [ 'tag_a', 'tag_b', 'tag_c' ] );

		$this->assertSame( 0, $tags[ 0 ]->order );
		$this->assertSame( 1, $tags[ 1 ]->order );
		$this->assertSame( 2, $tags[ 2 ]->order );
	}

	public function testOrderPerSection() {
		$tags = Tag::fromArgs( [
			'page_level_a',
			'detail_a#Details',
			'page_level_b',
			'detail_b#Details',
			'other#Other Section',
		] );

		// Page-level tags (no section) share one sequence: page_level_a, page_level_b.
		$this->assertSame( 0, $tags[ 0 ]->order );
		$this->assertSame( 1, $tags[ 2 ]->order );

		// Tags under the "Details" section share their own independent sequence:
		// detail_a#Details, detail_b#Details.
		$this->assertSame( 0, $tags[ 1 ]->order );
		$this->assertSame( 1, $tags[ 3 ]->order );

		// A different section starts its own sequence at 0 too: other#Other Section.
		$this->assertSame( 0, $tags[ 4 ]->order );
	}

	public function testThrowsOnDuplicateTagName() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', 'other_tag', 'shared_tag' ] );
	}

	public function testThrowsOnDuplicateAcrossSections() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', 'shared_tag#Some Section' ] );
	}

	public function testDuplicateExceptionCarriesName() {
		try {
			Tag::fromArgs( [ 'shared_tag', 'shared_tag' ] );
			$this->fail( 'Expected DuplicateTagException to be thrown' );
		} catch ( DuplicateTagException $e ) {
			$this->assertSame( 'shared tag', $e->tagName );
		}
	}
}
