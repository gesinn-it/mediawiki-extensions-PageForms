<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * Registry of all available form input types and their mappings to
 * Semantic MediaWiki property types.
 *
 * This class owns the five lookup tables that were previously private fields
 * of PFFormPrinter. PFFormPrinter delegates registration and lookup to an
 * instance of this class; the two public hook arrays ($mSemanticTypeHooks and
 * $mInputTypeHooks) remain on PFFormPrinter for backward compatibility with
 * external code that accesses them directly.
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

	/**
	 * Register an input type class and populate the lookup tables for its
	 * supported SMW property types.
	 *
	 * The caller (PFFormPrinter) is responsible for also updating
	 * $mSemanticTypeHooks and $mInputTypeHooks via its own setters, since
	 * those remain on PFFormPrinter for backward compatibility.
	 *
	 * @param string $inputTypeClass Fully qualified class name of the input type.
	 */
	public function register( string $inputTypeClass ): void {
		$inputTypeName = call_user_func( [ $inputTypeClass, 'getName' ] );
		$this->inputTypeClasses[$inputTypeName] = $inputTypeClass;

		$defaultProperties = call_user_func( [ $inputTypeClass, 'getDefaultPropTypes' ] );
		foreach ( $defaultProperties as $propertyType => $additionalValues ) {
			$this->defaultInputForPropType[$propertyType] = $inputTypeName;
		}

		$defaultPropertyLists = call_user_func( [ $inputTypeClass, 'getDefaultPropTypeLists' ] );
		foreach ( $defaultPropertyLists as $propertyType => $additionalValues ) {
			$this->defaultInputForPropTypeList[$propertyType] = $inputTypeName;
		}

		$otherProperties = call_user_func( [ $inputTypeClass, 'getOtherPropTypesHandled' ] );
		foreach ( $otherProperties as $propertyTypeID ) {
			$this->possibleInputsForPropType[$propertyTypeID][] = $inputTypeName;
		}

		$otherPropertyLists = call_user_func( [ $inputTypeClass, 'getOtherPropTypeListsHandled' ] );
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
}
