<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormFieldHtmlBuilder;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/StubFormInput.php';

/**
 * @covers MediaWiki\Extension\PageForms\FormFieldHtmlBuilder
 */
class FormFieldHtmlBuilderTest extends TestCase {

	// -----------------------------------------------------------------------
	// formFieldHTML — hidden field
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlHiddenFieldReturnsHiddenInput(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeHiddenFormField( 'input_foo', 'hello' );
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, 'hello' );

		$this->assertStringContainsString( 'type="hidden"', $html );
		$this->assertStringContainsString( 'name="input_foo"', $html );
		$this->assertStringContainsString( 'value="hello"', $html );
	}

	public function testFormFieldHtmlHiddenFieldWithClassArg(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		$formField = $this->makeHiddenFormField( 'input_bar', 'val', [ 'class' => 'myClass' ] );
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, 'val' );

		$this->assertStringContainsString( 'class="myClass"', $html );
	}

	// -----------------------------------------------------------------------
	// formFieldHTML — input type resolved from inputTypeHooks
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlUsesInputTypeHookClass(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		$formField = $this->makeVisibleFormField( 'text', '', 'input_t' );
		$inputTypeHooks = [ 'text' => [ StubFormInput::class, [] ] ];
		$builder = new FormFieldHtmlBuilder( $inputTypeHooks, [] );

		$html = $builder->formFieldHTML( $formField, 'myval' );

		$this->assertSame( StubFormInput::STUB_HTML, $html );
	}

	// -----------------------------------------------------------------------
	// formFieldHTML — fallback to PFTextInput for list field
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlListFieldGetsSizeDefault(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		$formField = $this->makeListFormField( 'input_list' );
		$builder = new FormFieldHtmlBuilder( [], [] );

		// PFTextInput::getHtmlText() is called; we just verify no exception is thrown
		// and the output is a non-empty string (PFTextInput renders an <input> element).
		$html = $builder->formFieldHTML( $formField, '' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<input', $html );
	}

	// -----------------------------------------------------------------------
	// createFormFieldTranslateTag — translate disabled / not translatable
	// -----------------------------------------------------------------------

	public function testCreateFormFieldTranslateTagDoesNothingWhenNotTranslatable(): void {
		$formField = $this->makeNonTranslatableFormField();
		$builder = new FormFieldHtmlBuilder( [], [] );

		$template = null;
		$tif = null;
		$cur_value = 'original';
		$builder->createFormFieldTranslateTag( $template, $tif, $formField, $cur_value );

		$this->assertSame( 'original', $cur_value );
	}

	public function testCreateFormFieldTranslateTagDoesNothingWhenCurValueIsNull(): void {
		$formField = $this->makeTranslatableFormField();
		$builder = new FormFieldHtmlBuilder( [], [] );

		$template = null;
		$tif = null;
		$cur_value = null;
		$builder->createFormFieldTranslateTag( $template, $tif, $formField, $cur_value );

		$this->assertNull( $cur_value );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeHiddenFormField(
		string $inputName,
		string $curValue,
		array $fieldArgs = []
	): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );

		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'getTemplateField' )->willReturn( $templateField );
		$formField->method( 'isHidden' )->willReturn( true );
		$formField->method( 'getInputName' )->willReturn( $inputName );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => array_key_exists( $key, $fieldArgs )
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $fieldArgs[$key] ?? null
		);
		return $formField;
	}

	private function makeVisibleFormField(
		string $inputType,
		string $propertyType,
		string $inputName
	): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );
		$templateField->method( 'getPropertyType' )->willReturn( $propertyType );
		$templateField->method( 'isList' )->willReturn( false );
		$templateField->method( 'getRegex' )->willReturn( null );

		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'getTemplateField' )->willReturn( $templateField );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getInputType' )->willReturn( $inputType );
		$formField->method( 'getInputName' )->willReturn( $inputName );
		$formField->method( 'isList' )->willReturn( false );
		$formField->method( 'isDisabled' )->willReturn( false );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getFieldArg' )->willReturn( null );
		$formField->method( 'getArgumentsForInputCall' )->willReturn( [] );
		return $formField;
	}

	private function makeListFormField( string $inputName ): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );
		$templateField->method( 'getPropertyType' )->willReturn( '' );
		$templateField->method( 'isList' )->willReturn( false );
		$templateField->method( 'getRegex' )->willReturn( null );

		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'getTemplateField' )->willReturn( $templateField );
		$formField->method( 'isHidden' )->willReturn( false );
		$formField->method( 'getInputType' )->willReturn( '' );
		$formField->method( 'getInputName' )->willReturn( $inputName );
		$formField->method( 'isList' )->willReturn( true );
		$formField->method( 'isDisabled' )->willReturn( false );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getFieldArg' )->willReturn( null );
		$formField->method( 'getArgumentsForInputCall' )->willReturn( [] );
		return $formField;
	}

	private function makeNonTranslatableFormField(): PFFormField {
		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'hasFieldArg' )->willReturn( false );
		$formField->method( 'getFieldArg' )->willReturn( null );
		return $formField;
	}

	private function makeTranslatableFormField(): PFFormField {
		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => $key === 'translatable'
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static fn ( $key ) => $key === 'translatable' ? true : null
		);
		return $formField;
	}
}
