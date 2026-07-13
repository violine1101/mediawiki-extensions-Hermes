<?php

namespace MediaWiki\Extension\Hermes\Exceptions;

use InvalidArgumentException;

class LanguageConflictException extends InvalidArgumentException {

	public string $language;
	public string $existingWiki;
	public string $requestedWiki;

	public function __construct( string $language, string $existingWiki, string $requestedWiki ) {
		parent::__construct(
			"Language \"{$language}\" is already registered to wiki \"{$existingWiki}\", " .
				"can't register it to wiki \"{$requestedWiki}\"."
		);
		$this->language = $language;
		$this->existingWiki = $existingWiki;
		$this->requestedWiki = $requestedWiki;
	}
}
