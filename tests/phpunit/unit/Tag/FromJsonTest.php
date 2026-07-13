<?php

namespace MediaWiki\Extension\Hermes\Tests\Tag;

use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag::fromJson
 */
class FromJsonTest extends MediaWikiUnitTestCase {

	public function testRoundTripsFromArg() {
		$original = Tag::fromArg( 'some_tag#Some_Section' );
		$decoded = json_decode( json_encode( $original ) );

		$tag = Tag::fromJson( $decoded );

		$this->assertSame( $original->name, $tag->name );
		$this->assertSame( $original->section, $tag->section );
	}

	public function testHandlesMissingSection() {
		$tag = Tag::fromJson( (object)[ 'name' => 'some_tag' ] );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertNull( $tag->section );
	}

	public function testHandlesMissingOrder() {
		$tag = Tag::fromJson( (object)[ 'name' => 'some_tag' ] );

		$this->assertSame( 0, $tag->order );
	}

	public function testRoundTripsOrderFromArgs() {
		$originals = Tag::fromArgs( [ 'tag_a', 'tag_b' ] );
		$decoded = json_decode( json_encode( $originals ) );

		$tags = array_map( Tag::fromJson( ... ), $decoded );

		$this->assertSame( $originals[ 0 ]->order, $tags[ 0 ]->order );
		$this->assertSame( $originals[ 1 ]->order, $tags[ 1 ]->order );
	}
}
