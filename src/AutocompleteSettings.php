<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;

/**
 * What a form input that autocompletes needs of the arguments of its field: where the values come
 * from, whether they are loaded into the form or fetched while the user types, and the settings
 * string that the JavaScript uses to find the values.
 *
 * @ingroup PF
 */
class AutocompleteSettings {

	/**
	 * Get all autocomplete-related values, plus delimiter value
	 * (it's needed also for the 'uploadable' link, if there is one).
	 *
	 * @param array $fieldArgs
	 * @param bool $isList
	 * @param bool $forceRemote Skip the count check and always return the remote data type
	 * @return array{0: string|null, 1: string|null, 2: string|null} The settings string, the remote
	 *   data type (null if the values are local) and the delimiter (null if the field is no list)
	 */
	public static function forField( array $fieldArgs, bool $isList, bool $forceRemote = false ): array {
		[ $autocompleteFieldType, $autocompletionSource ] = self::typeAndSource( $fieldArgs );
		$autocompleteSettings = $autocompletionSource;
		if ( $isList ) {
			$autocompleteSettings .= ',list';
			if ( array_key_exists( 'delimiter', $fieldArgs ) ) {
				$delimiter = $fieldArgs['delimiter'];
				$autocompleteSettings .= ',' . $delimiter;
			} else {
				$delimiter = ',';
			}
		} else {
			$delimiter = null;
		}

		$remoteDataType = self::remoteDataType(
			$autocompleteFieldType, $autocompletionSource, $fieldArgs, $autocompleteSettings, $forceRemote
		);
		return [ $autocompleteSettings, $remoteDataType, $delimiter ];
	}

	/**
	 * @param array $fieldArgs
	 * @return array{0: string|null, 1: string|null} The type of the source ('property', 'category',
	 *   'concept', 'namespace', 'external_url', 'wikidata' or 'values') and the name of the source
	 */
	public static function typeAndSource( array $fieldArgs ): array {
		global $wgCapitalLinks;

		if ( array_key_exists( 'values from property', $fieldArgs ) ) {
			$autocompletionSource = $fieldArgs['values from property'];
			$autocompleteFieldType = 'property';
		} elseif ( array_key_exists( 'values from category', $fieldArgs ) ) {
			$autocompleteFieldType = 'category';
			$autocompletionSource = $fieldArgs['values from category'];
		} elseif ( array_key_exists( 'values from concept', $fieldArgs ) ) {
			$autocompleteFieldType = 'concept';
			$autocompletionSource = $fieldArgs['values from concept'];
		} elseif ( array_key_exists( 'values from namespace', $fieldArgs ) ) {
			$autocompleteFieldType = 'namespace';
			$autocompletionSource = $fieldArgs['values from namespace'];
		} elseif ( array_key_exists( 'values from url', $fieldArgs ) ) {
			$autocompleteFieldType = 'external_url';
			$autocompletionSource = $fieldArgs['values from url'];
		} elseif ( array_key_exists( 'values from wikidata', $fieldArgs ) ) {
			$autocompleteFieldType = 'wikidata';
			$autocompletionSource = $fieldArgs['values from wikidata'];
		} elseif ( array_key_exists( 'values', $fieldArgs ) ) {
			$counters = FormCounters::current();
			$autocompleteFieldType = 'values';
			$autocompletionSource = "values-$counters->fieldNum";
		} elseif ( array_key_exists( 'autocomplete field type', $fieldArgs ) ) {
			$autocompleteFieldType = $fieldArgs['autocomplete field type'];
			$autocompletionSource = $fieldArgs['autocompletion source'];
		} elseif ( array_key_exists( 'semantic_property', $fieldArgs ) ) {
			$autocompletionSource = $fieldArgs['semantic_property'];
			$autocompleteFieldType = 'property';
		} else {
			$autocompleteFieldType = null;
			$autocompletionSource = null;
		}

		if ( $autocompletionSource !== null && $wgCapitalLinks &&
			$autocompleteFieldType != 'external_url' ) {
			$autocompletionSource = PFUtils::getContLang()->ucfirst( $autocompletionSource );
		}

		return [ $autocompleteFieldType, $autocompletionSource ];
	}

