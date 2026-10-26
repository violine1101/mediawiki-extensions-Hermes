<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Hermes;
use MediaWiki\Extension\Hermes\Project;
use MediaWiki\Extension\Hermes\ProjectStore;
use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\WikiMap\WikiMap;
use OOUI\ActionFieldLayout;
use OOUI\ButtonInputWidget;
use OOUI\DropdownInputWidget;

/**
 * Special:HermesProjects: the wikis of the family with their projects (highlighting active ones), and how many
 * pages with Hermes tags each project has. Users with the hermes-local-admin right can add translation
 * projects to this wiki, and activate or deactivate those whose language no other wiki has; those with
 * hermes-global-admin can add them to any wiki, and activate or deactivate any project. Changes are
 * logged in the "hermes" log.
 */
class SpecialHermesProjects extends HermesSpecialPage {

	private const LOCAL_RIGHT = 'hermes-local-admin';
	private const GLOBAL_RIGHT = 'hermes-global-admin';

	public function __construct( private readonly LanguageNameUtils $languageNameUtils ) {
		parent::__construct( 'HermesProjects' );
	}

	/** @inheritDoc */
	protected function showDetails( string $par ): bool {
		return false;
	}

	/** @inheritDoc */
	protected function showList( string $par ): void {
		if ( $this->canManage( WikiMap::getCurrentWikiId() ) ) {
			$this->getOutput()->enableOOUI();
			$this->handlePost();
		}
		$this->getOutput()->addHTML( $this->projectsTable() );
	}

	/** Whether the user may manage $wiki's projects: their own wiki as a local admin, any as a global one. */
	private function canManage( string $wiki ): bool {
		$authority = $this->getAuthority();
		return $authority->isAllowed( self::GLOBAL_RIGHT )
			|| ( $wiki === WikiMap::getCurrentWikiId() && $authority->isAllowed( self::LOCAL_RIGHT ) );
	}

	/**
	 * Whether the user may activate or deactivate $project: any as a global admin, or as a local admin a translation
	 * project of their own wiki whose language no other wiki has, as that doesn't affect which wiki others link to.
	 */
	private function canActivate( Project $project ): bool {
		$authority = $this->getAuthority();
		return $authority->isAllowed( self::GLOBAL_RIGHT ) || (
			$authority->isAllowed( self::LOCAL_RIGHT )
			&& $project->isLocalTranslationProject()
			&& count( ProjectStore::getProjects()[ $project->code ] ) === 1
		);
	}

	/**
	 * One row per project, grouped by wiki: its base project first (in bold), then its translation projects,
	 * and for wikis the user can manage the form adding one, unless there's nothing left to add. If the user can
	 * (de)activate any project, a last column has buttons for those.
	 */
	private function projectsTable(): string {
		$byWiki = [];
		foreach ( ProjectStore::getWikiIds() as $wiki ) {
			$projects = ProjectStore::getProjectsOfWiki( $wiki );
			ksort( $projects );
			uasort( $projects, static fn ( Project $a, Project $b ) => $b->isBase <=> $a->isBase );
			$byWiki[ $wiki ] = $projects;
		}
		ksort( $byWiki );
		$hasActions = false;
		foreach ( $byWiki as $projects ) {
			foreach ( $projects as $project ) {
				$hasActions = $hasActions || $this->canActivate( $project );
			}
		}

		$counts = TagQuery::countPages( Hermes::getDB() );
		$rows = [];
		foreach ( $byWiki as $wiki => $projects ) {
			$addable = $this->canManage( $wiki ) ? $this->getAddableLanguages( $wiki ) : [];
			$wikiCell = Html::rawElement( 'th',
				[ 'scope' => 'rowgroup', 'rowspan' => count( $projects ) + ( $addable ? 1 : 0 ) ],
				$this->wikiLink( $wiki )
			);
			foreach ( $projects as $code => $project ) {
				$rows[] = $this->projectRow( $project, $wikiCell, $counts[ $wiki ][ $code ] ?? 0, $hasActions );
				$wikiCell = '';
			}
			if ( $addable ) {
				$rows[] = Html::rawElement( 'tr', [],
					Html::rawElement( 'td',
						[ 'colspan' => 3 + (int)$hasActions ], $this->addForm( $wiki, $addable )
					)
				);
			}
		}
		return $this->getFormatter()->table( $this->headers( $hasActions ), $rows, false, [ 'ext-hermes-projects' ] );
	}

