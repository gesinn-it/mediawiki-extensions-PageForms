<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use WebRequest;

/**
 * The values that a submitted form has for one instance of a template, read from the request.
 *
 * @ingroup PF
 */
class TemplateSubmittedValues {

	/** @var array The values of the instance read last, by field name */
	private array $values = [];

	private int $numInstances = 0;

	/**
	 * Read the values of an instance of the template. The values of the instance read before are
	 * forgotten, except for the first instance, whose values stay if the request has none.
	 *
	 * @param WebRequest $request
	 * @param string $templateName
	 * @param bool $allowsMultiple
	 * @param int $instanceNum The instance to read the values of, counted from 0
	 * @param int|null $numSeenInstancesOnPage How many instances the page has up to the template
	 *   instance that is printed, if the page calls the template
	 */
	public function read(
		WebRequest $request, string $templateName, bool $allowsMultiple, int $instanceNum,
		?int $numSeenInstancesOnPage
	): void {
		// Reset values for every new instance, if this is a
		// multiple-instance template.
		if ( $instanceNum > 0 ) {
			$this->values = [];
		}

		// Periods are replaced with underlines too, since that's what
		// POST does to strings anyway.
		$queryTemplateName = str_replace( [ ' ', '.' ], '_', $templateName );

		$allValuesFromSubmit = $request->getArray( $queryTemplateName );
		if ( $allValuesFromSubmit === null ) {
			return;
		}
		if ( !$allowsMultiple ) {
			$this->values = $allValuesFromSubmit;
			return;
		}
		// If this data came from a spreadsheet, unescape some characters.
		$spreadsheetTemplates = $request->getArray( 'spreadsheet_templates' );
		if ( is_array( $spreadsheetTemplates ) && array_key_exists( $queryTemplateName, $spreadsheetTemplates ) ) {
			$allValuesFromSubmit = self::unescapeSpreadsheetValues( $allValuesFromSubmit );
		}
		$this->readInstance( $allValuesFromSubmit, $instanceNum, $numSeenInstancesOnPage );
	}

	/**
	 * @return array The values of the instance read last, by field name
	 */
	public function get(): array {
		return $this->values;
	}

	public function hasValues(): bool {
		return $this->values != null;
	}

	/**
	 * @return int How many instances the request has values for
	 */
	public function numInstances(): int {
		return $this->numInstances;
	}

	/**
	 * @param array $allValuesFromSubmit The values of all instances, by row
	 * @return array
	 */
	private static function unescapeSpreadsheetValues( array $allValuesFromSubmit ): array {
		foreach ( $allValuesFromSubmit as &$rowValues ) {
			foreach ( $rowValues as &$curValue ) {
				$curValue = str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], $curValue );
			}
		}
		return $allValuesFromSubmit;
	}

	/**
	 * Gets the values for this instance of a multiple-instance template.
	 *
	 * @param array $allValuesFromSubmit The values of all instances, by instance key
	 * @param int $instanceNum
	 * @param int|null $numSeenInstancesOnPage
	 */
	private function readInstance( array $allValuesFromSubmit, int $instanceNum, ?int $numSeenInstancesOnPage ): void {
		$valuesFromSubmitKeys = [];
		foreach ( array_keys( $allValuesFromSubmit ) as $key ) {
			if ( $key != 'num' ) {
				$valuesFromSubmitKeys[] = $key;
			}
		}
		$this->numInstances = count( $valuesFromSubmitKeys );
		# First search if there are keys \d+a? (keys are made of an integer plus optional letter a),
		# return them if the main loop modify existing templates
		for ( $i = 0; $i < $this->numInstances; $i++ ) {
			$intkey = preg_filter( '/^(\d+)a?$/', '$1', (string)$valuesFromSubmitKeys[$i] );
			if ( $intkey !== null ) {
				$intkey = (int)$intkey;
			}
			if ( $intkey !== null && $numSeenInstancesOnPage !== null && $intkey < $numSeenInstancesOnPage ) {
				if ( $intkey === $instanceNum ) {
					$instanceKey = $valuesFromSubmitKeys[$intkey];
					$this->values = self::asValues( $allValuesFromSubmit[$instanceKey] );
					return;
				}
				unset( $valuesFromSubmitKeys[$i] );
			} else {
				break;
			}
		}
		# The main loop is still in existing templates
		if ( $numSeenInstancesOnPage !== null && $instanceNum < $numSeenInstancesOnPage ) {
			return;
		}
		# Now the main loop is adding new templates
		$valuesFromSubmitKeys = array_values( $valuesFromSubmitKeys );
		$offset = $instanceNum - ( $numSeenInstancesOnPage ?? 0 );
		if ( $offset < count( $valuesFromSubmitKeys ) ) {
			$instanceKey = $valuesFromSubmitKeys[$offset];
			$this->values = self::asValues( $allValuesFromSubmit[$instanceKey] );
		}
	}

	/**
	 * @param mixed $instanceValues What the request has for an instance: a string if it was not sent
	 *   as an array of fields
	 * @return array
	 */
	private static function asValues( $instanceValues ): array {
		return is_array( $instanceValues ) ? $instanceValues : [];
	}
}
