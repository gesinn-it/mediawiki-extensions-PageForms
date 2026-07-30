<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Parser;
use ParserOptions;
use PFValuesUtils;

/**
 * Holds the state of a FormField that is specific to one instance of the
 * form being rendered, as opposed to the field's static form-definition
 * configuration (which stays on FormField itself).
 *
 * This covers: the field's HTML input name and whether it is disabled -
 * both computed once, from the form-definition context, when the FormField
 * is created - and the deferred 'remote autocompletion' possible-values
 * fetch introduced in #187, which is genuinely resolved later, once the
 * field's current value is known.
 *
 * @ingroup PF
 */
class FormInstanceField {

	private FormField $formField;
	private ?string $mInputName = null;
	private bool $mIsDisabled = false;
	/**
	 * Set instead of eagerly fetching the FormField's possible-values list
	 * when 'remote autocompletion' is active and the source exceeds
	 * $wgPageFormsMaxLocalAutocompleteValues (see #187). Resolved on demand
	 * by resolveDeferredPossibleValues() once the field's current value is
	 * known.
	 */
	private ?string $mDeferredAutocompleteType = null;

	public function __construct( FormField $formField ) {
		$this->formField = $formField;
	}

	public function getFormField(): FormField {
		return $this->formField;
	}

	public function getInputName() {
		return $this->mInputName;
	}

	public function setInputName( $val ): void {
		$this->mInputName = $val;
	}

	public function isDisabled() {
		return $this->mIsDisabled;
	}

	public function setIsDisabled( $val ): void {
		$this->mIsDisabled = $val;
	}

	/**
	 * Marks this field's 'values from ...' fetch as deferred (see #187):
	 * resolved on demand by resolveDeferredPossibleValues() once the
	 * field's current value is known, instead of eagerly fetching the full
	 * source now. The FormField's own possible-values list is set to an
	 * intentionally empty (but non-null) placeholder until then.
	 *
	 * @param string $autocompleteType
	 */
	public function deferPossibleValues( string $autocompleteType ): void {
		$this->mDeferredAutocompleteType = $autocompleteType;
		$this->formField->setPossibleValues( [] );
	}

	/**
	 * Whether the eager 'values from ...' fetch was skipped for this field
	 * (see FormField::canDeferAutocompleteFetch()), leaving the FormField's
	 * possible-values list empty until resolveDeferredPossibleValues() is
	 * called with the field's current value.
	 *
	 * @return bool
	 */
	public function hasDeferredPossibleValues(): bool {
		return $this->mDeferredAutocompleteType !== null;
	}

	/**
	 * Resolves the FormField's possible-values list for a field whose eager
	 * fetch was deferred (see #187), using only the field's current
	 * value(s) - the minimum needed to render it correctly - rather than
	 * the source's full, possibly very large, value list. Live
	 * browsing/searching of the rest of the source is handled client-side
	 * via PF_AutocompleteAPI.
	 *
	 * A no-op when nothing was deferred, or when there is no current value
	 * to resolve.
	 *
	 * @param string|null $curValue
	 */
	public function resolveDeferredPossibleValues( ?string $curValue ): void {
		if ( $this->mDeferredAutocompleteType === null ) {
			return;
		}

		$this->formField->setPossibleValues( [] );
		if ( $curValue === null || $curValue === '' ) {
			return;
		}

		$delimiter = $this->formField->getFieldArgs()['delimiter'] ?? ',';
		$rawValues = $this->formField->isList() ? explode( $delimiter, $curValue ) : [ $curValue ];
		$rawValues = array_values( array_unique( array_filter(
			array_map( 'trim', $rawValues ),
			static fn ( $v ) => $v !== ''
		) ) );

		if ( $rawValues === [] ) {
			return;
		}

		$this->formField->setPossibleValues( PFValuesUtils::addDisplayTitlesForPageValues( $rawValues ) );
	}

	/**
	 * Since Page Forms uses a hook system for the functions that
	 * create HTML inputs, most arguments are contained in the "$other_args"
	 * array - create this array, using the attributes of the underlying
	 * form field, the template field it corresponds to (if any), and this
	 * instance's resolved possible-values.
	 * @param Parser $parser Must already be titled by the caller (see issue #189) -
	 *   wikitext evaluated below is resolved against $parser->getTitle().
	 * @param array|null $default_args
	 * @return array
	 */
	public function getArgumentsForInputCall( Parser $parser, ?array $default_args = null ) {
		$formField = $this->formField;
		$templateField = $formField->getTemplateField();

		// MW 1.43 compat: same typed-property guard as in FormField::newFromFormFieldTag() -
		// see comment there for the full explanation.
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromAnon() );
		}
		$parser->clearState();
		$parser->setOutputType( Parser::OT_HTML );

		// start with the arguments array already defined
		$other_args = $formField->getFieldArgs();
		// a value defined for the form field should always supersede
		// the coresponding value for the template field
		$other_args['possible_values'] = $formField->getPossibleValues();
		if ( !$formField->hasOwnPossibleValues() ) {
			if ( $formField->hasFieldArg( 'mapping using translate' ) ) {
				$other_args['value_labels'] = [];
				foreach ( $other_args['possible_values'] as $key ) {
					$other_args['value_labels'][$key] = $parser->recursiveTagParse(
						'{{int:' . $formField->getFieldArg( 'mapping using translate' ) . $key . '}}'
					);
				}
			} else {
				$other_args['value_labels'] = $templateField->getValueLabels();
			}
		}
		$other_args['is_list'] = ( $formField->isList() || $templateField->isList() );
		if ( $templateField->isMandatory() ) {
			$other_args['mandatory'] = true;
		}
		if ( $templateField->isUnique() ) {
			$other_args['unique'] = true;
		}

		// Now add some extension-specific arguments to the input call.
		if ( defined( 'SMW_VERSION' ) ) {
			$formField->getArgumentsForInputCallSMW( $other_args );
		}

		// Now merge in the default values set by FormPrinter, if
		// there were any - put the default values first, so that if
		// there's a conflict they'll be overridden.
		if ( $default_args != null ) {
			$other_args = array_merge( $default_args, $other_args );
		}

		foreach ( $other_args as $argname => $argvalue ) {
			if ( is_string( $argvalue ) ) {
				$other_args[$argname] =
					$parser->recursiveTagParse( $argvalue );
			}
		}

		return $other_args;
	}

}
