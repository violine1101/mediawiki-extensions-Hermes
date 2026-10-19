<?php

namespace MediaWiki\Extension\Hermes\Exceptions;

use InvalidArgumentException;

class EmptySectionException extends InvalidArgumentException {

	/** The "#=tag" argument, as entered */
	public string $arg;

	public function __construct( string $arg ) {
		parent::__construct( "Argument \"{$arg}\" has an empty section name." );
		$this->arg = $arg;
	}
}
