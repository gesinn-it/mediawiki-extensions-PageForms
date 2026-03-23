<?php

/**
 * Integration tests for PFFormField value-mapping methods that require a real
 * wiki DB and parser (setValuesWithMappingTemplate).
 *
 * Separated from PFFormFieldTest (which extends PHPUnit\Framework\TestCase) so
 * that DB access is contained to this file and does not slow down the pure
 * unit tests.
 *
 * @covers PFFormField
 *
 * @group PF
 * @group Database
 * @group medium
 */
class PFFormFieldMappingTest extends MediaWikiIntegrationTestCase {

	// -------------------------------------------------------------------------
	// setValuesWithMappingTemplate
	// -------------------------------------------------------------------------

	/**
	 * When the mapping template exists and returns a non-empty label for a
	 * value, mPossibleValues must map value → label.
	 *
	 * The template always returns the fixed string 'PFTestLabel' regardless of
	 * input (no ParserFunctions/switch available in the CI container). This is
	 * sufficient to verify that setValuesWithMappingTemplate() correctly stores
	 * the template's return value as the label for each key.
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 */
	public function testSetValuesWithMappingTemplateTranslatesKnownValues(): void {
		$templateName = 'PFTestMappingTpl01';
		$this->editPage(
			"Template:$templateName",
			'PFTestLabel'
		);

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE', 'FR' ] );
		$field->setValuesWithMappingTemplate();

		// Both values receive the same label returned by the template.
		$this->assertSame(
			[ 'DE' => 'PFTestLabel', 'FR' => 'PFTestLabel' ],
			$field->getPossibleValues()
		);
	}

	/**
	 * When the mapping template exists but returns an empty string for a value,
	 * the value is used as its own label (identity fallback).
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 */
	public function testSetValuesWithMappingTemplateEmptyLabelFallsBackToValue(): void {
		$templateName = 'PFTestMappingTplEmpty01';
		$this->editPage(
			"Template:$templateName",
			// Template content is empty — returns empty string for all inputs.
			''
		);

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE' ] );
		$field->setValuesWithMappingTemplate();

		$this->assertSame(
			[ 'DE' => 'DE' ],
			$field->getPossibleValues()
		);
	}

	/**
	 * When the mapping template does not exist, every value must be mapped to
	 * itself (identity fallback — no parse call is made).
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 */
	public function testSetValuesWithMappingTemplateNonexistentTemplateUsesIdentity(): void {
		$templateName = 'PFTestMappingTplNonexistent01';
		// Deliberately NOT creating the template page.

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE', 'FR' ] );
		$field->setValuesWithMappingTemplate();

		$this->assertSame(
			[ 'DE' => 'DE', 'FR' => 'FR' ],
			$field->getPossibleValues()
		);
	}

	/**
	 * Full round-trip: valueStringToLabels after setValuesWithMappingTemplate
	 * must translate a storage key to its display label.
	 *
	 * This is the integration counterpart to the pure-unit tests in
	 * PFFormFieldTest — it proves the full mapping pipeline works end-to-end
	 * with a real template in the wiki.
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 * @covers PFFormField::valueStringToLabels
	 */
	public function testSetValuesWithMappingTemplateRoundTripValueToLabel(): void {
		$templateName = 'PFTestMappingTplRoundTrip01';
		$this->editPage(
			"Template:$templateName",
			'PFTestLabel'
		);

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE' ] );
		$field->setValuesWithMappingTemplate();

		// After mapping, 'DE' → 'PFTestLabel'. valueStringToLabels must return the label.
		$this->assertSame( 'PFTestLabel', $field->valueStringToLabels( 'DE', null ) );
	}

	/**
	 * Full round-trip: labelToValue after setValuesWithMappingTemplate
	 * must translate a display label back to its storage key.
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 * @covers PFFormField::labelToValue
	 */
	public function testSetValuesWithMappingTemplateRoundTripLabelToValue(): void {
		$templateName = 'PFTestMappingTplRoundTripRev01';
		$this->editPage(
			"Template:$templateName",
			'PFTestLabel'
		);

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE' ] );
		$field->setValuesWithMappingTemplate();

		// After mapping DE → PFTestLabel; labelToValue('PFTestLabel') must return 'DE'.
		$this->assertSame( 'DE', $field->labelToValue( 'PFTestLabel' ) );
	}

	/**
	 * Autoedit preload scenario: a raw storage key arriving via labelToValue
	 * (as if read directly from wikitext) must pass through unchanged, even
	 * after the mapping template has been applied.
	 *
	 * This proves that bypassing the HTML round-trip (reading 'DE' from wikitext
	 * instead of the label from HTML) will NOT corrupt the save path.
	 *
	 * @covers PFFormField::setValuesWithMappingTemplate
	 * @covers PFFormField::labelToValue
	 */
	public function testSetValuesWithMappingTemplateRawKeyPassesThroughLabelToValue(): void {
		$templateName = 'PFTestMappingTplRawKey01';
		$this->editPage(
			"Template:$templateName",
			'PFTestLabel'
		);

		$field = $this->makeFieldForMappingTemplate( $templateName, [ 'DE' ] );
		$field->setValuesWithMappingTemplate();

		// After mapping: ['DE' => 'PFTestLabel'].
		// 'DE' is a storage key, not a label. array_search('DE', ['DE' => 'PFTestLabel'])
		// finds nothing → identity fallback → 'DE' returned unchanged.
		$this->assertSame( 'DE', $field->labelToValue( 'DE' ) );
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Build a PFFormField configured for setValuesWithMappingTemplate.
	 *
	 * @param string $templateName Name of the mapping template (without Template: prefix)
	 * @param string[] $values Numerically-indexed list of storage values
	 * @return PFFormField
	 */
	private function makeFieldForMappingTemplate( string $templateName, array $values ): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );
		$templateField->method( 'getPossibleValues' )->willReturn( null );
		$templateField->method( 'getDelimiter' )->willReturn( '' );

		$field = PFFormField::create( $templateField );
		$field->setFieldArg( 'mapping template', $templateName );

		$ref = new ReflectionClass( PFFormField::class );
		$prop = $ref->getProperty( 'mPossibleValues' );
		$prop->setAccessible( true );
		$prop->setValue( $field, $values );

		return $field;
	}
}
