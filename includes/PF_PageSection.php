<?php

use MediaWiki\Extension\PageForms\FormDefinitionWriter;

/**
 * Represents a page section in a user-defined form.
 * This class should really be called "PFPageSectionInForm", to differentiate
 * it from the PFWikiPageSection class.
 *
 * @author Himeshi
 * @ingroup PF
 */
class PFPageSection {
	private $mSectionName;
	private $mSectionLevel = 2;
	private $mIsMandatory = false;
	private $mIsHidden = false;
	private $mIsRestricted = false;
	private $mHideIfEmpty = false;
	private $mSectionArgs = [];

	public static function create( $section_name ) {
		$ps = new PFPageSection();
		$ps->mSectionName = $section_name;

		return $ps;
	}

	public static function newFromFormTag( $tag_components, User $user ) {
		$ps = new PFPageSection();
		$ps->mSectionName = trim( $tag_components[1] );

		// cycle through the other components
		for ( $i = 2; $i < count( $tag_components ); $i++ ) {
			$component = trim( $tag_components[$i] );

			if ( $component === 'mandatory' ) {
				$ps->mIsMandatory = true;
			} elseif ( $component === 'hidden' ) {
				$ps->mIsHidden = true;
			} elseif ( $component === 'restricted' ) {
				$ps->mIsRestricted = ( !$user || !$user->isAllowed( 'editrestrictedfields' ) );
			} elseif ( $component === 'autogrow' ) {
				$ps->mSectionArgs['autogrow'] = true;
			} elseif ( $component === 'hide if empty' ) {
				$ps->mHideIfEmpty = true;
			}

			$sub_components = array_map( 'trim', explode( '=', $component, 2 ) );

			if ( count( $sub_components ) === 2 ) {
				switch ( $sub_components[0] ) {
					case 'level':
						$ps->mSectionLevel = $sub_components[1];
						break;
					case 'rows':
					case 'cols':
					case 'class':
					case 'editor':
					case 'placeholder':
						$ps->mSectionArgs[$sub_components[0]] = $sub_components[1];
						break;
					default:
						// Ignore unknown
				}
			}
		}
		return $ps;
	}

	public function getSectionName() {
		return $this->mSectionName;
	}

	public function getSectionLevel() {
		return $this->mSectionLevel;
	}

	public function setSectionLevel( $section_level ) {
		$this->mSectionLevel = $section_level;
	}

	public function setIsMandatory( $isMandatory ) {
		$this->mIsMandatory = $isMandatory;
	}

	public function isMandatory() {
		return $this->mIsMandatory;
	}

	public function setIsHidden( $isHidden ) {
		$this->mIsHidden = $isHidden;
	}

	public function isHidden() {
		return $this->mIsHidden;
	}

	public function setIsRestricted( $isRestricted ) {
		$this->mIsRestricted = $isRestricted;
	}

	public function isRestricted() {
		return $this->mIsRestricted;
	}

	public function isHideIfEmpty() {
		return $this->mHideIfEmpty;
	}

	public function setSectionArgs( $key, $value ) {
		$this->mSectionArgs[$key] = $value;
	}

	public function getSectionArgs() {
		return $this->mSectionArgs;
	}

	/**
	 * @deprecated use FormDefinitionWriter::section()
	 * @return string
	 */
	public function createMarkup() {
		return ( new FormDefinitionWriter() )->section( $this );
	}

	public static function getParameters() {
		$params = [];

		$params['mandatory'] = [
			'name' => 'mandatory',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_mandatory' )->text()
		];
		$params['restricted'] = [
			'name' => 'restricted',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_restricted' )->text()
		];
		$params['hidden'] = [
			'name' => 'hidden',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_createform_hiddensection' )->text()
		];
		$params['class'] = [
			'name' => 'class',
			'type' => 'string',
			'description' => wfMessage( 'pf_forminputs_class' )->text()
		];
		$params['rows'] = [
			'name' => 'rows',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_rows' )->text()
		];
		$params['cols'] = [
			'name' => 'cols',
			'type' => 'int',
			'description' => wfMessage( 'pf_forminputs_cols' )->text()
		];
		$params['autogrow'] = [
			'name' => 'autogrow',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_autogrow' )->text()
		];

		return $params;
	}
}
