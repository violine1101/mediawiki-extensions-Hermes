<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\LanguageStore;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Pager\TablePager;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * A Hermes special page: a filterable list of the whole wiki family, with a details view of a single
 * entry as its sub-page.
 */
abstract class HermesSpecialPage extends SpecialPage {

	private ?TagFormatter $formatter = null;

	public function __construct(
		string $name,
		protected readonly LanguageNameUtils $languageNameUtils,
	) {
		parent::__construct( $name );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( [ 'ext.hermes.special.styles', 'mediawiki.interface.helpers.styles' ] );

		$par ??= '';
		if ( $par === '' || !$this->showDetails( $par ) ) {
			$this->showList( $par );
		}
	}

	/**
	 * Shows the details view for a sub-page.
	 *
	 * @param string $par The sub-page, not empty
	 * @return bool False to show the list instead
	 */
	abstract protected function showDetails( string $par ): bool;

	/**
	 * Shows the filter form and the list.
	 *
	 * @param string $par The sub-page, if showDetails() didn't handle it
	 */
	abstract protected function showList( string $par ): void;

	protected function getFormatter(): TagFormatter {
		$this->formatter ??= new TagFormatter(
			$this->getContext(), $this->getLinkRenderer(), $this->languageNameUtils
		);
		return $this->formatter;
	}

	/**
	 * The registered languages selected in the request's language filter.
	 *
	 * @param string[] $default If the request doesn't have the filter
	 * @return string[]
	 */
	protected function getLanguageFilter( array $default = [] ): array {
		$languages = $this->getRequest()->getArray( 'language' ) ?? $default;
		return array_values( array_intersect( $languages, array_keys( LanguageStore::getLanguages() ) ) );
	}

	/**
	 * @param string[] $selected
	 * @return array HTMLForm field for the language filter
	 */
	protected function getLanguageField( array $selected ): array {
		return [
			'type' => 'multiselect',
			'dropdown' => true,
			'name' => 'language',
			'label-message' => 'hermestags-language',
			'options' => $this->getFormatter()->languageOptions(),
			'default' => $selected,
		];
	}

	protected function showForm( array $fields, string $legendMsg ): void {
		HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() )
			->setWrapperLegendMsg( $legendMsg )
			->setSubmitTextMsg( 'hermestags-submit' )
			->prepareForm()
			->displayForm( false );
	}

	protected function showPager( TablePager $pager ): void {
		if ( !$pager->getNumRows() ) {
			$this->getOutput()->addWikiMsg( 'hermestags-empty' );
			return;
		}
		$this->getOutput()->addParserOutputContent(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'hermes';
	}
}
