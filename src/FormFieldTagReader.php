<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MediaWiki\Extension\PageForms\FormDefinition\FieldSpec;
use MediaWiki\MediaWikiServices;
use Parser;
use ParserOptions;
use RequestContext;
use Sanitizer;
use User;

/**
 * Sets up a FormField from the tag of the field in a form definition: the flags and arguments of
 * the tag, where the possible values come from and the name of the input.
 *
 * @ingroup PF
 */
class FormFieldTagReader {

	private FormField $field;

	public function __construct( FormField $field ) {
		$this->field = $field;
	}

	/**
	 * @param FieldSpec $spec
	 * @param Template $template
	 * @param TemplateInForm $template_in_form
	 * @param bool $form_is_disabled
	 * @param User $user
	 * @param Parser $parser
	 */
	public function read(
		FieldSpec $spec,
		$template,
		$template_in_form,
		$form_is_disabled,
		User $user,
		Parser $parser
	): void {
		self::prepareParser( $parser );

		$field_name = $spec->getName();
		$template_name = $template_in_form->getTemplateName();

		if ( !$this->setUpTemplateField( $template, $template_in_form, $field_name, $template_name ) ) {
			return;
		}

		$fullFieldName = $template_name . '[' . $field_name . ']';
		$this->applyTemplateFieldDefaults();
		$this->applySpecFlags( $spec, $user );
		$args = $this->readArguments( $spec, $parser, $user, $fullFieldName );

		$this->setUpPossibleValuesFromSources( $args, $template_in_form->getDisplay() );
		$this->setUpDelimiter();
		$this->setPossibleValuesFromList( $args['values'] );
		$this->setUpSemanticProperty( $args['semantic_property'], $fullFieldName );
		$this->setUpMapping( $args['valuesSourceType'], $args['valuesSource'] );

		if ( $template_in_form->allowsMultiple() ) {
			$this->field->setFieldArg( 'part_of_multiple', true );
		}
		if ( count( $args['show_on_select'] ) > 0 ) {
			$this->field->setFieldArg( 'show on select', $args['show_on_select'] );
		}

		// Disable this field if either the whole form is disabled, or
		// it's a restricted field and user doesn't have sysop privileges.
		$this->field->getInstanceField()->setIsDisabled( $form_is_disabled || $this->field->isRestricted() );
		$this->setUpInputName( $template_in_form, $template_name, $field_name, $fullFieldName );
	}

	/**
	 * Whether the full 'values from ...' fetch for $autocompleteType/
	 * $autocompleteSource may be skipped in favor of resolving only the
	 * field's current value later, via resolveDeferredPossibleValues() (see
	 * #187). Requires the 'remote autocompletion' flag, a source that
	 * exceeds the local-autocomplete threshold, and no mapping (mapping
	 * needs the complete source to translate values) or dependent-field
	 * configuration (whose source is filtered by a parent value, not fixed).
	 *
	 * Also excluded: 'display=spreadsheet' templates. Those are rendered by
	 * SpreadsheetHtmlBuilder::spreadsheetHTML(), which reads possible_values
	 * directly - bypassing FormFieldHtmlBuilder::formFieldHTML(), the only
	 * place resolveDeferredPossibleValues() is called - and holds one
	 * current value per grid row rather than a single value, which is
	 * incompatible with this single-value resolution. ('display=table' is
	 * fine: SpreadsheetHtmlBuilder::tableHTML() calls formFieldHTML() once
	 * per row with that row's value.)
	 *
	 * @param string $autocompleteType
	 * @param string $autocompleteSource
	 * @param string|null $display The containing template's display mode
	 *  (TemplateInForm::getDisplay()).
	 * @return bool
	 */
	private function canDeferAutocompleteFetch(
		string $autocompleteType, string $autocompleteSource, ?string $display
	): bool {
		if ( $display === 'spreadsheet' ) {
			return false;
		}
		if ( !$this->field->hasFieldArg( 'remote autocompletion' ) ) {
			return false;
		}
		if ( $this->field->hasFieldArg( 'mapping template' ) ||
			$this->field->hasFieldArg( 'mapping property' ) ||
			$this->field->hasFieldArg( 'values dependent on' )
		) {
			return false;
		}
		return AutocompleteSourceCount::exceedsLocalThreshold( $autocompleteType, $autocompleteSource );
	}

