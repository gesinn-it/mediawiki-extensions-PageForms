<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

use MWException;

/**
 * An error in a form definition, such as a malformed tag.
 *
 * The message is the HTML that is shown to the user, so the exception is displayed like any
 * other MWException; getErrorText() and getDetail() give the parts it is built from.
 */
class FormDefinitionException extends MWException {

	private string $errorText;

	private ?string $detail;

	/**
	 * @param string $errorText What is wrong, as plain text without HTML
	 * @param string|null $detail The part of the form definition that is concerned; shown escaped
	 */
	public function __construct( string $errorText, ?string $detail = null ) {
		$this->errorText = $errorText;
		$this->detail = $detail;
		parent::__construct( $this->getErrorHtml() );
	}

	public function getErrorText(): string {
		return $this->errorText;
	}

	public function getDetail(): ?string {
		return $this->detail;
	}

	/**
	 * @return string The error as HTML, with the detail below it in a pre element
	 */
	public function getErrorHtml(): string {
		$html = '<div class="error">' . $this->errorText . '</div>';
		if ( $this->detail !== null ) {
			$html .= "\n<pre>" . htmlspecialchars( $this->detail ) . '</pre>';
		}
		return $html;
	}
}
