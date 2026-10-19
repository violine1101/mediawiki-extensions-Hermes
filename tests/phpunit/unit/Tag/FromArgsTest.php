<?php

namespace MediaWiki\Extension\Hermes\Tests\Tag;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidSectionException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag::fromArgs
 */
class FromArgsTest extends MediaWikiUnitTestCase {

	public function testMapsAllArguments() {
		$tags = Tag::fromArgs( [ 'tag_a', 'tag_b=#section' ] );

		$this->assertCount( 2, $tags );
		$this->assertSame( 'tag_a', $tags[ 0 ]->name );
		$this->assertNull( $tags[ 0 ]->section );
		$this->assertSame( 'tag_b', $tags[ 1 ]->name );
		$this->assertSame( 'section', $tags[ 1 ]->section );
	}

	public function testSectionAppliesOnlyToItsOwnTag() {
		$tags = Tag::fromArgs( [
			'page_tag', ' tag_a = #Some Section ', 'tag_b', 'tag_c=#Other', 'tag_d=#Some Section',
		] );

		$this->assertSame(
			[ null, 'Some_Section', null, 'Other', 'Some_Section' ],
			array_map( static fn ( Tag $tag ) => $tag->section, $tags )
		);
		$this->assertSame(
			[ 'page_tag', 'tag_a', 'tag_b', 'tag_c', 'tag_d' ],
			array_map( static fn ( Tag $tag ) => $tag->name, $tags )
		);
	}

	public function testRejectsOldSectionSuffix() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ 'tag#section' ] );
	}

	public function testRejectsOldSectionPrefix() {
		$this->expectException( InvalidSectionException::class );
		Tag::fromArgs( [ '#section=tag' ] );
	}

	public function testRejectsEmptySection() {
		$this->expectException( InvalidSectionException::class );
		Tag::fromArgs( [ 'tag_a', ' tag_b = #  ' ] );
	}

	public function testRejectsSectionWithoutHash() {
		$this->expectException( InvalidSectionException::class );
		Tag::fromArgs( [ 'tag_a=section' ] );
	}

	public function testRejectsSectionWithoutTag() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ '=#section' ] );
	}

	public function testThrowsOnInvalidArgument() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ 'valid_tag', 'invalid tag!' ] );
	}

	public function testPriorityFollowsArguments() {
		$tags = Tag::fromArgs( [ 'tag_a', 'tag_b', 'tag_c' ] );

		$this->assertSame( 0, $tags[ 0 ]->priority );
		$this->assertSame( 1, $tags[ 1 ]->priority );
		$this->assertSame( 2, $tags[ 2 ]->priority );
	}

	public function testPriorityIgnoresSection() {
		$tags = Tag::fromArgs( [
			'page_level_a',
			'page_level_b',
			'detail_a=#Details',
			'detail_b=#Details',
			'other=#Other Section',
			'page_level_c',
			'detail_c=#Details',
		] );

		$this->assertSame( [ 0, 1, 2, 3, 4, 5, 6 ], array_map( static fn ( Tag $tag ) => $tag->priority, $tags ) );
	}

	public function testThrowsOnDuplicateTagName() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', 'other_tag', 'shared_tag' ] );
	}

	public function testThrowsOnDuplicateAcrossSections() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', 'shared_tag=#Some Section' ] );
	}

	public function testDuplicateExceptionCarriesName() {
		try {
			Tag::fromArgs( [ 'shared_tag', 'shared_tag' ] );
			$this->fail( 'Expected DuplicateTagException to be thrown' );
		} catch ( DuplicateTagException $e ) {
			$this->assertSame( 'shared_tag', $e->tagName );
		}
	}
}
