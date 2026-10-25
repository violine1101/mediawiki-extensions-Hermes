<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Pager\TablePager;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * A Hermes special page: a filterable list of the whole wiki family, with a details view of a single
 * entry as its sub-page.
 */
abstract class HermesSpecialPage extends SpecialPage {

	private ?TagFormatter $formatter = null;

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
		$this->formatter ??= new TagFormatter( $this->getContext(), $this->getLinkRenderer() );
		return $this->formatter;
	}

	/**
	 * The languages with projects selected in the request's language filter.
	 *
	 * @param string[] $default If the request doesn't have the filter
	 * @return string[]
	 */
	protected function getLanguageFilter( array $default = [] ): array {
		$languages = $this->getRequest()->getArray( 'language' ) ?? $default;
		return array_values( array_intersect( $languages, array_keys( ProjectStore::getProjects() ) ) );
	}

	/**
	 * The wikis with projects selected in the request's wiki filter.
	 *
	 * @param string[] $default If the request doesn't have the filter
	 * @return string[]
	 */
	protected function getWikiFilter( array $default = [] ): array {
		$wikis = $this->getRequest()->getArray( 'wiki' ) ?? $default;
		return array_values( array_intersect( $wikis, ProjectStore::getWikiIds() ) );
	}

	/**
	 * Whether the request asks for inactive projects too (`inactive=1`).
	 *
	 * @param bool $default If the form wasn't submitted
	 * @param string $formField A field the form always submits, telling an unchecked box from no form
	 */
	protected function getInactiveFilter( bool $default, string $formField ): bool {
		$request = $this->getRequest();
		return $request->getCheck( $formField ) ? $request->getBool( 'inactive' ) : $default;
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

	/**
	 * @param string[] $selected
	 * @return array HTMLForm field for the wiki filter
	 */
	protected function getWikiField( array $selected ): array {
		return [
			'type' => 'multiselect',
			'dropdown' => true,
			'name' => 'wiki',
			'label-message' => 'hermestags-wiki',
			'options' => $this->getFormatter()->wikiOptions(),
			'default' => $selected,
		];
	}

	/** @return array HTMLForm field for the "show inactive projects" filter (`inactive=1`) */
	protected function getInactiveField( bool $checked ): array {
		return [
			'type' => 'check',
			'name' => 'inactive',
			'label-message' => 'hermestags-inactive',
			'default' => $checked,
		];
	}

	/** @return array HTMLForm field for the "only conflicts" filter (`conflicts=1`) */
	protected function getConflictsField( bool $checked ): array {
		return [
			'type' => 'check',
			'name' => 'conflicts',
			'label-message' => 'hermestags-conflicts-only',
			'default' => $checked,
		];
	}

	/**
	 * @param array $fields
	 * @param string|null $legendMsg Null for no frame around the form
	 * @param string $submitMsg
	 */
	protected function showForm( array $fields, ?string $legendMsg, string $submitMsg = 'hermestags-submit' ): void {
		$form = HTMLForm::factory( 'ooui', $fields, $this->getContext() )
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() )
			->setSubmitTextMsg( $submitMsg );
		if ( $legendMsg !== null ) {
			$form->setWrapperLegendMsg( $legendMsg );
		} else {
			$form->setWrapperLegend( false );
		}
		$form->prepareForm()->displayForm( false );
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
