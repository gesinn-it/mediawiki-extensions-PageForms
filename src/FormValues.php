<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use MWException;
use PFUtils;

/**
 * The values of a form, kept apart from the form's structure (FormDefinition): the value of each
 * field per template instance, the template parameters the form does not define, and the free text.
 *
 * Values come from the text of an existing page (FormDefParser::readPageValues()) and from a request
 * or the API (mergeRequest()). Where both have a value, the request wins.
 */
class FormValues {

	/** @var array<string, array<string, mixed>|array<string, array<string, mixed>>> */
	private array $templates = [];

	/** @var array<string, string> Parameter names are the full "_unhandled_<template>_<parameter>" keys */
	private array $unhandled = [];

	private ?string $freeText = null;

	/** @var array<string, list<string>> Names of the mapped fields, by template key */
	private array $mappedFields = [];

	/** @var array<string, array<string, string>> List delimiter of each field, by template key */
	private array $delimiters = [];

	/**
	 * Set the value of a field.
	 *
	 * @param string $templateKey Template name with spaces as underscores
	 * @param string|null $instance Instance key ("0a", "1a", ...) of a multiple-instance template,
	 *   null for a single-instance one
	 * @param string $field
	 * @param mixed $value
	 */
	public function setFieldValue( string $templateKey, ?string $instance, string $field, $value ): void {
		if ( $instance === null ) {
			$this->templates[$templateKey][$field] = $value;
		} else {
			$this->templates[$templateKey][$instance][$field] = $value;
		}
	}

	/**
	 * Register an instance of a multiple-instance template, even if it has no field value.
	 */
	public function addInstance( string $templateKey, string $instance ): void {
		$this->templates[$templateKey][$instance] = [];
	}

	/**
	 * Set a template parameter the form does not define.
	 *
	 * @param string $templateKey
	 * @param string $parameter
	 * @param string $value
	 * @param string|null $instance Instance key ("0a", ...) of a multiple-instance template, so that
	 *   the parameter stays with its instance; null for a single-instance template
	 */
	public function setUnhandled(
		string $templateKey, string $parameter, string $value, ?string $instance = null
	): void {
		if ( $instance === null ) {
			$this->unhandled['_unhandled_' . $templateKey . '_' . urlencode( $parameter )] = $value;
		} else {
			$this->templates[$templateKey][$instance]['_unhandled'][urlencode( $parameter )] = $value;
		}
	}

	public function hasUnhandled( string $templateKey, string $parameter ): bool {
		return array_key_exists( '_unhandled_' . $templateKey . '_' . urlencode( $parameter ), $this->unhandled );
	}

	public function setFreeText( string $freeText ): void {
		$this->freeText = $freeText;
	}

	/**
	 * Note that the form maps the values of these fields to labels, so that a request may carry
	 * the label instead of the value.
	 *
	 * @param string $templateKey
	 * @param list<string> $fields
	 */
	public function setMappedFields( string $templateKey, array $fields ): void {
		if ( $fields !== [] ) {
			$this->mappedFields[$templateKey] = $fields;
		}
	}

	/**
	 * Set the delimiter of the list a field holds, which the "+" and "-" modifiers of a request
	 * need to add to or remove from the list.
	 */
	public function setDelimiter( string $templateKey, string $field, string $delimiter ): void {
		$this->delimiters[$templateKey][$field] = $delimiter;
	}

	/**
	 * The values in the shape the form takes its request in: template key, then field name (or
	 * instance key, then field name), plus the "_unhandled_*" parameters and "pf_free_text".
	 *
	 * @return array<string, mixed>
	 */
	public function toOptions(): array {
		$options = $this->templates + $this->unhandled;
		if ( $this->freeText !== null ) {
			$options['pf_free_text'] = $this->freeText;
		}
		return $options;
	}

