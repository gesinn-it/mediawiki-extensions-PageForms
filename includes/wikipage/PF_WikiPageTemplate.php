<?php
/**
 * @author Yaron Koren
 * @ingroup PF
 */

/**
 * Represents a single template call within a wiki page.
 */
class PFWikiPageTemplate {
	private $mName;
	private $mParams = [];
	private $mAddUnhandledParams;
	private $mInstanceUnhandledParams = [];

	public function __construct( $name, $addUnhandledParams ) {
		$this->mName = $name;
		$this->mAddUnhandledParams = $addUnhandledParams;
	}

	public function addParam( $paramName, $value ) {
		$this->mParams[] = new PFWikiPageTemplateParam( $paramName, $value );
	}

	public function addUnhandledParam( $paramName, $value ) {
		// See if there's already a value for this parameter, and
		// if it's blank, replace it.
		// This only happens if values are coming in from both the
		// page and the form submission, i.e. for #autoedit.
		foreach ( $this->mParams as $i => $param ) {
			if ( $param->getName() == $paramName ) {
				if ( $param->getValue() == '' ) {
					$this->mParams[$i]->setValue( $value );
				}
				return;
			}
		}

		// All other cases, probably.
		$this->addParam( $paramName, $value );
	}

	/**
	 * Set the parameters the form does not define that belong to this very call. They are added
	 * after the form's own parameters, like the ones a single-instance template reads from the
	 * request.
	 *
	 * @param array<string, mixed> $params Parameter name (URL-encoded) => value
	 */
	public function setInstanceUnhandledParams( array $params ) {
		$this->mInstanceUnhandledParams = $params;
	}

	public function addUnhandledParams( WebRequest $request ) {
		foreach ( $this->mInstanceUnhandledParams as $paramName => $value ) {
			if ( is_string( $value ) ) {
				$this->addUnhandledParam( urldecode( (string)$paramName ), $value );
			}
		}
		$this->mInstanceUnhandledParams = [];
		if ( !$this->mAddUnhandledParams ) {
			return;
		}

		$templateName = str_replace( ' ', '_', $this->mName );
		$prefix = '_unhandled_' . $templateName . '_';
		$prefixSize = strlen( $prefix );
		foreach ( $request->getValues() as $key => $value ) {
			if ( str_starts_with( $key, $prefix ) ) {
				$paramName = urldecode( substr( $key, $prefixSize ) );
				$this->addUnhandledParam( $paramName, $value );
			}
		}
	}

	public function getName() {
		return $this->mName;
	}

	public function getParams() {
		return $this->mParams;
	}
}
