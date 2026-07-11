<?php

namespace MediaWiki\Extension\Hermes;

use InvalidArgumentException;

class InvalidTagNameException extends InvalidArgumentException {

	public string $tagName;

	public function __construct( string $tagName ) {
		parent::__construct( "Tag name \"{$tagName}\" is not valid." );
		$this->tagName = $tagName;
	}
}