	/** @return string[] HTML of the header cells */
	private function headers( bool $hasActions ): array {
		$headers = [
			Html::element( 'th', [], $this->msg( 'hermesprojects-header-wiki' )->text() ),
			Html::element( 'th', [], $this->msg( 'hermesprojects-header-language' )->text() ),
			Html::element( 'th', [], $this->msg( 'hermesprojects-header-pages' )->text() ),
			Html::rawElement( 'th', [],
				Html::element( 'abbr', [ 'title' => $this->msg( 'hermesprojects-header-active-tooltip' )->text() ],
					$this->msg( 'hermesprojects-header-active' )->text()
				)
			),
		];
		if ( $hasActions ) {
			$headers[] = Html::element( 'th', [], $this->msg( 'hermesprojects-header-actions' )->text() );
		}
		return $headers;
	}

	/**
	 * @param Project $project
	 * @param string $wikiCell HTML of the wiki's cell, for its first row
	 * @param int $count Number of pages with tags
	 * @param bool $hasActions Whether the table has an actions column
	 * @return string HTML
	 */
	private function projectRow( Project $project, string $wikiCell, int $count, bool $hasActions ): string {
		$classes = [
			'ext-hermes-base-project' => $project->isBase,
			'ext-hermes-active-project' => $project->isActive,
		];
		$pagesLink = $this->getLinkRenderer()->makeKnownLink(
			SpecialPage::getTitleFor( 'HermesPages', $project->toId() ), $this->getLanguage()->formatNum( $count )
		);
		$actions = $hasActions
			? Html::rawElement( 'td', [ 'class' => 'ext-hermes-project-actions' ],
				$this->canActivate( $project ) ? $this->activeButton( $project ) : ''
			)
			: '';
		return Html::rawElement( 'tr', [ 'class' => $classes ],
			$wikiCell .
			Html::element( 'td', [], $this->getFormatter()->languageLabel( $project->code ) ) .
			Html::rawElement( 'td', [ 'class' => 'ext-hermes-project-pages' ], $pagesLink ) .
			Html::element( 'td', [ 'class' => 'ext-hermes-project-active' ],
				$this->msg( $project->isActive ? 'hermesprojects-active-yes' : 'hermesprojects-active-no' )->text()
			) .
			$actions
		);
	}

	/** A link to the wiki's main page, or just its ID if it has no registered URL. */
	private function wikiLink( string $wiki ): string {
		$url = ProjectStore::getUrlTemplate( $wiki );
		return $url !== null
			? Html::element( 'a', [ 'href' => str_replace( '$1', '', $url ), 'class' => 'extiw' ], $wiki )
			: htmlspecialchars( $wiki );
	}

	/**
	 * @return array<string, string> Languages MediaWiki supports that $wiki has no project in yet,
	 *   code => autonym
	 */
	private function getAddableLanguages( string $wiki ): array {
		$codes = array_keys( array_filter(
			$this->languageNameUtils->getLanguageNames( LanguageNameUtils::AUTONYMS, LanguageNameUtils::SUPPORTED ),
			static fn ( string $code ) => ProjectStore::getProject( $code, $wiki ) === null,
			ARRAY_FILTER_USE_KEY
		) );
		sort( $codes );
		return array_combine( $codes, array_map( Hermes::getAutonym( ... ), $codes ) );
	}

