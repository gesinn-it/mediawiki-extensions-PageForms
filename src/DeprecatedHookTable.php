<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use ArrayAccess;

/**
 * What FormPrinter::$mInputTypeHooks and FormPrinter::$mSemanticTypeHooks give out: array access
 * that reads and writes through the InputTypeRegistry instead of handing out its tables.
 *
 * @deprecated use FormPrinter::registerInputType()
 */
class DeprecatedHookTable implements ArrayAccess {

	private InputTypeRegistry $registry;

	private bool $semantic;

	public function __construct( InputTypeRegistry $registry, bool $semantic ) {
		$this->registry = $registry;
		$this->semantic = $semantic;
	}

	/**
	 * @return array The current table, as a copy
	 */
	public function toArray(): array {
		return $this->semantic ? $this->registry->getSemanticTypeHooks() : $this->registry->getInputTypeHooks();
	}

	/**
	 * Set the whole table, as assigning an array to the property did.
	 *
	 * @param array $table
	 */
	public function replaceWith( array $table ): void {
		foreach ( $table as $key => $value ) {
			$this->offsetSet( $key, $value );
		}
	}

	/**
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->toArray()[$offset] );
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->toArray()[$offset] ?? null;
	}

	/**
	 * @param mixed $offset
	 * @param mixed $value For an input type [ class name, default arguments ]; for an SMW property
	 *  type that list of pairs by list flag
	 */
	public function offsetSet( $offset, $value ): void {
		if ( !$this->semantic ) {
			$this->registry->setInputTypeHook( (string)$offset, (string)$value[0], (array)( $value[1] ?? [] ) );
			return;
		}
		foreach ( $value as $isList => $hook ) {
			$this->registry->setSemanticTypeHook(
				(string)$offset, (bool)$isList, (string)$hook[0], (array)( $hook[1] ?? [] )
			);
		}
	}

	/**
	 * @param mixed $offset
	 */
	public function offsetUnset( $offset ): void {
		$this->registry->removeHook( (string)$offset, $this->semantic );
	}
}
