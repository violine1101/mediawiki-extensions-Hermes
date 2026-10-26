<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\LocalProjectTitle;
use MediaWiki\Hook\CategoryViewer__generateLinkHook;
use MediaWiki\Hook\GetDefaultSortkeyHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\OutputPageRenderCategoryLinkHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * Hides the "!xx:" prefix in category links, listings and sort keys, and marks categories and category members
 * whose language differs from the viewed page's.
 */
class CategoryHooks implements
	OutputPageRenderCategoryLinkHook,
	GetDefaultSortkeyHook,
	CategoryViewer__generateLinkHook
{

	private ?string $viewedCategoryLanguage = null;

	/** @inheritDoc */
	public function onOutputPageRenderCategoryLink(
		OutputPage $outputPage, ProperPageIdentity $categoryTitle, string $text, ?string &$link
	): void {
		$category = LocalProjectTitle::fromTitle( $categoryTitle );
		$pageTitle = $outputPage->getTitle();
		$suffix = $pageTitle !== null
			? self::languageSuffix( $category, LocalProjectTitle::fromTitle( $pageTitle )->project->code )
			: '';
		if ( $category->project->isBase && $suffix === '' ) {
			return;
		}

		$text = $category->project->isBase ? new HtmlArmor( $text ) : $category->getDisplayTitle();
		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $categoryTitle, $text ) . $suffix;
	}

	/** @inheritDoc */
	public function onGetDefaultSortkey( $title, &$sortkey ) {
		$page = LocalProjectTitle::fromTitle( $title );
		if ( $page->project->isBase ) {
			return;
		}

		$sortkey = $page->getDisplayTitle();
	}

	/** @inheritDoc */
	public function onCategoryViewer__generateLink( $type, $title, $html, &$link ) {
		$categoryTitle = RequestContext::getMain()->getTitle();
		if ( $categoryTitle?->getNamespace() !== NS_CATEGORY ) {
			return;
		}

		$this->viewedCategoryLanguage ??= LocalProjectTitle::fromTitle( $categoryTitle )->project->code;
		$page = LocalProjectTitle::fromTitle( $title );
		$suffix = self::languageSuffix( $page, $this->viewedCategoryLanguage );
		if ( $page->project->isBase && $suffix === '' ) {
			return;
		}

		if ( !$page->project->isBase ) {
			// Like core: subcategories are shown without namespace, other members with it.
			$text = $page->getDisplayTitle( $type !== 'subcat' );
		} else {
			$text = $html !== null ? new HtmlArmor( $html ) : null;
		}

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $title, $text ) . $suffix;
	}

	/** "<sup>(xx)</sup>" if $page's language isn't $language, else ''. */
	private static function languageSuffix( LocalProjectTitle $page, string $language ): string {
		$code = $page->project->code;
		return $code !== $language
			? Html::rawElement( 'sup', [ 'class' => 'hermes-category-language' ], "({$code})" )
			: '';
	}
}