	/**
	 * MW 1.43 compat: Parser::$mStripState and $mOutputType are typed properties
	 * that are only initialised after clearState()/setOutputType() are called.
	 * $parser must already be titled by the caller (see FormPrinter::createFreshParser()) -
	 * wikitext evaluated while reading the field (e.g. a 'default=' value containing {{PAGENAME}})
	 * is resolved against $parser->getTitle(), and an untitled parser resolves it against a
	 * "Badtitle" placeholder instead (see issue #189).
	 *
	 * The state of the parser is cleared, which is a side effect on the parser of the caller.
	 */
	private static function prepareParser( Parser $parser ): void {
		if ( !$parser->getOptions() ) {
			$parser->setOptions( ParserOptions::newFromAnon() );
		}
		$parser->clearState();
		$parser->setOutputType( Parser::OT_HTML );
	}

	/**
	 * See if the field matches one of the fields defined for the template - if it does, use all
	 * available information about that field; if it doesn't, either include it in the form or
	 * not, depending on whether the template has a 'strict' setting in the form definition.
	 *
	 * @param Template $template
	 * @param TemplateInForm $template_in_form
	 * @param string $field_name
	 * @param string|null $template_name
	 * @return bool false if the field is not part of the form
	 */
	private function setUpTemplateField( $template, $template_in_form, $field_name, $template_name ): bool {
		global $wgPageFormsEmbeddedTemplates;

		$template_field = $template->getFieldNamed( $field_name );
		if ( $template_field != null ) {
			$this->field->setTemplateField( $template_field );
		} else {
			if ( $template_in_form->strictParsing() ) {
				$this->field->setTemplateField( new TemplateField() );
				$this->field->setIsList( false );
				return false;
			}
			$this->field->setTemplateField( TemplateField::create( $field_name, null ) );
		}

		$embeddedTemplate = $this->field->getTemplateField()->getHoldsTemplate();
		if ( $embeddedTemplate != '' ) {
			$this->field->setIsHidden( true );
			$this->field->setHoldsTemplate( true );
			// Store this information so that the embedded/"held"
			// template - which is hopefully after this one in the
			// form definition - can be handled correctly. In forms,
			// both the embedding field and the embedded template are
			// specified as such, but in templates (i.e., with
			// #template_params), it's only the embedding field.
			$wgPageFormsEmbeddedTemplates[$embeddedTemplate] = [ $template_name, $field_name ];
		}
		return true;
	}

	/**
	 * We set "values from ..." params if there are corresponding
	 * values set in #template_params - this is a bit of a @hack,
	 * since we should really just use these values directly, but
	 * there are various places in the code that check for "values
	 * from ...", so it's easier to just pretend that these params
	 * were set.
	 */
	private function applyTemplateFieldDefaults(): void {
		$categoryFromTemplate = $this->field->getTemplateField()->getCategory();
		if ( $categoryFromTemplate !== null ) {
			$this->field->setFieldArg( 'values from category', $categoryFromTemplate );
		}
		$namespaceFromTemplate = $this->field->getTemplateField()->getNSText();
		if ( $namespaceFromTemplate !== null ) {
			$this->field->setFieldArg( 'values from namespace', $namespaceFromTemplate );
		}
	}

