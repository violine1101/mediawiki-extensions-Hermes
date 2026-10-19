<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\User;

/**
 * Treats "User talk:!xx:Foo" as a talk page of user "Foo": edits to it set the user's "new
 * messages" notice, and the user viewing it clears the notice.
 */
class TalkPageHooks implements PageSaveCompleteHook, OutputPageBeforeHTMLHook {

	/** @inheritDoc */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		$page = ProjectTitle::newFromLinkTarget( $wikiPage->getTitle() );
		if ( $page->namespace !== NS_USER_TALK || $page->project === null || $editResult->isNullEdit() ) {
			return;
		}

		// Mirrors ChangeTrackingEventIngress::updateNewTalkAfterPageUpdated().
		$services = MediaWikiServices::getInstance();
		$userNameUtils = $services->getUserNameUtils();
		$recipientName = $userNameUtils->isIP( $page->baseTitle )
			? $page->baseTitle
			: $userNameUtils->getCanonical( $page->baseTitle );
		if (
			$recipientName === false
			|| $recipientName === $user->getName()
			|| ( $revisionRecord->isMinor() && $services->getPermissionManager()->userHasRight( $user, 'nominornewtalk' ) )
		) {
			return;
		}

		$recipient = User::newFromName( $recipientName, false );
		if (
			!$recipient
			|| !( $userNameUtils->isIP( $recipientName ) || $recipient->isRegistered() )
			|| !( new HookRunner( $services->getHookContainer() ) )->onArticleEditUpdateNewTalk( $wikiPage, $recipient )
		) {
			return;
		}

		$services->getTalkPageNotificationManager()->setUserHasNewMessages( $recipient, $revisionRecord );
	}

	/** @inheritDoc */
	public function onOutputPageBeforeHTML( $out, &$text ) {
		$title = $out->getTitle();
		$user = $out->getUser();
		if ( !$title || $title->getNamespace() !== NS_USER_TALK ) {
			return;
		}

		$page = ProjectTitle::newFromLinkTarget( $title );
		if ( $page->project === null || $page->baseTitle !== strtr( $user->getName(), ' ', '_' ) ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$oldId = $out->getRequest()->getInt( 'oldid' );
		$oldRev = $oldId ? $services->getRevisionLookup()->getRevisionById( $oldId ) : null;
		$services->getTalkPageNotificationManager()->clearForPageView( $user, $oldRev );
	}
}
