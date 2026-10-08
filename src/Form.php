<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Represents a user-defined form.
 *
 * @author Yaron Koren
 * @ingroup PF
 */
class Form {
	private $mFormName;
	private $mPageNameFormula;
	private $mCreateTitle;
	private $mEditTitle;
	private $mAssociatedCategory;
	private $mItems;

	public static function create( $formName, $items ) {
		$form = new Form();
		$form->mFormName = ucfirst( str_replace( '_', ' ', $formName ?? '' ) );
		$form->mAssociatedCategory = null;
		$form->mItems = $items;
		return $form;
	}

	public function getFormName() {
		return $this->mFormName;
	}

	public function getItems() {
		return $this->mItems;
	}

	public function setPageNameFormula( $pageNameFormula ) {
		$this->mPageNameFormula = $pageNameFormula;
	}

	public function setCreateTitle( $createTitle ) {
		$this->mCreateTitle = $createTitle;
	}

	public function setEditTitle( $editTitle ) {
		$this->mEditTitle = $editTitle;
	}

	public function setAssociatedCategory( $associatedCategory ) {
		$this->mAssociatedCategory = $associatedCategory;
	}

	public function getPageNameFormula() {
		return $this->mPageNameFormula;
	}

	public function getCreateTitle() {
		return $this->mCreateTitle;
	}

	public function getEditTitle() {
		return $this->mEditTitle;
	}

	/**
	 * @return string|null
	 */
	public function getAssociatedCategory() {
		return $this->mAssociatedCategory;
	}

	/**
	 * @deprecated use FormDefinitionWriter::form()
	 * @param bool $includeFreeText
	 * @param string|null $freeTextLabel
	 * @return string
	 */
	public function createMarkup( $includeFreeText = true, $freeTextLabel = null ) {
		return ( new FormDefinitionWriter() )->form( $this, $includeFreeText, $freeTextLabel );
	}

}
