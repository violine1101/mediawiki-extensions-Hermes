<?php

namespace MediaWiki\Extension\Hermes\Tests\Tag;

use MediaWiki\Extension\Hermes\Exceptions\InvalidTagNameException;
use MediaWiki\Extension\Hermes\Tag;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Hermes\Tag::fromArg
 */
class FromArgTest extends MediaWikiUnitTestCase {

	public function testWithoutSection() {
		$tag = Tag::fromArg( 'Some_Tag' );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertNull( $tag->section );
	}

	public function testWithSection() {
		$tag = Tag::fromArg( 'some_tag#Some_Section' );

		$this->assertSame( 'some_tag', $tag->name );
		$this->assertSame( 'Some_Section', $tag->section );
	}

	public function testNormalizesCaseAndWhitespace() {
		$tag = Tag::fromArg( '  Some Tag  ' );

		$this->assertSame( 'some_tag', $tag->name );
	}

	public function testCollapsesWhitespaceAndUnderscores() {
		$tag = Tag::fromArg( 'foo   bar___baz' );

		$this->assertSame( 'foo_bar_baz', $tag->name );
	}

	public function testPreservesUnderscoreSeparator() {
		$tag = Tag::fromArg( 'a/b.c:d_e' );

		$this->assertSame( 'a/b.c:d_e', $tag->name );
	}

	public function testRejectsInvalidCharacters() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArg( 'invalid tag!' );
	}

	public function testRejectsEmptyName() {
		$this->expectException( InvalidTagNameException::class );
		Tag::fromArg( '  ' );
	}

	public function testExceptionCarriesRawName() {
		try {
			Tag::fromArg( 'bad!tag' );
			$this->fail( 'Expected InvalidTagNameException to be thrown' );
		} catch ( InvalidTagNameException $e ) {
			$this->assertSame( 'bad!tag', $e->tagName );
		}
	}

	public function testCollapsesWhitespaceInSection() {
		$tag = Tag::fromArg( 'tag#Some   Section___Name' );

		$this->assertSame( 'Some_Section_Name', $tag->section );
	}

	public function testSectionPreservesCase() {
		$tag = Tag::fromArg( 'tag#SOME SECTION' );

		$this->assertSame( 'SOME_SECTION', $tag->section );
	}

	public function testWhitespaceOnlySection() {
		$tag = Tag::fromArg( 'tag#   ' );

		$this->assertNull( $tag->section );
	}
}
