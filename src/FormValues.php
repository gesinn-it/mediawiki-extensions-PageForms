<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

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

	public function setUnhandled( string $templateKey, string $parameter, string $value ): void {
		$this->unhandled['_unhandled_' . $templateKey . '_' . urlencode( $parameter )] = $value;
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
	 * A request value for a mapped field may be the label the user saw instead of the value, so the
	 * form is told to map it back (the "map_field" marker the rendered form carries).
	 *
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	public function mergeRequest( array $request ): array {
		$request = $this->markMappedFields( $request );
		$values = $this->toOptions();
		return PFUtils::arrayMergeRecursiveDistinct( $values, $request );
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<string, mixed>
	 */
	private function markMappedFields( array $request ): array {
		foreach ( $this->mappedFields as $templateKey => $fields ) {
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