	private function applySpecFlags( FieldSpec $spec, User $user ): void {
		if ( $spec->isMandatory() ) {
			$this->field->setIsMandatory( true );
		}
		if ( $spec->isHidden() ) {
			$this->field->setIsHidden( true );
		}
		if ( $spec->isList() ) {
			$this->field->setIsList( true );
		}
		if ( $spec->isUnique() ) {
			$this->field->setFieldArg( 'unique', true );
		}
		if ( $spec->isFlag( 'restricted' ) ) {
			$this->field->setIsRestricted( !$user->isAllowed( 'editrestrictedfields' ) );
		}
		if ( $spec->isFlag( 'edittools' ) ) {
			// free text only
			$this->field->setFieldArg( 'edittools', true );
		}
	}

	/**
	 * Go through the arguments of the field tag. What can only be used once all arguments are
	 * known (the values and where they come from, the property, "show on select") is returned.
	 *
	 * @param FieldSpec $spec
	 * @param Parser $parser
	 * @param User $user
	 * @param string $fullFieldName
	 * @return array<string, mixed> The keys semantic_property, show_on_select, values, valuesSourceType,
	 *   valuesSource and valuesFromPropertyName
	 */
	private function readArguments( FieldSpec $spec, Parser $parser, User $user, string $fullFieldName ): array {
		$args = [
			'semantic_property' => null,
			'show_on_select' => [],
			'values' => null,
			'valuesSourceType' => null,
			'valuesSource' => null,
			// The actual fetch is deferred to after the full loop has run, so that a 'remote
			// autocompletion' argument appearing later in the tag is already known.
			'valuesFromPropertyName' => null,
		];

		foreach ( $spec->getArgs() as $argName => $argValue ) {
			if ( $spec->isFlag( $argName ) ) {
				// add handling for single-value params, for custom input types
				$this->field->setFieldArg( $argName, true );

				if ( $argName == 'holds template' ) {
					$this->field->setIsHidden( true );
					$this->field->setHoldsTemplate( true );
				}
			} else {
				// First, set each value as its own entry in the arguments of the field.
				$this->field->setFieldArg( $argName, $argValue );

				// Then, do all special handling.
				$this->readArgument( $argName, $argValue, $parser, $user, $fullFieldName, $args );
			}
		}

		return $args;
	}

	/**
	 * The special handling of one argument that has a value.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @param User $user
	 * @param string $fullFieldName
	 * @param array &$args What readArguments() returns
	 */
	private function readArgument(
		string $argName, $argValue, Parser $parser, User $user, string $fullFieldName, array &$args
	): void {
		if ( $this->readValuesSourceArgument( $argName, $argValue, $parser, $args ) ||
			$this->readUniqueArgument( $argName, $argValue, $parser )
		) {
			return;
		}
		if ( $argName == 'autocapitalize' ) {
			$this->field->setAutocapitalize( strtolower( $argValue ) );
		} elseif ( $argName == 'input type' ) {
			$this->field->setInputType( $argValue );
		} elseif ( $argName == 'default' ) {
			// We call recursivePreprocess() here,
			// and not the more standard
			// recursiveTagParse(), so that
			// wikitext in the value, and bare URLs,
			// will not get turned into HTML.
			$this->field->setDefaultValue( $parser->recursivePreprocess( $argValue ) );
		} elseif ( $argName == 'preload' ) {
			$this->field->setPreloadPage( $argValue );
		} elseif ( $argName == 'label' ) {
			$this->field->setLabel( $argValue );
		} elseif ( $argName == 'label msg' ) {
			$this->field->setLabelMsg( $argValue );
		} elseif ( $argName == 'show on select' ) {
			$this->readShowOnSelect( $argValue, $parser, $args['show_on_select'] );
		} elseif ( $argName == 'values dependent on' ) {
			global $wgPageFormsDependentFields;
			$wgPageFormsDependentFields[] = [ $argValue, $fullFieldName ];
		} elseif ( $argName == 'property' ) {
			$args['semantic_property'] = $argValue;
		} elseif ( $argName == 'default filename' ) {
			$this->field->setFieldArg( 'default filename', $this->defaultFilename( $argValue, $parser ) );
		} elseif ( $argName == 'restricted' ) {
			$effectiveGroups = MediaWikiServices::getInstance()->getUserGroupManager()
				->getUserEffectiveGroups( $user );
			$this->field->setIsRestricted( !array_intersect(
				$effectiveGroups, array_map( 'trim', explode( ',', $argValue ) )
			) );
		}
	}

