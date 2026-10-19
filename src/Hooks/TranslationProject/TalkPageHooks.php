<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Extension\Hermes\PageInfo;
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
		$page = PageInfo::fromLocalPage( $wikiPage->getTitle() );
		if ( $page->namespace !== NS_USER_TALK || $page->translationProject === null || $editResult->isNullEdit() ) {
			return;
		}

		// Mirrors ChangeTrackingEventIngress::updateNewTalkAfterPageUpdated().
		$services = MediaWikiServices::getInstance();
		$userNameUtils = $services->getUserNameUtils();
		$recipientName = $userNameUtils->isIP( $page->title )
			? $page->title
			: $userNameUtils->getCanonical( $page->title );
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

		$page = PageInfo::fromLocalPage( $title );
		if ( $page->translationProject === null || $page->title !== strtr( $user->getName(), ' ', '_' ) ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$oldId = $out->getRequest()->getInt( 'oldid' );
		$oldRev = $oldId ? $services->getRevisionLookup()->getRevisionById( $oldId ) : null;
		$services->getTalkPageNotificationManager()->clearForPageView( $user, $oldRev );
	}
}
