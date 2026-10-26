<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Extension\Hermes\ProjectPrefix;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\WantedQueryPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Missing "!xx:" pages of this wiki's translation projects that are linked to, which Special:WantedPages and
 * Special:WantedCategories leave out (see WantedPagesHooks). Links to those whose base page exists go to
 * Special:HermesTranslate (see LinkHooks). `?language=xx` or the sub-page `/xx` shows only project "xx".
 */
abstract class WantedTranslationsPage extends WantedQueryPage {

	/** The selected project, or null for all; never set when updating the query cache. */
	private ?string $language = null;

	/** @inheritDoc */
	public function execute( $par ) {
		$language = $this->getRequest()->getVal( 'language' ) ?? $par ?? '';
		if ( isset( ProjectStore::getActiveLocalTranslationProjects()[ $language ] ) ) {
			$this->language = $language;
		}
		parent::execute( $par );
	}

	/**
	 * Condition for link targets in the selected translation project, or any of this wiki's active ones.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $title Title field of the link target
	 * @return IExpression|string Matching nothing if this wiki has no active translation projects
	 */
	protected function inProject( IReadableDatabase $dbr, string $title ): IExpression|string {
		$languages = $this->language !== null
			? [ $this->language ]
			: array_keys( ProjectStore::getActiveLocalTranslationProjects() );
		return $languages ? ProjectPrefix::projectSqlCondition( $dbr, $title, $languages ) : '0 = 1';
	}

	/**
	 * Like QueryPage's, but filtered by inProject(), which the cache knows nothing about.
	 *
	 * @inheritDoc
	 */
	public function fetchFromCache( $limit, $offset = false ) {
		$dbr = $this->getDatabaseProvider()->getReplicaDatabase();
		$query = $dbr->newSelectQueryBuilder()
			->select( [ 'qc_type', 'namespace' => 'qc_namespace', 'title' => 'qc_title', 'value' => 'qc_value' ] )
			->from( 'querycache' )
			->where( [ 'qc_type' => $this->getName(), $this->inProject( $dbr, 'qc_title' ) ] )
			->orderBy( $this->getCacheOrderFields(), $this->sortDescending() ? SelectQueryBuilder::SORT_DESC : null )
			->caller( __METHOD__ );
		if ( $limit !== false ) {
			$query->limit( intval( $limit ) );
		}
		if ( $offset !== false ) {
			$query->offset( intval( $offset ) );
		}
		return $query->fetchResultSet();
	}

	/** @inheritDoc */
	protected function getPageHeader() {
		$languages = array_keys( ProjectStore::getActiveLocalTranslationProjects() );
		if ( !$languages ) {
			return '';
		}
		$options = [ $this->msg( 'hermeswanted-all' )->text() => '' ];
		foreach ( $languages as $language ) {
			$autonym = Hermes::getAutonym( $language );
			$options[ $autonym !== '' ? "$autonym ($language)" : $language ] = $language;
		}
		return HTMLForm::factory( 'ooui', [
			'language' => [
				'type' => 'select',
				'name' => 'language',
				'label-message' => 'hermeswanted-project',
				'options' => $options,
				'default' => $this->language ?? '',
			],
		], $this->getContext() )
			->setMethod( 'get' )
			->setTitle( $this->getPageTitle() )
			->setWrapperLegend( false )
			->setSubmitTextMsg( 'hermestags-submit' )
			->prepareForm()
			->getHTML( false );
	}

	/** @inheritDoc */
	protected function linkParameters() {
		return $this->language !== null ? [ 'language' => $this->language ] : [];
	}

	/** Whether results show their namespace. */
	protected function showNamespace(): bool {
		return true;
	}

	/**
	 * Like WantedQueryPage's, with the title as readers see it, after its project's language (unless only
	 * one project is shown).
	 *
	 * @inheritDoc
	 */
	public function formatResult( $skin, $result ) {
		$title = Title::makeTitleSafe( $result->namespace, $result->title );
		$page = $title !== null ? LocalProjectTitle::fromTitle( $title ) : null;
		if ( $page === null || $page->project->isBase ) {
			return parent::formatResult( $skin, $result );
		}

		$language = $page->project->code;
		$link = $this->getLinkRenderer()->makeLink( $title, $page->getDisplayTitle( $this->showNamespace() ) );
		if ( ( $this->isCached() || $this->forceExistenceCheck() ) && $this->existenceCheck( $title ) ) {
			$link = "<del>$link</del>";
		}
		$html = $this->getLanguage()->specialList( $link, $this->makeWlhLink( $title, $result ) );
		if ( $this->language !== null ) {
			return $html;
		}
		$autonym = Hermes::getAutonym( $language );
		return htmlspecialchars( $autonym !== '' ? "$autonym ($language)" : $language )
			. $this->msg( 'colon-separator' )->escaped() . $html;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'hermes';
	}
}
