<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use PFUtils;
use SMW\DataValueFactory;
use SMW\DataValues\PropertyValue;
use SMWDIUri;
use Title;

/**
 * The values of a Semantic MediaWiki property, as the values of a field.
 *
 * @ingroup PF
 */
class PropertyValues {

	/**
	 * Helper function to handle getPropertyValues().
	 *
	 * @param \SMW\Store $store
	 * @param Title|null $subject
	 * @param string $propID
	 * @param \SMW\RequestOptions|null $requestOptions
	 * @return array
	 */
	public static function forSubject( $store, ?Title $subject, $propID, $requestOptions = null ) {
		// If SMW is not installed, exit out.
		if ( !class_exists( '\SMW\DIWikiPage' ) ) {
			return [];
		}
		if ( $subject === null ) {
			$page = null;
		} else {
			$page = \SMW\DIWikiPage::newFromTitle( $subject );
		}
		$property = \SMW\DIProperty::newFromUserLabel( $propID );
		$res = $store->getPropertyValues( $page, $property, $requestOptions );
		$values = [];
		foreach ( $res as $value ) {
			if ( $value instanceof SMWDIUri ) {
				$values[] = $value->getURI();
			} elseif ( $value instanceof \SMW\DIWikiPage ) {
				// Use the canonical (English) namespace name, matching how
				// MediaWiki serializes internal links in wikitext, regardless
				// of content language. The localized name (e.g. "Kategorie" on
				// a German wiki) would not match the "Category:"-prefixed value
				// actually stored on the page, breaking display-title mapping.
				$values[] = PageValue::newFromDIWikiPage( $value )->getCanonicalString();
			} else {
				// getSortKey() seems to return the correct
				// value for all the other data types.
				$values[] = str_replace( '_', ' ', $value->getSortKey() );
			}
		}
		return $values;
	}

	/**
	 * This function, unlike the others, doesn't take in a substring
	 * because it uses the SMW data store, which can't perform
	 * case-insensitive queries; for queries with a substring, the
	 * function PFAutocompleteAPI::getAllValuesForProperty() exists.
	 *
	 * @param string $property_name
	 * @param \SMW\Store|null $store
	 * @param int|null $maxValues
	 * @param bool|null $useDisplayTitle
	 * @return array
	 */
	public static function all(
		$property_name, $store = null, $maxValues = null, $useDisplayTitle = null
	) {
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$store ??= PFUtils::getSMWStore();
		if ( $store == null ) {
			return [];
		}
		$maxValues ??= $wgPageFormsMaxAutocompleteValues;
		$useDisplayTitle ??= $wgPageFormsUseDisplayTitle;

		$requestoptions = new \SMW\RequestOptions();
		$requestoptions->limit = (int)$maxValues;
		$values = self::forSubject( $store, null, $property_name, $requestoptions );
		sort( $values );

		if ( !$useDisplayTitle || $values === [] ) {
			return $values;
		}

		$property = DataValueFactory::getInstance()->newPropertyValueByLabel( $property_name );
		if ( !( $property instanceof PropertyValue ) || $property->getPropertyTypeID() !== '_wpg' ) {
			return $values;
		}

		return DisplayTitles::forPages( $values );
	}
}
