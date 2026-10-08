<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\FormDefinition;

use MWException;

/**
 * An error in a form definition, such as a malformed tag.
 *
 * The message is plain text, for logs and the command line. Where the error is shown to the
 * user, getErrorHtml() gives the escaped HTML.
 */
class FormDefinitionException extends MWException {

	private string $errorText;

	private ?string $detail;

	/**
	 * @param string $errorText What is wrong, as plain text; shown escaped
	 * @param string|null $detail The part of the form definition that is concerned; shown escaped
	 */
	public function __construct( string $errorText, ?string $detail = null ) {
		$this->errorText = $errorText;
		$this->detail = $detail;
		parent::__construct( $errorText . ( $detail !== null ? "\n" . $detail : '' ) );
	}

	public function getErrorText(): string {
		return $this->errorText;
	}

	public function getDetail(): ?string {
		return $this->detail;
	}

	/**
	 * @return string The error as HTML, with the detail below it in a pre element; both are escaped
	 */
	public function getErrorHtml(): string {
		$html = '<div class="error">' . htmlspecialchars( $this->errorText, ENT_NOQUOTES ) . '</div>';
		if ( $this->detail !== null ) {
			$html .= "\n<pre>" . htmlspecialchars( $this->detail ) . '</pre>';
		}
		return $html;
	}
}
