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

		// FormFieldHtmlBuilder sets $other_args['size'] = 100 for list fields with
		// no explicit size (src/FormFieldHtmlBuilder.php:76-85), which PFTextInput::getHtmlText()
		// renders as a size="100" attribute.
		$html = $builder->formFieldHTML( $formField, '' );

		$this->assertIsString( $html );
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'size="100"', $html );
	}

	// -----------------------------------------------------------------------
	// formFieldHTML — semantic-type hook (property-type dispatch)
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlUsesSemanticTypeHookClass(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		// No inputType hook, but a property-type hook for '_str'
		$formField = $this->makeVisibleFormField( '', '_str', 'input_s' );
		$semanticTypeHooks = [ '_str' => [ false => [ StubFormInput::class, [] ] ] ];
		$builder = new FormFieldHtmlBuilder( [], $semanticTypeHooks );

		$html = $builder->formFieldHTML( $formField, 'smwval' );

		$this->assertSame( StubFormInput::STUB_HTML, $html );
	}

	public function testFormFieldHtmlFallsBackToPFTextInputWhenNoHookMatches(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 1;

		// Neither inputType nor semanticType hook → PFTextInput
		$formField = $this->makeVisibleFormField( '', '', 'input_f' );
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, '' );

		$this->assertStringContainsString( '<input', $html );
	}

	// -----------------------------------------------------------------------
	// formFieldHTML — regex-wrapped input (PFRegExpInput)
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlWrapsInputInRegExpInputWhenRegexDefined(): void {
		global $wgPageFormsFieldNum, $wgPageFormsFormPrinter;
		$wgPageFormsFieldNum = 1;

		// PFRegExpInput constructor reads $wgPageFormsFormPrinter->getAllInputTypes()
		// and ->getInputType() when resolving the base type — stub both.
		$wgPageFormsFormPrinter = new class {
			public function getAllInputTypes(): array {
				return [ 'text' ];
			}

			public function getInputType( string $name ): string {
				return StubFormInput::class;
			}
		};

		$formField = $this->makeVisibleFormFieldWithRegex( 'text', 'input_re', '/^[a-z]+$/' );
		$inputTypeHooks = [ 'text' => [ StubFormInput::class, [] ] ];
		$builder = new FormFieldHtmlBuilder( $inputTypeHooks, [] );

		$html = $builder->formFieldHTML( $formField, 'abc' );

		// PFRegExpInput delegates getHtmlText() to its base input (StubFormInput), then
		// injects a data-regexp attribute into the base input's rendered <input> tag.
		$this->assertStringContainsString( 'data-regexp="^[a-z]+$"', $html );
		$this->assertStringContainsString( 'class="stub"', $html );

		$wgPageFormsFormPrinter = null;
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

	public function testCreateFormFieldTranslateTagStripsTranslateTagsViaRegex(): void {
		// Translate extension disabled (default in tests): early-return NOT taken.
		// cur_value wrapped in <translate>...</translate> on a single line → preg_match branch.
		$formField = $this->makeTranslatableFormField();
		$builder = new FormFieldHtmlBuilder( [], [] );

		$template = null;
		$tif = null;
		$cur_value = '<translate>Hello world</translate>';
		$builder->createFormFieldTranslateTag( $template, $tif, $formField, $cur_value );

		$this->assertSame( 'Hello world', $cur_value );
	}

	public function testCreateFormFieldTranslateTagStripsTranslateTagsViaSubstr(): void {
		// Multi-line content: preg_match with $ does not match \n before </translate>,
		// so the substr fallback branch is exercised.
		$formField = $this->makeTranslatableFormField();
		$builder = new FormFieldHtmlBuilder( [], [] );

		$template = null;
		$tif = null;
		$inner = "Hello\nworld";
		$cur_value = '<translate>' . $inner . '</translate>';
		$builder->createFormFieldTranslateTag( $template, $tif, $formField, $cur_value );

		$this->assertSame( $inner, $cur_value );
	}

	public function testCreateFormFieldTranslateTagExtractsTranslateNumberTag(): void {
		// cur_value starts with <!--T:X --> — the tag is moved to a field arg.
		$formField = $this->makeCapturingTranslatableFormField();
		$builder = new FormFieldHtmlBuilder( [], [] );

		$template = null;
		$tif = null;
		$cur_value = '<!--T:42--> Some content';
		$builder->createFormFieldTranslateTag( $template, $tif, $formField, $cur_value );

		// The tag should have been stripped from $cur_value.
		$this->assertStringNotContainsString( '<!--T:', $cur_value );
		// The tag should have been stored as a field arg.
		$this->assertNotNull( $formField->getFieldArg( 'translate_number_tag' ) );
	}

	// -----------------------------------------------------------------------
	// addTranslatableInput (private, exercised via formFieldHTML)
	// -----------------------------------------------------------------------

	public function testFormFieldHtmlEmitsHiddenTranslateTagInputForBracketedInputName(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		// A hidden field whose field args carry 'translatable' + 'translate_number_tag'.
		// The input name uses bracket notation so the name-rewrite branch (line 168) fires.
		$tag = '<!--T:7--> ';
		$formField = $this->makeTranslatableHiddenFormField(
			'MyTemplate[MyField]',
			'some value',
			$tag
		);
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, 'some value' );

		$this->assertStringContainsString( 'MyTemplate[MyField_translate_number_tag]', $html );
		$this->assertStringContainsString( htmlspecialchars( $tag ), $html );
	}

	public function testFormFieldHtmlEmitsHiddenTranslateTagInputForPlainInputName(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		// Plain (non-bracketed) input name → the else-branch appends _translate_number_tag.
		$tag = '<!--T:3--> ';
		$formField = $this->makeTranslatableHiddenFormField(
			'plain_field',
			'val',
			$tag
		);
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, 'val' );

		$this->assertStringContainsString( 'plain_field_translate_number_tag', $html );
		$this->assertStringContainsString( htmlspecialchars( $tag ), $html );
	}

	public function testFormFieldHtmlEscapesTranslateTagContainingSingleQuote(): void {
		global $wgPageFormsFieldNum;
		$wgPageFormsFieldNum = 0;

		// Malicious translate tag containing a single quote and an onmouseover handler.
		// If the hidden input were built via raw string concatenation with '...' attribute
		// delimiters, this value would break out of the value attribute and inject markup.
		$tag = "<!--T:1'onmouseover='alert(1)-->";
		$formField = $this->makeTranslatableHiddenFormField(
			'plain_field',
			'val',
			$tag
		);
		$builder = new FormFieldHtmlBuilder( [], [] );

		$html = $builder->formFieldHTML( $formField, 'val' );

		$this->assertStringNotContainsString( "onmouseover='alert(1)", $html );
		$this->assertStringContainsString( '&#039;', $html );
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

	/**
	 * Returns a translatable PFFormField mock that accepts setFieldArg() calls
	 * and exposes the stored value via getFieldArg().
	 */
	private function makeCapturingTranslatableFormField(): PFFormField {
		$stored = [];
		$formField = $this->createMock( PFFormField::class );
		$formField->method( 'hasFieldArg' )->willReturnCallback(
			static fn ( $key ) => $key === 'translatable' || array_key_exists( $key, $stored )
		);
		$formField->method( 'getFieldArg' )->willReturnCallback(
			static function ( $key ) use ( &$stored ) {
				if ( $key === 'translatable' ) {
					return true;
				}
				return $stored[$key] ?? null;
			}
		);
		$formField->method( 'setFieldArg' )->willReturnCallback(
			static function ( $key, $value ) use ( &$stored ) {
				$stored[$key] = $value;
			}
		);
		return $formField;
	}

	private function makeVisibleFormFieldWithRegex(
		string $inputType,
		string $inputName,
		string $regex
	): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );
		$templateField->method( 'getPropertyType' )->willReturn( '' );
		$templateField->method( 'isList' )->willReturn( false );
		$templateField->method( 'getRegex' )->willReturn( $regex );

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

	/**
	 * Hidden field mock with 'translatable' and 'translate_number_tag' field args set,
	 * so that addTranslatableInput() emits the hidden tag input.
	 */
	private function makeTranslatableHiddenFormField(
		string $inputName,
		string $curValue,
		string $translateTag
	): PFFormField {
		$templateField = $this->createMock( PFTemplateField::class );

		$fieldArgs = [
			'translatable' => true,
			'translate_number_tag' => $translateTag,
		];

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
}