	/**
	 * Decide whether the values are fetched while the user types, and if not, load them into
	 * $wgPageFormsAutocompleteValues.
	 *
	 * @param string|null $autocompleteFieldType
	 * @param string|null $autocompletionSource
	 * @param array $fieldArgs
	 * @param string|null $autocompleteSettings The key of the loaded values
	 * @param bool $forceRemote
	 * @return string|null The type of the source if the values are remote, null if there are none or
	 *   they are loaded
	 */
	public static function remoteDataType(
		$autocompleteFieldType, $autocompletionSource, array $fieldArgs, $autocompleteSettings,
		bool $forceRemote = false
	) {
		global $wgPageFormsAutocompleteValues;

		if ( $autocompleteFieldType == 'external_url' || $autocompleteFieldType == 'wikidata' ) {
			// Autocompletion from URL is always done remotely.
			return $autocompleteFieldType;
		}
		if ( $autocompletionSource == '' ) {
			// No autocompletion.
			return null;
		}
		if ( self::isRemoteByCount( $autocompleteFieldType, $autocompletionSource, $fieldArgs, $forceRemote ) ) {
			return $autocompleteFieldType;
		}

		$autocompleteValues = self::maybeDisambiguateLabels(
			self::loadValues( $autocompleteFieldType, $autocompletionSource, $fieldArgs ),
			$autocompleteFieldType
		);

		global $wgPageFormsMaxLocalAutocompleteValues;
		if ( count( $autocompleteValues ) > $wgPageFormsMaxLocalAutocompleteValues &&
			$autocompleteFieldType != 'values' &&
			!array_key_exists( 'values dependent on', $fieldArgs ) &&
			!array_key_exists( 'mapping template', $fieldArgs ) &&
			!array_key_exists( 'mapping property', $fieldArgs )
		) {
			return $autocompleteFieldType;
		} else {
			$wgPageFormsAutocompleteValues[$autocompleteSettings] = $autocompleteValues;
			return null;
		}
	}

	/**
	 * For wiki-sourced types, do a cheap MODE_COUNT query to decide between
	 * local and remote autocompletion. If the count is at or below the local
	 * threshold, the values are pre-loaded into wgPageFormsAutocompleteValues so the
	 * dropdown button shows all values without the user having to type.
	 * When SMW is unavailable or the count cannot be determined, fall back to
	 * always-remote (safe, same as the previous unconditional behaviour).
	 *
	 * Exceptions that skip the count and stay remote:
	 * - $forceRemote: caller requires remote (e.g. FormLink target-input where
	 *   no values are pre-loaded into the form).
	 * - possible_values is a string-keyed (canonical→display) map: the remote
	 *   type must be preserved so PF_ComboBoxInput writes the canonical title
	 *   into the option's value attribute for DisplayTitle support.
	 *
	 * @param string|null $autocompleteFieldType
	 * @param string $autocompletionSource
	 * @param array $fieldArgs
	 * @param bool $forceRemote
	 * @return bool
	 */
	private static function isRemoteByCount(
		$autocompleteFieldType, $autocompletionSource, array $fieldArgs, bool $forceRemote
	): bool {
		global $wgPageFormsMaxLocalAutocompleteValues;

		$countableTypes = [ 'concept', 'category', 'namespace', 'property' ];
		if ( !in_array( $autocompleteFieldType, $countableTypes, true ) ) {
			return false;
		}
		// Stay remote when a mapping template/property is active: PF_ComboBoxInput
		// encodes the canonical title in the <option value> attribute so the JS can
		// bootstrap its DisplayTitle↔canonical maps from the DOM without an AJAX
		// round-trip.  wgPageFormsUseDisplayTitle also produces a string-keyed
		// possible_values map, but _fetchStaticValues() handles that correctly, so
		// we do NOT force remote for that case.
		$hasMappingArg = array_key_exists( 'mapping template', $fieldArgs )
			|| array_key_exists( 'mapping property', $fieldArgs );
		if ( $forceRemote || $hasMappingArg ) {
			return true;
		}
		$count = AutocompleteSourceCount::get( $autocompleteFieldType, $autocompletionSource );
		return $count === null || $count > $wgPageFormsMaxLocalAutocompleteValues;
	}

	/**
	 * @param string|null $autocompleteFieldType
	 * @param string $autocompletionSource
	 * @param array $fieldArgs
	 * @return array
	 */
	private static function loadValues( $autocompleteFieldType, $autocompletionSource, array $fieldArgs ): array {
		if ( array_key_exists( 'possible_values', $fieldArgs ) && $fieldArgs['possible_values'] !== [] ) {
			return $fieldArgs['possible_values'];
		}
		if ( $autocompleteFieldType == 'values' ) {
			return explode( ',', $fieldArgs['values'] );
		}
		return ValuesUtils::getAutocompleteValues( $autocompletionSource, $autocompleteFieldType );
	}

	/**
	 * For local autocomplete values, disambiguate duplicate displaytitle labels
	 * for sources that map page titles to display titles.
	 *
	 * @param array $autocompleteValues
	 * @param string|null $autocompleteFieldType
	 * @return array
	 */
	public static function maybeDisambiguateLabels( array $autocompleteValues, $autocompleteFieldType ): array {
		global $wgPageFormsUseDisplayTitle;

		if ( !$wgPageFormsUseDisplayTitle ) {
			return $autocompleteValues;
		}

		if ( !in_array( $autocompleteFieldType, [ 'category', 'concept', 'namespace', 'property' ], true ) ) {
			return $autocompleteValues;
		}

		return ValuesUtils::disambiguateLabels( $autocompleteValues );
	}
}
