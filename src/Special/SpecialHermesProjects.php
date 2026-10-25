<?php

namespace MediaWiki\Extension\Hermes\Special;

use MediaWiki\Extension\Hermes\Exceptions\LanguageConflictException;
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
	 * and for wikis the user can manage the form adding one, unless there's nothing left to add. Global admins get
	 * a button (de)activating each.
	 */
	private function projectsTable(): string {
		$formatter = $this->getFormatter();
		$counts = TagQuery::countPages( Hermes::getDB() );
		$byWiki = [];
		foreach ( ProjectStore::getProjects() as $code => $ofLanguage ) {
			foreach ( $ofLanguage as $wiki => $project ) {
				$byWiki[ $wiki ][ $project->isBase ? '' : $code ] = $project;
			}
		}
		ksort( $byWiki );
		$hasActions = false;
		foreach ( $byWiki as $projects ) {
			foreach ( $projects as $project ) {
				$hasActions = $hasActions || $this->canActivate( $project );
			}
		}

		$headerMsgs = [
			'hermesprojects-header-wiki', 'hermesprojects-header-language', 'hermesprojects-header-pages',
			'hermesprojects-header-active',
		];
		if ( $hasActions ) {
			$headerMsgs[] = 'hermesprojects-header-actions';
		}
		$headers = array_map(
			fn ( string $msg ) => Html::element( 'th', [], $this->msg( $msg )->text() ), $headerMsgs
		);
		$headers[ 3 ] = Html::rawElement( 'th', [],
			Html::element( 'abbr', [ 'title' => $this->msg( 'hermesprojects-header-active-tooltip' )->text() ],
				$this->msg( 'hermesprojects-header-active' )->text()
			)
		);
		$rows = [];
		foreach ( $byWiki as $wiki => $projects ) {
			ksort( $projects );
			$addable = $this->canManage( $wiki ) ? $this->getAddableLanguages( $wiki ) : [];
			$addRow = (bool)$addable;
			$wikiCell = Html::rawElement( 'th',
				[ 'scope' => 'rowgroup', 'rowspan' => count( $projects ) + (int)$addRow ],
				$this->wikiLink( $wiki )
			);
			foreach ( $projects as $key => $project ) {
				$code = $project->code;
				$count = $this->getLanguage()->formatNum( $counts[ $wiki ][ $code ] ?? 0 );
				$classes = [
					'ext-hermes-base-project' => $key === '',
					'ext-hermes-active-project' => $project->isActive,
				];
				$rows[] = Html::rawElement( 'tr', [ 'class' => $classes ],
					$wikiCell .
					Html::element( 'td', [], $formatter->languageLabel( $code ) ) .
					Html::rawElement( 'td',
						[ 'class' => 'ext-hermes-project-pages' ],
						$this->getLinkRenderer()->makeKnownLink( SpecialPage::getTitleFor( 'HermesPages',
							$project->toId()
						), $count )
					) .
					Html::element( 'td', [ 'class' => 'ext-hermes-project-active' ],
						$this->msg( $project->isActive ? 'hermesprojects-active-yes' : 'hermesprojects-active-no' )
							->text()
					) .
					( $hasActions
						? Html::rawElement( 'td', [ 'class' => 'ext-hermes-project-actions' ],
							$this->canActivate( $project ) ? $this->activeButton( $project ) : ''
						)
						: '' )
				);
				$wikiCell = '';
			}
			if ( $addRow ) {
				$rows[] = Html::rawElement( 'tr', [],
					Html::rawElement( 'td',
						[ 'colspan' => 3 + (int)$hasActions ], $this->addForm( $wiki, $addable )
					)
				);
			}
		}
		return $formatter->table( $headers, $rows, false, [ 'ext-hermes-projects' ] );
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
			static fn ( string $code ) => !isset( ProjectStore::getProjects()[ $code ][ $wiki ] ),
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

		$registered = in_array( $wiki, ProjectStore::getWikiIds(), true );
		if ( $add !== '' && $registered && isset( $this->getAddableLanguages( $wiki )[ $add ] ) ) {
			try {
				if ( ProjectStore::addTranslationProject( $wiki, $add ) ) {
					$this->log( 'add-project', $add, $wiki );
				}
			} catch ( LanguageConflictException ) {
				// Impossible, because getAddableLanguages excludes the wiki's base language
			}
		}
		$toActivate = ProjectStore::getProjects()[ $activate ][ $wiki ] ?? null;
		if ( $toActivate !== null && $this->canActivate( $toActivate ) ) {
			$previous = ProjectStore::getWikiForLanguage( $activate );
			if ( ProjectStore::activate( $activate, $wiki ) ) {
				if ( $previous !== null ) {
					$this->log( 'deactivate', $activate, $previous );
				}
				$this->log( 'activate', $activate, $wiki );
			}
		}
		$toDeactivate = ProjectStore::getProjects()[ $deactivate ][ $wiki ] ?? null;
		if ( $toDeactivate !== null && $toDeactivate->isActive && $this->canActivate( $toDeactivate ) ) {
			ProjectStore::deactivate( $deactivate );
			$this->log( 'deactivate', $deactivate, $wiki );
		}
	}

	private function log( string $action, string $language, string $wiki ): void {
		$entry = new ManualLogEntry( 'hermes', $action );
		$entry->setPerformer( $this->getUser() );
		$entry->setTarget( $this->getPageTitle() );
		$entry->setParameters( [ '4::language' => $language, '5::wiki' => $wiki ] );
		$entry->publish( $entry->insert() );
	}
}