	/**
	 * @param string $wiki
	 * @param array<string, string> $addable As getAddableLanguages()
	 * @return string HTML
	 */
	private function addForm( string $wiki, array $addable ): string {
		$options = [ [ 'data' => '', 'label' => $this->msg( 'hermesprojects-add-placeholder' )->text() ] ];
		foreach ( $addable as $code => $name ) {
			$options[] = [ 'data' => $code, 'label' => "$name ($code)" ];
		}
		return $this->postForm( $wiki, [],
			new ActionFieldLayout(
				new DropdownInputWidget( [ 'name' => 'add-project', 'options' => $options ] ),
				new ButtonInputWidget( [
					'type' => 'submit',
					'label' => $this->msg( 'hermesprojects-add-submit' )->text(),
					'flags' => [ 'progressive' ],
				] ),
				[ 'label' => $this->msg( 'hermesprojects-add-placeholder' )->text(), 'invisibleLabel' => true ]
			)
		);
	}

	/** A button making $project inactive if it's active, active otherwise. */
	private function activeButton( Project $project ): string {
		$action = $project->isActive ? 'deactivate' : 'activate';
		return $this->postForm( $project->wiki, [],
			new ButtonInputWidget( [
				'type' => 'submit',
				'name' => "$action-project",
				'value' => $project->code,
				'label' => $this->msg( "hermesprojects-$action" )->text(),
				'flags' => [ 'progressive' ],
			] )
		);
	}

	/** A form posting $fields (HTML) to this page for $wiki, with a CSRF token. */
	private function postForm( string $wiki, array $attribs, string $fields ): string {
		return Html::rawElement( 'form',
			[ 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL() ] + $attribs,
			$fields .
			Html::hidden( 'wiki', $wiki ) .
			Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() )
		);
	}

	private function handlePost(): void {
		$request = $this->getRequest();
		$wiki = $request->getVal( 'wiki', WikiMap::getCurrentWikiId() );
		$add = $this->canManage( $wiki ) ? $request->getVal( 'add-project', '' ) : '';
		$activate = $request->getVal( 'activate-project', '' );
		$deactivate = $request->getVal( 'deactivate-project', '' );
		if ( !$request->wasPosted() || ( $add === '' && $activate === '' && $deactivate === '' ) ) {
			return;
		}

		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			$this->getOutput()->addHTML( Html::errorBox( $this->msg( 'sessionfailure' )->parse() ) );
			return;
		}
		$this->checkReadOnly();

		$known = in_array( $wiki, ProjectStore::getWikiIds(), true );
		if ( $add !== '' && $known && isset( $this->getAddableLanguages( $wiki )[ $add ] )
			&& ProjectStore::addTranslationProject( $add, $wiki )
		) {
			$this->log( 'add-project', ProjectStore::getProject( $add, $wiki ) );
		}
		$toActivate = ProjectStore::getProject( $activate, $wiki );
		if ( $toActivate !== null && $this->canActivate( $toActivate ) ) {
			$previous = ProjectStore::getActiveProject( $activate );
			if ( ProjectStore::activate( $activate, $wiki ) ) {
				if ( $previous !== null ) {
					$this->log( 'deactivate', $previous );
				}
				$this->log( 'activate', $toActivate );
			}
		}
		$toDeactivate = ProjectStore::getProject( $deactivate, $wiki );
		if ( $toDeactivate !== null && $toDeactivate->isActive && $this->canActivate( $toDeactivate ) ) {
			ProjectStore::deactivate( $deactivate );
			$this->log( 'deactivate', $toDeactivate );
		}
	}

	private function log( string $action, Project $project ): void {
		$entry = new ManualLogEntry( 'hermes', $action );
		$entry->setPerformer( $this->getUser() );
		$entry->setTarget( $this->getPageTitle() );
		$entry->setParameters( [ '4::language' => $project->code, '5::wiki' => $project->wiki ] );
		$entry->publish( $entry->insert() );
	}
}
