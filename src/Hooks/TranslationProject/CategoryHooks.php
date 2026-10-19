<?php

namespace MediaWiki\Extension\Hermes\Hooks\TranslationProject;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Hermes\ProjectTitle;
use MediaWiki\Hook\CategoryViewer__generateLinkHook;
use MediaWiki\Hook\GetDefaultSortkeyHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\OutputPageRenderCategoryLinkHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\ProperPageIdentity;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * Hides the "!xx:" prefix in category links, listings and sort keys, and marks category members
 * whose language differs from the category's.
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
		$category = ProjectTitle::newFromLinkTarget( $categoryTitle );
		if ( $category->project === null ) {
			return;
		}

		$link = MediaWikiServices::getInstance()->getLinkRenderer()
			->makeLink( $categoryTitle, $category->getDisplayTitle() );
	}

	/** @inheritDoc */
	public function onGetDefaultSortkey( $title, &$sortkey ) {
		$page = ProjectTitle::newFromLinkTarget( $title );
		if ( $page->project === null ) {
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

		$this->viewedCategoryLanguage ??= ProjectTitle::newFromLinkTarget( $categoryTitle )->getLanguageCode();
		$page = ProjectTitle::newFromLinkTarget( $title );
		$languageCode = $page->getLanguageCode();

		$suffix = '';
		if ( $languageCode !== $this->viewedCategoryLanguage ) {
			$suffix = Html::rawElement( 'sup', [ 'class' => 'hermes-category-language' ], "({$languageCode})" );
		}

		if ( $page->project === null && $suffix === '' ) {
			return;
		}

		if ( $page->project !== null ) {
			// Like core: subcategories are shown without namespace, other members with it.
			$text = $page->getDisplayTitle( $type !== 'subcat' );
		} else {
			$text = $html !== null ? new HtmlArmor( $html ) : null;
		}

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $title, $text ) . $suffix;
	}
}