	/**
	 * The arguments that say where the possible values come from, or give them.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @param array &$args What readArguments() returns
	 * @return bool Whether the argument is one of these
	 */
	private function readValuesSourceArgument( string $argName, $argValue, Parser $parser, array &$args ): bool {
		if ( $argName == 'values' ) {
			// Handle this one only after
			// 'delimiter' has also been set.
			$args['values'] = $parser->recursiveTagParse( $argValue );
		} elseif ( $argName == 'values from property' ) {
			$args['valuesFromPropertyName'] = $argValue;
		} elseif ( $argName == 'values from wikidata' ) {
			$args['valuesSourceType'] = 'wikidata';
			$args['valuesSource'] = urlencode( $argValue );
		} elseif ( $argName == 'values from query' ) {
			$args['valuesSourceType'] = 'query';
			$args['valuesSource'] = $argValue;
		} elseif ( $argName == 'values from category' ) {
			$valuesSource = $parser->recursiveTagParse( $argValue );
			global $wgCapitalLinks;
			if ( $wgCapitalLinks ) {
				$valuesSource = ucfirst( $valuesSource );
			}
			$args['valuesSource'] = $valuesSource;
			$args['valuesSourceType'] = 'category';
		} elseif ( $argName == 'values from concept' ) {
			$args['valuesSourceType'] = 'concept';
			$args['valuesSource'] = $parser->recursiveTagParse( $argValue );
		} elseif ( $argName == 'values from namespace' ) {
			$args['valuesSourceType'] = 'namespace';
			$args['valuesSource'] = $parser->recursiveTagParse( $argValue );
		} else {
			return false;
		}
		return true;
	}

	/**
	 * The arguments that make the field's values unique within a category, namespace or concept.
	 *
	 * @param string $argName
	 * @param string $argValue
	 * @param Parser $parser
	 * @return bool Whether the argument is one of these
	 */
	private function readUniqueArgument( string $argName, $argValue, Parser $parser ): bool {
		if ( $argName != 'unique for category' && $argName != 'unique for namespace' &&
			$argName != 'unique for concept'
		) {
			return false;
		}
		$this->field->setFieldArg( 'unique', true );
		$scope = substr( $argName, strlen( 'unique for ' ) );
		$this->field->setFieldArg( "unique_for_$scope", $this->plainText( $argValue, $parser ) );
		return true;
	}

	/**
	 * @param string $argValue
	 * @param Parser $parser
	 * @param array &$showOnSelect The div IDs, each with the options that show it
	 */
	private function readShowOnSelect( $argValue, Parser $parser, array &$showOnSelect ): void {
		// html_entity_decode() is needed to turn '&gt;' to '>'
		$vals = explode( ';', html_entity_decode( $argValue ) );
		foreach ( $vals as $val ) {
			$val = trim( $val );
			if ( $val === '' ) {
				continue;
			}
			$option_div_pair = explode( '=>', $val, 2 );
			if ( count( $option_div_pair ) > 1 ) {
				$option = trim( $parser->recursiveTagParse( $option_div_pair[0] ) );
				$div_id = $option_div_pair[1];
				if ( array_key_exists( $div_id, $showOnSelect ) ) {
					$showOnSelect[$div_id][] = $option;
				} else {
					$showOnSelect[$div_id] = [ $option ];
				}
			} else {
				$showOnSelect[$val] = [];
			}
		}
	}

