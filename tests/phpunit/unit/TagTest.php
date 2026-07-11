<?php

namespace MediaWiki\Extension\Hermes\Tests;

use MediaWiki\Extension\Hermes\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag
 */
class TagTest extends MediaWikiUnitTestCase {

	public function testFromArgParsesNameWithoutSection() {
		$tag = Tag::fromArg( 'Some_Tag' );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertNull( $tag->section );
	}

	public function testFromArgParsesNameWithSection() {
		$tag = Tag::fromArg( 'some_tag#Some_Section' );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertSame( 'Some_Section', $tag->section );
	}

	public function testFromArgNormalizesCaseAndWhitespace() {
		$tag = Tag::fromArg( '  Some Tag  ' );

		$this->assertSame( 'some_tag', $tag->name );
	}

	public function testFromArgAllowsSpecialCharacters() {
		$tag = Tag::fromArg( 'a/b.c:d_e' );

		$this->assertSame( 'a/b.c:d_e', $tag->name );
	}

	public function testFromArgRejectsInvalidCharacters() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArg( 'invalid tag!' );
	}

	public function testFromArgRejectsEmptyName() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArg( '  ' );
	}

	public function testInvalidTagNameExceptionCarriesRawName() {
		try {
			Tag::fromArg( 'bad!tag' );
			$this->fail( 'Expected InvalidTagNameException to be thrown' );
		} catch ( InvalidTagNameException $e ) {
			$this->assertSame( 'bad!tag', $e->tagName );
		}
	}

	public function testFromArgsMapsAllArguments() {
		$tags = Tag::fromArgs( [ 'tag_a', 'tag_b#section' ] );

		$this->assertCount( 2, $tags );
		$this->assertSame( 'tag_a', $tags[ 0 ]->name );
		$this->assertSame( 'tag_b', $tags[ 1 ]->name );
		$this->assertSame( 'section', $tags[ 1 ]->section );
	}

	public function testFromArgsThrowsOnFirstInvalidArgument() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArgs( [ 'valid_tag', 'invalid tag!' ] );
	}

	public function testFromJsonRoundTripsFromArg() {
		$original = Tag::fromArg( 'some_tag#Some_Section' );
		$decoded = json_decode( json_encode( $original ) );

		$tag = Tag::fromJson( $decoded );

		$this->assertSame( $original->name, $tag->name );
		$this->assertSame( $original->section, $tag->section );
	}

	public function testFromJsonHandlesMissingSection() {
		$tag = Tag::fromJson( (object)[ 'name' => 'some_tag' ] );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertNull( $tag->section );
	}
}
