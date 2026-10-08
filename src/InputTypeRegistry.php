<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Registry of all available form input types and their mappings to
 * Semantic MediaWiki property types.
 *
 * This class is the only place that holds the lookup tables. FormPrinter and
 * FormFieldHtmlBuilder use the same instance, so an input type registered at any
 * time is seen by both. The deprecated properties $mInputTypeHooks and
 * $mSemanticTypeHooks of FormPrinter go through the methods of this class.
 */
class InputTypeRegistry {

	/** @var array<string, string> Map of input type name → class name */
	private array $inputTypeClasses = [];

	/** @var array<string, string> Map of SMW scalar property type → default input type name */
	private array $defaultInputForPropType = [];

	/** @var array<string, string> Map of SMW list property type → default input type name */
	private array $defaultInputForPropTypeList = [];

	/** @var array<string, string[]> Map of SMW scalar property type → possible input type names */
	private array $possibleInputsForPropType = [];

	/** @var array<string, string[]> Map of SMW list property type → possible input type names */
	private array $possibleInputsForPropTypeList = [];

	/** @var array<string, array{0: string, 1: array}> Input type name → [ class name, default arguments ] */
	private array $inputTypeHooks = [];

	/** @var array<string, array<int, array{0: string, 1: array}>> SMW property type → by list flag [ class name, default arguments ] */
	private array $semanticTypeHooks = [];

	/**
	 * The registry with the input types that Page Forms comes with.
	 */
	public static function newWithBuiltInTypes(): self {
		global $wgPageFormsDisableOutsideServices;

		$registry = new self();
		foreach ( [
			'PFTextInput', 'PFTextWithAutocompleteInput', 'PFTextAreaInput', 'PFTextAreaWithAutocompleteInput',
			'PFDateInput', 'PFStartDateInput', 'PFEndDateInput', 'PFDatePickerInput', 'PFDateTimePicker',
			'PFDateTimeInput', 'PFStartDateTimeInput', 'PFEndDateTimeInput', 'PFYearInput', 'PFCheckboxInput',
			'PFDropdownInput', 'PFRadioButtonInput', 'PFCheckboxesInput', 'PFListBoxInput', 'PFComboBoxInput',
			'PFTreeInput', 'PFTokensInput', 'PFRegExpInput', 'PFRatingInput', 'PFSFSelectInput',
		] as $inputTypeClass ) {
			$registry->register( $inputTypeClass );
		}
		// Add this if the Semantic Maps extension is not
		// included, or if it's SM (really Maps) v4.0 or higher.
		if ( !$wgPageFormsDisableOutsideServices ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal SM_VERSION guarded by defined()
			if ( !defined( 'SM_VERSION' ) || version_compare( SM_VERSION, '4.0', '>=' ) ) {
				$registry->register( 'PFGoogleMapsInput' );
			}
			$registry->register( 'PFOpenLayersInput' );
			$registry->register( 'PFLeafletInput' );
		}
		return $registry;
	}

	/**
	 * Register an input type class and populate the lookup tables for its
	 * supported SMW property types.
	 *
	 * @param class-string<\PFFormInput> $inputTypeClass Fully qualified class name of the input type.
	 */
	public function register( string $inputTypeClass ): void {
		$inputTypeName = $inputTypeClass::getName();
		$this->inputTypeClasses[$inputTypeName] = $inputTypeClass;
		$this->setInputTypeHook( $inputTypeName, $inputTypeClass, [] );

		$defaultProperties = $inputTypeClass::getDefaultPropTypes();
		foreach ( $defaultProperties as $propertyType => $additionalValues ) {
			$this->defaultInputForPropType[$propertyType] = $inputTypeName;
			$this->setSemanticTypeHook( $propertyType, false, $inputTypeClass, $additionalValues );
		}

		$defaultPropertyLists = $inputTypeClass::getDefaultPropTypeLists();
		foreach ( $defaultPropertyLists as $propertyType => $additionalValues ) {
			$this->defaultInputForPropTypeList[$propertyType] = $inputTypeName;
			$this->setSemanticTypeHook( $propertyType, true, $inputTypeClass, $additionalValues );
		}

		$otherProperties = $inputTypeClass::getOtherPropTypesHandled();
		foreach ( $otherProperties as $propertyTypeID ) {
			$this->possibleInputsForPropType[$propertyTypeID][] = $inputTypeName;
		}

		$otherPropertyLists = $inputTypeClass::getOtherPropTypeListsHandled();
		foreach ( $otherPropertyLists as $propertyTypeID ) {
			$this->possibleInputsForPropTypeList[$propertyTypeID][] = $inputTypeName;
		}
	}

