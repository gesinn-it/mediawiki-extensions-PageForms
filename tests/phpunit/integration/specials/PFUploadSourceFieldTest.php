<?php

declare( strict_types=1 );

/**
 * @covers \PFUploadSourceField
 * @group Database
 */
class PFUploadSourceFieldTest extends MediaWikiIntegrationTestCase {

	private function makeField( array $extraParams = [] ): PFUploadSourceField {
		$form = HTMLForm::factory( 'ooui', [
			'UploadFile' => array_merge( [
				'class' => 'PFUploadSourceField',
				'label' => 'Source file',
				'upload-type' => 'File',
			], $extraParams ),
		], RequestContext::getMain() );

		return $form->getField( 'UploadFile' );
	}

	public function testGetLabelHtmlWithoutRadioOmitsInputElement(): void {
		$field = $this->makeField();

		$html = $field->getLabelHtml();

		$this->assertStringContainsString( 'wpSourceTypeFile', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	public function testGetLabelHtmlWithRadioRendersRadioInput(): void {
		$radio = true;
		$field = $this->makeField( [ 'radio' => &$radio ] );

		$html = $field->getLabelHtml();

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'type="radio"', $html );
		$this->assertStringContainsString( 'name="wpSourceType"', $html );
		$this->assertStringContainsString( 'value="File"', $html );
	}

	public function testGetLabelHtmlWithCheckedRadioAddsCheckedAttribute(): void {
		$radio = true;
		$field = $this->makeField( [ 'radio' => &$radio, 'checked' => true ] );

		$html = $field->getLabelHtml();

		$this->assertStringContainsString( 'checked=', $html );
	}

	public function testGetSizeDefaultsTo60(): void {
		$field = $this->makeField();

		$this->assertSame( 60, $field->getSize() );
	}

	public function testGetSizeUsesConfiguredValue(): void {
		$field = $this->makeField( [ 'size' => 42 ] );

		$this->assertSame( 42, $field->getSize() );
	}
}
