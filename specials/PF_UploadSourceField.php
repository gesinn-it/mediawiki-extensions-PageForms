<?php
/**
 * PFUploadWindow - used for uploading files from within a form.
 * This class is nearly identical to MediaWiki's SpecialUpload class, with
 * a few changes to remove skin CSS and HTML, and to populate the relevant
 * field in the form with the name of the uploaded form.
 *
 * @author Yaron Koren
 * @ingroup PF
 */

/**
 * A form field that contains a radio box in the label.
 */

/**
 * @ingroup PFSpecialPages
 */
class PFUploadSourceField extends HTMLTextField {

	public function getLabelHtml( $cellAttributes = [] ) {
		$id = "wpSourceType{$this->mParams['upload-type']}";
		$label = Html::rawElement( 'label', [ 'for' => $id ], $this->mLabel );

		if ( !empty( $this->mParams['radio'] ) ) {
			$attribs = [
				'name' => 'wpSourceType',
				'type' => 'radio',
				'id' => $id,
				'value' => $this->mParams['upload-type'],
			];

			if ( !empty( $this->mParams['checked'] ) ) {
				$attribs['checked'] = 'checked';
			}
			$label .= Html::element( 'input', $attribs );
		}

		return Html::rawElement( 'td', [ 'class' => 'mw-label' ], $label );
	}

	public function getSize() {
		return $this->mParams['size'] ?? 60;
	}

}