	/**
	 * The values with a request laid over them: wherever the request has a value, it wins.
	 *
	 * Before the request is laid over the values:
	 * - The instance of a multiple-instance template may be given as its plain number
	 *   ("Tpl[1][field]"); the values of the page key the instances "0a", "1a", ... Without
	 *   this the request would add an instance instead of changing the one it names.
	 * - A field key ending in "+" or "-" ("Tags+") adds a value to or removes it from the
	 *   list of values the page has for the field; the result takes the place of the request value.
	 * - A request value for a mapped field may be the label the user saw instead of the value,
	 *   so the form is told to map it back (the "map_field" marker the rendered form carries).
	 *
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	public function mergeRequest( array $request ): array {
		$request = $this->numberedInstancesAsPageInstances( $request );
		[ $request, $modifiedFields ] = $this->applyModifiers( $request );
		$request = $this->markMappedFields( $request, $modifiedFields );
		$values = $this->toOptions();
		return PFUtils::arrayMergeRecursiveDistinct( $values, $request );
	}

	/**
	 * Whether the values hold the instances of a multiple-instance template: they are keyed
	 * "0a", "1a", ... (by addInstance() and setFieldValue()).
	 */
	private function isMultiple( string $templateKey ): bool {
		foreach ( array_keys( $this->templates[$templateKey] ?? [] ) as $key ) {
			if ( preg_match( '/^\d+a$/', (string)$key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rename the instances a request names by their plain number ("1") to the key the page's
	 * instances have ("1a"), where the page has such an instance. A number beyond the instances
	 * of the page stays as it is and adds an instance.
	 *
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function numberedInstancesAsPageInstances( array $request ): array {
		foreach ( array_keys( $this->templates ) as $templateKey ) {
			if ( !is_array( $request[$templateKey] ?? null ) || !$this->isMultiple( $templateKey ) ) {
				continue;
			}
			$instances = [];
			foreach ( $request[$templateKey] as $key => $instance ) {
				$pageKey = $key . 'a';
				if ( preg_match( '/^\d+$/', (string)$key ) && isset( $this->templates[$templateKey][$pageKey] ) ) {
					$key = $pageKey;
				}
				$instances[$key] = isset( $instances[$key] ) && is_array( $instance )
					? PFUtils::arrayMergeRecursiveDistinct( $instances[$key], $instance )
					: $instance;
			}
			$request[$templateKey] = $instances;
		}
		return $request;
	}

	/**
	 * Replace the fields of a request that carry a "+" or "-" modifier by the value the modifier
	 * results in, given what the page has for the field.
	 *
	 * Only fields of a template the page values know are looked at; a multiple-instance template
	 * needs the instance in the key ("Tpl[0a][Tags+]").
	 *
	 * @param array<string, mixed> $request
	 * @return array{0: array<string, mixed>, 1: array<string, list<string>>} The request, and the
	 *   names of the modified fields by template key
	 */
	private function applyModifiers( array $request ): array {
		$modified = [];
		$resolver = new FieldValueResolver();
		foreach ( array_keys( $this->templates ) as $templateKey ) {
			if ( !is_array( $request[$templateKey] ?? null ) ) {
				continue;
			}
			// A single-instance template holds its fields directly; a multiple-instance one
			// holds one array of fields per instance.
			$isMultiple = $this->isMultiple( $templateKey );
			if ( $isMultiple ) {
				$this->assertNoModifierWithoutInstance( $templateKey, $request[$templateKey] );
			}
			$levels = $isMultiple ? array_keys( array_filter( $request[$templateKey], 'is_array' ) ) : [ null ];
			foreach ( $levels as $instance ) {
				$fields = $instance === null ? $request[$templateKey] : $request[$templateKey][$instance];
				$pageValues = $instance === null
					? $this->templates[$templateKey]
					: ( $this->templates[$templateKey][$instance] ?? [] );
				foreach ( $fields as $key => $value ) {
					$modifier = is_string( $key ) && strlen( $key ) > 1 ? $key[-1] : '';
					if ( $modifier !== '+' && $modifier !== '-' ) {
						continue;
					}
					$field = substr( $key, 0, -1 );
					$delimiter = $this->delimiters[$templateKey][$field] ?? ',';
					if ( is_array( $value ) ) {
						$value = FormInputValues::getStringFromPassedInArray( $value, $delimiter );
					}
					$pageValue = $pageValues[$field] ?? '';
					$result = $resolver->applyValModifier(
						(string)$value, $modifier, is_string( $pageValue ) ? $pageValue : '', $delimiter
					);
					if ( $instance === null ) {
						unset( $request[$templateKey][$key] );
						$request[$templateKey][$field] = $result;
					} else {
						unset( $request[$templateKey][$instance][$key] );
						$request[$templateKey][$instance][$field] = $result;
					}
					$modified[$templateKey][] = $field;
				}
			}
		}
		return [ $request, $modified ];
	}

	/**
	 * A "+" or "-" modifier needs to know which instance of a multiple-instance template it
	 * applies to. Without one it cannot be applied, and left in the request it would be saved
	 * as an instance of its own, so the request is refused.
	 *
	 * @param string $templateKey
	 * @param array<string, mixed> $templateRequest The request values for the template
	 * @throws MWException
	 */
	private function assertNoModifierWithoutInstance( string $templateKey, array $templateRequest ): void {
		foreach ( $templateRequest as $key => $value ) {
			$isModifier = is_string( $key ) && strlen( $key ) > 1 && in_array( $key[-1], [ '+', '-' ] );
			if ( !is_array( $value ) && $isModifier ) {
				throw new MWException(
					"The modifier '$key' cannot be applied: '" . str_replace( '_', ' ', $templateKey )
					. "' can occur more than once, so the instance has to be named, as in '"
					. $templateKey . '[0a][' . $key . "]'."
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $request
	 * @param array<string, list<string>> $modifiedFields Fields whose request value is the result of
	 *   a modifier; it already holds the values of the page, which are not labels
	 * @return array<string, mixed>
	 */
	private function markMappedFields( array $request, array $modifiedFields = [] ): array {
		foreach ( $this->mappedFields as $templateKey => $fields ) {
			$fields = array_diff( $fields, $modifiedFields[$templateKey] ?? [] );
			if ( !is_array( $request[$templateKey] ?? null ) ) {
				continue;
			}
			// A single-instance template holds its fields directly; a multiple-instance one
			// holds one array of fields per instance.
			$levels = [ &$request[$templateKey] ];
			foreach ( $request[$templateKey] as $key => &$child ) {
				if ( is_array( $child ) && $key !== 'map_field' ) {
					$levels[] = &$child;
				}
			}
			unset( $child );
			foreach ( $levels as &$level ) {
				foreach ( $fields as $field ) {
					if ( array_key_exists( $field, $level ) && !isset( $level['map_field'][$field] ) ) {
						$level['map_field'][$field] = 'true';
					}
				}
			}
			unset( $level );
		}
		return $request;
	}
}