	private function defaultFilename( string $argValue, Parser $parser ): string {
		$titleGlobal = RequestContext::getMain()->getTitle();
		$page_name = $titleGlobal->getText();
		if ( $titleGlobal->isSpecialPage() ) {
			// If it's of the form
			// Special:FormEdit/form/target,
			// get just the target.
			$pageNameComponents = explode( '/', $page_name, 3 );
			if ( count( $pageNameComponents ) == 3 ) {
				$page_name = $pageNameComponents[2];
			}
		}
		$default_filename = str_replace( '<page name>', $page_name, $argValue );
		// Expand the value, so default filename can include parser functions.
		return $this->plainText( $default_filename, $parser );
	}

	/**
	 * The text of a value that is written into the page later on, where it is escaped for its place.
	 * The value is expanded, but not turned into HTML, because HTML would be escaped a second time.
	 */
	private function plainText( string $value, Parser $parser ): string {
		return Sanitizer::decodeCharReferences( $parser->recursivePreprocess( $value ) );
	}

	/**
	 * Fetch the possible values from "values from ..." - or note that it is left to the
	 * autocomplete request, if the field has 'remote autocompletion'.
	 *
	 * The 'values from property' fetch is made here, after all arguments were read, so that a
	 * 'remote autocompletion' argument appearing later in the tag is known when deciding whether
	 * to defer the fetch.
	 *
	 * @param array $args What readArguments() returns
	 * @param string $display The display mode of the template in the form
	 */
	private function setUpPossibleValuesFromSources( array $args, $display ): void {
		$valuesFromPropertyName = $args['valuesFromPropertyName'];
		$valuesSourceType = $args['valuesSourceType'];
		$valuesSource = $args['valuesSource'];

		if ( $valuesFromPropertyName !== null ) {
			if ( $this->canDeferAutocompleteFetch( 'property', $valuesFromPropertyName, $display ) ) {
				$this->field->getInstanceField()->deferPossibleValues( 'property' );
			} else {
				$possibleValues = PropertyValues::all( $valuesFromPropertyName );
				$this->field->setPossibleValues( $possibleValues );
				$this->field->setUseDisplayTitle( is_string( array_key_first( $possibleValues ) ) );
			}
		}

		if ( $valuesSourceType !== null && $valuesSource !== null && ( $valuesSourceType !== 'wikidata' || (
				$this->field->getInputType() !== 'combobox' && $this->field->getInputType() !== 'tokens' ) ) ) {
			if ( in_array( $valuesSourceType, [ 'category', 'namespace', 'concept' ], true ) &&
				$this->canDeferAutocompleteFetch( $valuesSourceType, $valuesSource, $display )
			) {
				$this->field->getInstanceField()->deferPossibleValues( $valuesSourceType );
			} else {
				$this->field->setPossibleValues(
					ValuesUtils::getAutocompleteValues( $valuesSource, $valuesSourceType )
				);
				if ( in_array( $valuesSourceType, [ 'category', 'namespace', 'concept' ], true ) ) {
					global $wgPageFormsUseDisplayTitle;
					$this->field->setUseDisplayTitle( $wgPageFormsUseDisplayTitle );
				}
			}
		}
	}

	private function setUpDelimiter(): void {
		if ( !$this->field->hasFieldArg( 'delimiter' ) ) {
			$delimiterFromTemplate = $this->field->getTemplateField()->getDelimiter();
			if ( $delimiterFromTemplate == '' ) {
				$this->field->setFieldArg( 'delimiter', ',' );
			} else {
				$this->field->setFieldArg( 'delimiter', $delimiterFromTemplate );
				$this->field->setIsList( true );
			}
		}
	}

	/**
	 * If the 'values' parameter was set, separate it based on the 'delimiter' parameter, if any.
	 *
	 * @param string|null $values
	 */
	private function setPossibleValuesFromList( $values ): void {
		if ( $values != null ) {
			// Remove whitespaces, and un-escape characters
			$valuesArray = array_map( 'trim', explode( $this->field->getFieldArg( 'delimiter' ), $values ) );
			$this->field->setPossibleValues( array_map( 'htmlspecialchars_decode', $valuesArray ) );
		}
	}

