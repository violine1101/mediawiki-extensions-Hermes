<?php

namespace MediaWiki\Extension\Hermes\Exceptions;

use InvalidArgumentException;

class DuplicateTagException extends InvalidArgumentException {

	public string $tagName;

	public function __construct( string $tagName ) {
		parent::__construct( "Tag \"{$tagName}\" was used more than once." );
		$this->tagName = $tagName;
	}
}
