<?php
/**
 * @file
 * @ingroup PF
 */

use MediaWiki\MediaWikiServices;

/**
 * Adds and handles the 'pfautocomplete' action to the MediaWiki API.
 *
 * @ingroup PF
 *
 * @author Sergey Chernyshev
 * @author Yaron Koren
 */
class PFAutocompleteAPI extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$substr = $params['substr'];
		$namespace = $params['namespace'];
		$property = $params['property'];
		$category = $params['category'];
		$wikidata = $params['wikidata'];
		$concept = $params['concept'];
		$external_url = $params['external_url'];
		$baseprop = $params['baseprop'];
		$basevalue = $params['basevalue'];

		if ( $baseprop === null && ( $substr === null || strlen( $substr ) == 0 ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'substr' ], 'param_substr' );
		}

		global $wgPageFormsUseDisplayTitle;
		$map = false;
		if ( $baseprop !== null ) {
			if ( $property !== null ) {
				$data = $this->getAllValuesForProperty( $property, null, $baseprop, $basevalue );
				$map = is_string( array_key_first( $data ) );
			} else {
				$data = [];
			}
		} elseif ( $property !== null ) {
			$data = $this->getAllValuesForProperty( $property, $substr );
			$map = is_string( array_key_first( $data ) );
		} elseif ( $wikidata !== null ) {
			$data = PFValuesUtils::getAllValuesFromWikidata( urlencode( $wikidata ), $substr );
		} elseif ( $category !== null ) {
			$data = PFValuesUtils::getAllPagesForCategory( $category, 3, $substr );
			$map = $wgPageFormsUseDisplayTitle;
		} elseif ( $concept !== null ) {
			$data = PFValuesUtils::getAllPagesForConcept( $concept, $substr );
			$map = $wgPageFormsUseDisplayTitle;
		} elseif ( $namespace !== null ) {
			$data = PFValuesUtils::getAllPagesForNamespace( $namespace, $substr );
			$map = $wgPageFormsUseDisplayTitle;
		} elseif ( $external_url !== null ) {
			$data = PFValuesUtils::getValuesFromExternalURL( $external_url, $substr );
		} else {
			$data = [];
		}

		// If we got back an error message, exit with that message.
		if ( !is_array( $data ) ) {
			if ( !$data instanceof Message ) {
				$data = ApiMessage::create( new RawMessage( '$1', [ $data ] ), 'unknownerror' );
			}
			$this->dieWithError( $data );
		}

		if ( $map ) {
			$data = PFValuesUtils::disambiguateLabels( $data );
		}

		// Format data as the API requires it - this is not needed
		// for "values from url", where the data is already formatted
		// correctly.
		if ( $external_url === null ) {
			$formattedData = [];
			foreach ( $data as $index => $value ) {
				if ( $map ) {
					$formattedData[] = [ 'title' => $index, 'displaytitle' => $value ];
				} else {
					$formattedData[] = [ 'title' => $value ];
				}
			}
		} else {
			$formattedData = $data;
		}

		// Set top-level elements.
		$result = $this->getResult();
		$result->setIndexedTagName( $formattedData, 'p' );
		$result->addValue( null, $this->getModuleName(), $formattedData );
	}

	protected function getAllowedParams() {
		return [
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'substr' => null,
			'property' => null,
			'category' => null,
			'concept' => null,
			'wikidata' => null,
			'namespace' => null,
			'external_url' => null,
			'baseprop' => null,
			'basevalue' => null,
		];
	}

	protected function getParamDescription() {
		return [
			'substr' => 'Search substring',
			'property' => 'Semantic property for which to search values',
			'category' => 'Category for which to search values',
			'concept' => 'Concept for which to search values',
			'wikidata' => 'Search string for getting values from wikidata',
			'namespace' => 'Namespace for which to search values',
			'external_url' => 'Alias for external URL from which to get values',
			'baseprop' => 'A previous property in the form to check against',
			'basevalue' => 'The value to check for the previous property',
			// 'limit' => 'Limit how many entries to return',
		];
	}

	protected function getDescription() {
		return 'Autocompletion call used by the Page Forms extension (https://www.mediawiki.org/Extension:Page_Forms)';
	}

	protected function getExamples() {
		return [
			'api.php?action=pfautocomplete&substr=te',
			'api.php?action=pfautocomplete&substr=te&property=Has_author',
			'api.php?action=pfautocomplete&substr=te&category=Authors',
		];
	}

	private function getAllValuesForProperty(
		$property_name,
		$substring,
		$basePropertyName = null,
		$baseValue = null
	) {
		global $wgPageFormsCacheAutocompleteValues, $wgPageFormsAutocompleteCacheTimeout;
		global $smwgDefaultStore;

		if ( $smwgDefaultStore == null ) {
			$this->dieWithError( 'Semantic MediaWiki must be installed to query on "property"', 'param_property' );
		}

		$property_name = str_replace( ' ', '_', $property_name );

		// Use cache if allowed
		if ( !$wgPageFormsCacheAutocompleteValues ) {
			return $this->computeAllValuesForProperty( $property_name, $substring, $basePropertyName, $baseValue );
		}

		$cache = PFFormUtils::getFormCache();
		// Remove trailing whitespace to avoid unnecessary database selects
		$cacheKeyString = $property_name . '::' . rtrim( $substring );
		if ( $basePropertyName !== null ) {
			$cacheKeyString .= ',' . $basePropertyName . ',' . $baseValue;
		}
		$cacheKey = $cache->makeKey( 'pf-autocomplete', md5( $cacheKeyString ) );
		return $cache->getWithSetCallback(
			$cacheKey,
			$wgPageFormsAutocompleteCacheTimeout,
			function () use ( $property_name, $substring, $basePropertyName, $baseValue ) {
				return $this->computeAllValuesForProperty( $property_name, $substring, $basePropertyName, $baseValue );
			}
		);
	}

	/**
	 * @param string $property_name
	 * @param string $substring
	 * @param string|null $basePropertyName
	 * @param mixed $baseValue
	 * @return array
	 */
	private function computeAllValuesForProperty(
		$property_name,
		$substring,
		$basePropertyName = null,
		$baseValue = null
	) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;
		global $smwgDefaultStore;

		if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
			$db = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		} else {
			$db = wfGetDB( DB_REPLICA );
		}
		$sqlOptions = [];
		$sqlOptions['LIMIT'] = $wgPageFormsMaxAutocompleteValues;

		$property = SMW\DataValueFactory::getInstance()->newPropertyValueByLabel( $property_name );

		$propertyHasTypePage = ( $property->getPropertyTypeID() == '_wpg' );
		$conditions = [ 'p_ids.smw_title' => $property_name ];

		// Use raw table names (without tableName()) so that $db->select() can
		// properly quote and prefix them when tables are passed as an array.
		if ( $propertyHasTypePage ) {
			$valueField = 'o_ids.smw_title';
			if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
				$idsTableRaw = 'smw_ids';
				$propsTableRaw = 'smw_rels2';
			} else {
				// SMWSQLStore3 - also the backup for SMWSPARQLStore
				$idsTableRaw = 'smw_object_ids';
				$propsTableRaw = 'smw_di_wikipage';
			}
			$tables = [ 'p' => $propsTableRaw, 'p_ids' => $idsTableRaw, 'o_ids' => $idsTableRaw ];
			$joinConds = [
				'p_ids' => [ 'JOIN', 'p.p_id = p_ids.smw_id' ],
				'o_ids' => [ 'JOIN', 'p.o_id = o_ids.smw_id' ],
			];
			if ( $wgPageFormsUseDisplayTitle ) {
				$tables['pg'] = 'page';
				$tables['pp_displaytitle'] = 'page_props';
				$joinConds['pg'] = [ 'JOIN', [
					'pg.page_title = o_ids.smw_title',
					'pg.page_namespace = o_ids.smw_namespace'
				] ];
				$joinConds['pp_displaytitle'] = [ 'LEFT JOIN', [
					'pp_displaytitle.pp_page = pg.page_id',
					"pp_displaytitle.pp_propname = 'displaytitle'"
				] ];
			}
		} else {
			if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
				$valueField = 'p.value_xsd';
				$idsTableRaw = 'smw_ids';
				$propsTableRaw = 'smw_atts2';
			} else {
				// SMWSQLStore3 - also the backup for SMWSPARQLStore
				$valueField = 'p.o_hash';
				$idsTableRaw = 'smw_object_ids';
				$propsTableRaw = 'smw_di_blob';
			}
			$tables = [ 'p' => $propsTableRaw, 'p_ids' => $idsTableRaw ];
			$joinConds = [
				'p_ids' => [ 'JOIN', 'p.p_id = p_ids.smw_id' ],
			];
		}

		if ( $basePropertyName !== null ) {
			$baseProperty = SMW\DataValueFactory::getInstance()->newPropertyValueByLabel( $basePropertyName );
			$basePropertyHasTypePage = ( $baseProperty->getPropertyTypeID() == '_wpg' );

			$basePropertyName = str_replace( ' ', '_', $basePropertyName );
			$conditions['base_p_ids.smw_title'] = $basePropertyName;
			if ( $basePropertyHasTypePage ) {
				if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
					$baseIdsTableRaw = 'smw_ids';
					$basePropsTableRaw = 'smw_rels2';
				} else {
					$baseIdsTableRaw = 'smw_object_ids';
					$basePropsTableRaw = 'smw_di_wikipage';
				}
				$tables['p_base'] = $basePropsTableRaw;
				$tables['base_p_ids'] = $baseIdsTableRaw;
				$tables['base_o_ids'] = $baseIdsTableRaw;
				$joinConds['p_base'] = [ 'JOIN', 'p.s_id = p_base.s_id' ];
				$joinConds['base_p_ids'] = [ 'JOIN', 'p_base.p_id = base_p_ids.smw_id' ];
				$joinConds['base_o_ids'] = [ 'JOIN', 'p_base.o_id = base_o_ids.smw_id' ];
				$baseValue = str_replace( ' ', '_', $baseValue );
				$conditions['base_o_ids.smw_title'] = $baseValue;
			} else {
				if ( $smwgDefaultStore === 'SMWSQLStore2' ) {
					$baseValueField = 'p_base.value_xsd';
					$baseIdsTableRaw = 'smw_ids';
					$basePropsTableRaw = 'smw_atts2';
				} else {
					$baseValueField = 'p_base.o_hash';
					$baseIdsTableRaw = 'smw_object_ids';
					$basePropsTableRaw = 'smw_di_blob';
				}
				$tables['p_base'] = $basePropsTableRaw;
				$tables['base_p_ids'] = $baseIdsTableRaw;
				$joinConds['p_base'] = [ 'JOIN', 'p.s_id = p_base.s_id' ];
				$joinConds['base_p_ids'] = [ 'JOIN', 'p_base.p_id = base_p_ids.smw_id' ];
				$conditions[$baseValueField] = $baseValue;
			}
		}

		if ( $substring !== null ) {
			// "Page" type property values are stored differently
			// in the DB, i.e. underlines instead of spaces.
			if ( $wgPageFormsUseDisplayTitle && $propertyHasTypePage ) {
				// Search in displaytitle when set, fall back to internal title.
				$conditions[] =
					'((pp_displaytitle.pp_value IS NULL OR pp_displaytitle.pp_value = \'\') AND (' .
					PFValuesUtils::getSQLConditionForAutocompleteInColumn( $valueField, $substring, true ) .
					')) OR (' .
					PFValuesUtils::getSQLConditionForAutocompleteInColumn( 'pp_displaytitle.pp_value', $substring, false ) .
					')';
			} else {
				$conditions[] = PFValuesUtils::getSQLConditionForAutocompleteInColumn( $valueField, $substring, $propertyHasTypePage );
			}
		}

		$sqlOptions['ORDER BY'] = $valueField;
		if ( $propertyHasTypePage && $wgPageFormsUseDisplayTitle ) {
			$sqlOptions[] = 'DISTINCT';
			$res = $db->select( $tables,
				[ $valueField, 'o_ids.smw_namespace', 'pp_displaytitle.pp_value' ],
				$conditions, __METHOD__, $sqlOptions, $joinConds );
			$values = [];
			while ( $row = $res->fetchRow() ) {
				$smwTitle = str_replace( '_', ' ', $row[0] );
				$smwNamespace = (int)$row[1];
				if ( $smwNamespace === NS_MAIN ) {
					$prefixedTitle = $smwTitle;
				} else {
					$nsText = MediaWikiServices::getInstance()->getNamespaceInfo()
						->getCanonicalName( $smwNamespace );
					$prefixedTitle = $nsText . ':' . $smwTitle;
				}
				$displayTitle = PFValuesUtils::resolveDisplayTitle( $row[2], $prefixedTitle );
				$values[$prefixedTitle] = $displayTitle;
			}
			$res->free();
			return $values;
		}

		$res = $db->select( $tables, "DISTINCT $valueField",
			$conditions, __METHOD__, $sqlOptions, $joinConds );

		$values = [];
		while ( $row = $res->fetchRow() ) {
			$values[] = str_replace( '_', ' ', $row[0] );
		}
		$res->free();
		$values = self::shiftExactMatch( $substring, $values );
		return $values;
	}

	/**
	 * Move the exact match to the top for better autocompletion
	 * @param string $substring
	 * @param array $values
	 * @return array $values
	 */
	public static function shiftExactMatch( $substring, $values ) {
		$firstMatchIdx = array_search( $substring, $values );
		if ( $firstMatchIdx !== false && $firstMatchIdx !== 0 ) {
			unset( $values[ $firstMatchIdx ] );
			array_unshift( $values, $substring );
		}
		return $values;
	}

}
