<?php

namespace MediaWiki\Extension\Hermes\Exceptions;

use InvalidArgumentException;

class InvalidSectionException extends InvalidArgumentException {

	/** The "tag=#section" argument, as entered */
	public string $arg;

	public function __construct( string $arg ) {
		parent::__construct( "Argument \"{$arg}\" does not refer to a valid section." );
		$this->arg = $arg;
	}
}
