<?php

namespace MediaWiki\Extension\Hermes\Tests\Tag;

use MediaWiki\Extension\Hermes\Exceptions\DuplicateTagException;
use MediaWiki\Extension\Hermes\Exceptions\EmptySectionException;
use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag::fromArgs
 */
class FromArgsTest extends MediaWikiUnitTestCase {

	public function testMapsAllArguments() {
		$tags = Tag::fromArgs( [ 'tag_a', '#section=tag_b' ] );

		$this->assertCount( 2, $tags );
		$this->assertSame( 'tag_a', $tags[ 0 ]->name );
		$this->assertNull( $tags[ 0 ]->section );
		$this->assertSame( 'tag_b', $tags[ 1 ]->name );
		$this->assertSame( 'section', $tags[ 1 ]->section );
	}

	public function testSectionAppliesToFollowingTags() {
		$tags = Tag::fromArgs( [ 'page_tag', ' #Some Section = tag_a ', 'tag_b', '#Other=tag_c', 'tag_d' ] );

		$this->assertSame(
			[ null, 'Some_Section', 'Some_Section', 'Other', 'Other' ],
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

	public function testRejectsEmptySection() {
		$this->expectException( EmptySectionException::class );
		Tag::fromArgs( [ 'tag_a', ' #  = tag_b' ] );
	}

	public function testRejectsSectionWithoutTag() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ '#section=' ] );
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
			'page_level_b',
			'#Details=detail_a',
			'detail_b',
			'#Other Section=other',
			'#Details=detail_c',
		] );

		$this->assertSame( [ 0, 1, 0, 1, 0, 2 ], array_map( static fn ( Tag $tag ) => $tag->order, $tags ) );
	}

	public function testThrowsOnDuplicateTagName() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', 'other_tag', 'shared_tag' ] );
	}

	public function testThrowsOnDuplicateAcrossSections() {
		$this->expectException( DuplicateTagException::class );
		Tag::fromArgs( [ 'shared_tag', '#Some Section=shared_tag' ] );
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
