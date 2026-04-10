<?php

use MediaWiki\Extension\PageForms\InputTypeRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for InputTypeRegistry.
 *
 * Uses PFCheckboxInput (name='checkbox', default prop type '_boo') and
 * PFTextInput (name='text', no default prop types) as real registered types.
 *
 * No MediaWiki database bootstrap required — these are plain unit tests.
 *
 * @group PF
 * @covers MediaWiki\Extension\PageForms\InputTypeRegistry
 */
class InputTypeRegistryTest extends TestCase {

	private InputTypeRegistry $registry;

	protected function setUp(): void {
		parent::setUp();
		$this->registry = new InputTypeRegistry();
	}

	// -----------------------------------------------------------------------
	// getAllTypeNames
	// -----------------------------------------------------------------------

	public function testEmptyRegistryHasNoTypes() {
		$this->assertSame( [], $this->registry->getAllTypeNames() );
	}

	public function testRegisteredTypeAppearsInAllTypeNames() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->assertContains( 'checkbox', $this->registry->getAllTypeNames() );
	}

	public function testMultipleTypesAllAppear() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->registry->register( 'PFTextInput' );
		$names = $this->registry->getAllTypeNames();
		$this->assertContains( 'checkbox', $names );
		$this->assertContains( 'text', $names );
	}

	// -----------------------------------------------------------------------
	// getClass
	// -----------------------------------------------------------------------

	public function testGetClassReturnsNullForUnknownType() {
		$this->assertNull( $this->registry->getClass( 'nonexistent' ) );
	}

	public function testGetClassReturnsCorrectClassName() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->assertSame( 'PFCheckboxInput', $this->registry->getClass( 'checkbox' ) );
	}

	// -----------------------------------------------------------------------
	// getDefaultInputType — scalar
	// -----------------------------------------------------------------------

	public function testGetDefaultInputTypeScalarReturnsNullForUnregistered() {
		$this->assertNull( $this->registry->getDefaultInputType( false, '_boo' ) );
	}

	public function testGetDefaultInputTypeScalarReturnsMappedType() {
		$this->registry->register( 'PFCheckboxInput' );
		// PFCheckboxInput::getDefaultPropTypes() returns ['_boo' => []]
		$this->assertSame( 'checkbox', $this->registry->getDefaultInputType( false, '_boo' ) );
	}

	// -----------------------------------------------------------------------
	// getDefaultInputType — list
	// -----------------------------------------------------------------------

	public function testGetDefaultInputTypeListReturnsNullWhenNoneRegistered() {
		$this->registry->register( 'PFCheckboxInput' );
		// PFCheckboxInput does not register any default list types
		$this->assertNull( $this->registry->getDefaultInputType( true, '_boo' ) );
	}

	// -----------------------------------------------------------------------
	// getPossibleInputTypes
	// -----------------------------------------------------------------------

	public function testGetPossibleInputTypesReturnsEmptyArrayByDefault() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->assertSame( [], $this->registry->getPossibleInputTypes( false, '_str' ) );
	}

	public function testGetPossibleInputTypesListReturnsEmptyArrayByDefault() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->assertSame( [], $this->registry->getPossibleInputTypes( true, '_str' ) );
	}

	// -----------------------------------------------------------------------
	// Idempotency — registering the same class twice must not duplicate
	// -----------------------------------------------------------------------

	public function testRegisteringSameClassTwiceDoesNotDuplicateTypeNames() {
		$this->registry->register( 'PFCheckboxInput' );
		$this->registry->register( 'PFCheckboxInput' );
		$checkboxCount = count( array_filter(
			$this->registry->getAllTypeNames(),
			static fn ( $n ) => $n === 'checkbox'
		) );
		$this->assertSame( 1, $checkboxCount );
	}
}