	/**
	 * Do some data storage specific to the Semantic MediaWiki extension.
	 * This must happen before the "mapping template"/"mapping property"
	 * handling, since a 'property' set in the form definition can
	 * populate the template field's possible values (from the SMW
	 * property's "Allows value"/"Allows value list" annotations), and
	 * those values need to be in place before we decide what to map.
	 *
	 * @param string|null $semantic_property The property set in the form definition
	 * @param string $fullFieldName
	 */
	private function setUpSemanticProperty( $semantic_property, string $fullFieldName ): void {
		if ( defined( 'SMW_VERSION' ) ) {
			// If a property was set in the form definition,
			// overwrite whatever is set in the template field -
			// this is somewhat of a hack, since parameters set in
			// the form definition are meant to go into the
			// FormField object, not the TemplateField object
			// it contains;
			// it seemed like too much work, though, to create an
			// FormField::setSemanticProperty() function just for
			// this call.
			if ( $semantic_property !== null ) {
				$this->field->getTemplateField()->setSemanticProperty( $semantic_property );
			} else {
				$semantic_property = $this->field->getTemplateField()->getSemanticProperty();
			}
			if ( $semantic_property !== null ) {
				global $wgPageFormsFieldProperties;
				$wgPageFormsFieldProperties[$fullFieldName] = $semantic_property;
			}
		}
	}

	/**
	 * @param string|null $valuesSourceType
	 * @param string|null $valuesSource
	 */
	private function setUpMapping( $valuesSourceType, $valuesSource ): void {
		// A deferred field intentionally has an empty (but non-null)
		// mPossibleValues at this point (see above) - it must not be
		// overwritten by the template field's unrelated list.
		if ( !$this->field->hasOwnPossibleValues() && !$this->field->getInstanceField()->hasDeferredPossibleValues() ) {
			$this->field->setPossibleValues( $this->field->getTemplateField()->getPossibleValues() );
		}

		$mappingType = null;
		if ( $this->field->hasFieldArg( 'mapping template' ) ) {
			$mappingType = 'template';
		} elseif ( $this->field->hasFieldArg( 'mapping property' ) ) {
			$mappingType = 'property';
		} elseif ( $this->field->getUseDisplayTitle() ) {
			$this->field->setPossibleValues( MappingLabels::disambiguate( $this->field->getOwnPossibleValues() ) );
		}

		if ( $mappingType !== null && $this->field->getOwnPossibleValues() !== [] ) {
			// If we're going to be mapping values, we need to have
			// the exact page name - and if these values come from
			// "values from namespace", the namespace prefix was
			// not included, so we need to add it now.
			if ( $valuesSourceType == 'namespace' && $valuesSource != '' && $valuesSource != 'Main' ) {
				$this->field->setPossibleValues( array_map(
					static fn ( $value ) => $valuesSource . ':' . $value,
					$this->field->getOwnPossibleValues()
				) );
				// Has to be set to false to not mess up the
				// handling.
				$this->field->setUseDisplayTitle( false );
			}

			$this->field->setMappedValues( $mappingType );
		}
	}

	private function setUpInputName( $template_in_form, $template_name, $field_name, string $fullFieldName ): void {
		if ( $template_name === null || $template_name === '' ) {
			$this->field->getInstanceField()->setInputName( $field_name );
		} elseif ( $template_in_form->allowsMultiple() ) {
			// 'num' will get replaced by an actual index, either in PHP
			// or in Javascript, later on
			$this->field->getInstanceField()->setInputName( $template_name . '[num][' . $field_name . ']' );
			$this->field->setFieldArg( 'origName', $fullFieldName );
		} else {
			$this->field->getInstanceField()->setInputName( $fullFieldName );
		}
	}
}