	/**
	 * @param string $inputTypeName
	 * @return string|null The class name for the given input type, or null if not registered.
	 */
	public function getClass( string $inputTypeName ): ?string {
		return $this->inputTypeClasses[$inputTypeName] ?? null;
	}

	/**
	 * @param bool $isList Whether to look up list-type properties.
	 * @param string $propertyType The SMW property type identifier.
	 * @return string|null The default input type name, or null if none registered.
	 */
	public function getDefaultInputType( bool $isList, string $propertyType ): ?string {
		if ( $isList ) {
			return $this->defaultInputForPropTypeList[$propertyType] ?? null;
		}
		return $this->defaultInputForPropType[$propertyType] ?? null;
	}

	/**
	 * @param bool $isList Whether to look up list-type properties.
	 * @param string $propertyType The SMW property type identifier.
	 * @return string[] The possible input type names for this property type.
	 */
	public function getPossibleInputTypes( bool $isList, string $propertyType ): array {
		if ( $isList ) {
			return $this->possibleInputsForPropTypeList[$propertyType] ?? [];
		}
		return $this->possibleInputsForPropType[$propertyType] ?? [];
	}

	/**
	 * @return string[] All registered input type names.
	 */
	public function getAllTypeNames(): array {
		return array_keys( $this->inputTypeClasses );
	}

	/**
	 * Use a class for an input type name, without looking at what the class says about itself.
	 *
	 * @param string $inputType
	 * @param string $className
	 * @param array $defaultArgs
	 */
	public function setInputTypeHook( string $inputType, string $className, array $defaultArgs ): void {
		$this->inputTypeHooks[$inputType] = [ $className, $defaultArgs ];
	}

	/**
	 * Use a class as the input for an SMW property type.
	 *
	 * @param string $propertyType
	 * @param bool $isList
	 * @param string $className
	 * @param array $defaultArgs
	 */
	public function setSemanticTypeHook(
		string $propertyType, bool $isList, string $className, array $defaultArgs
	): void {
		$this->semanticTypeHooks[$propertyType][(int)$isList] = [ $className, $defaultArgs ];
	}

	/**
	 * @param string $inputType
	 * @return array{0: string, 1: array}|null [ class name, default arguments ] for an input type name
	 */
	public function getInputTypeHook( string $inputType ): ?array {
		return $this->inputTypeHooks[$inputType] ?? null;
	}

	/**
	 * @param string $propertyType
	 * @param bool $isList
	 * @return array{0: string, 1: array}|null [ class name, default arguments ] for an SMW property type
	 */
	public function getSemanticTypeHook( string $propertyType, bool $isList ): ?array {
		return $this->semanticTypeHooks[$propertyType][(int)$isList] ?? null;
	}

	/**
	 * @return array<string, array{0: string, 1: array}> A copy of the table of input type hooks
	 */
	public function getInputTypeHooks(): array {
		return $this->inputTypeHooks;
	}

	/**
	 * @return array<string, array<int, array{0: string, 1: array}>> A copy of the table of SMW type hooks
	 */
	public function getSemanticTypeHooks(): array {
		return $this->semanticTypeHooks;
	}

	/**
	 * Remove the hook of an input type name or of an SMW property type, for deprecated array access.
	 *
	 * @param string $key
	 * @param bool $semantic Whether the key is an SMW property type
	 */
	public function removeHook( string $key, bool $semantic ): void {
		if ( $semantic ) {
			unset( $this->semanticTypeHooks[$key] );
		} else {
			unset( $this->inputTypeHooks[$key] );
		}
	}
}
