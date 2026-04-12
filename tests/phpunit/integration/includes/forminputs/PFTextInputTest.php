<?php

declare( strict_types=1 );

/**
 * @covers \PFTextInput
 * @group Database
 */
class PFTextInputTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
	}

	private function getHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $extraArgs = []
	): string {
		return PFTextInput::getHTML(
			$curValue,
			'PFTIField01',
			$isMandatory,
			$isDisabled,
			$extraArgs
		);
	}

	// ---- basic rendering ----

	public function testGetHtmlRendersInputElement(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( '<input ', $html );
		$this->assertStringContainsString( 'name="PFTIField01"', $html );
	}

	public function testGetHtmlDefaultClassIsCreateboxInput(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'class="createboxInput"', $html );
	}

	public function testGetHtmlMandatoryAddsMandatoryFieldSpanClass(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
		$this->assertStringContainsString( 'mandatoryField', $html );
	}

	public function testGetHtmlDisabledAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'disabled', $html );
	}

	public function testGetHtmlCurValueAppearsInValueAttribute(): void {
		$html = $this->getHtml( 'PFTIPreloadedValue01' );

		$this->assertStringContainsString( 'value="PFTIPreloadedValue01"', $html );
	}

	// ---- uploadable: fancybox branch (wgPageFormsSimpleUpload = false) ----

	public function testGetHtmlUploadableFancyboxRendersUploadLink(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = false;

		$html = $this->getHtml( '', false, false, [ 'uploadable' => true ] );

		$this->assertStringContainsString( 'pfFancyBox', $html );
		$this->assertStringContainsString( 'pfUploadable', $html );
	}

	public function testGetHtmlUploadableFancyboxContainsInputId(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = false;

		$html = $this->getHtml( '', false, false, [ 'uploadable' => true ] );

		$this->assertStringContainsString( 'data-input-id="input_1"', $html );
	}

	public function testGetHtmlUploadableFancyboxDefaultFilenameInUrl(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = false;

		$html = $this->getHtml( '', false, false, [
			'uploadable' => true,
			'default filename' => 'PFTIDefaultFile01.jpg',
		] );

		$this->assertStringContainsString( 'wpDestFile=PFTIDefaultFile01.jpg', $html );
	}

	public function testGetHtmlUploadableFancyboxWithImagePreviewAddsPreviewClass(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = false;

		$html = $this->getHtml( '', false, false, [
			'uploadable' => true,
			'image preview' => true,
		] );

		$this->assertStringContainsString( 'pfImagePreview', $html );
		$this->assertStringContainsString( 'pfImagePreviewWrapper', $html );
	}

	// ---- uploadable: simpleupload branch (wgPageFormsSimpleUpload = true) ----

	public function testGetHtmlUploadableSimpleUploadRendersSimpleUploadInterface(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = true;

		$html = $this->getHtml( '', false, false, [ 'uploadable' => true ] );

		$this->assertStringContainsString( 'simpleUploadInterface', $html );
	}

	public function testGetHtmlUploadableSimpleUploadContainsInputId(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = true;

		$html = $this->getHtml( '', false, false, [ 'uploadable' => true ] );

		$this->assertStringContainsString( 'data-input-id="input_1"', $html );
	}

	public function testGetHtmlUploadableSimpleUploadOmitsFancyboxClass(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = true;

		$html = $this->getHtml( '', false, false, [ 'uploadable' => true ] );

		$this->assertStringNotContainsString( 'pfFancyBox', $html );
	}

	// ---- non-uploadable does not render upload elements ----

	public function testGetHtmlWithoutUploadableOmitsUploadElements(): void {
		global $wgPageFormsSimpleUpload;
		$wgPageFormsSimpleUpload = false;

		$html = $this->getHtml();

		$this->assertStringNotContainsString( 'pfFancyBox', $html );
		$this->assertStringNotContainsString( 'simpleUploadInterface', $html );
	}

	public function testGetNameReturnsText(): void {
		$this->assertSame( 'text', PFTextInput::getName() );
	}

	// ---- autocapitalize ----

	public function testGetHtmlAutocapitalizeAttributeIsRendered(): void {
		$html = $this->getHtml( '', false, false, [ 'autocapitalize' => 'none' ] );

		$this->assertStringContainsString( 'autocapitalize="none"', $html );
	}

	// ---- unique ----

	public function testGetHtmlUniqueAddsUniqueFieldClassToInput(): void {
		$html = $this->getHtml( '', false, false, [ 'unique' => true ] );

		$this->assertStringContainsString( 'uniqueField', $html );
	}

	public function testGetHtmlUniqueAddsUniqueFieldSpanClassToWrapper(): void {
		$html = $this->getHtml( '', false, false, [ 'unique' => true ] );

		$this->assertStringContainsString( 'uniqueFieldSpan', $html );
	}

	// ---- is_list ----

	public function testGetHtmlIsListImplodesArrayValueWithDefaultDelimiter(): void {
		$html = PFTextInput::getHTML(
			[ 'apple', 'banana', 'cherry' ],
			'PFTIField01',
			false,
			false,
			[ 'is_list' => true ]
		);

		$this->assertStringContainsString( 'value="apple, banana, cherry"', $html );
	}

	public function testGetHtmlIsListWithCustomDelimiterImplodesValues(): void {
		$html = PFTextInput::getHTML(
			[ 'alpha', 'beta' ],
			'PFTIField01',
			false,
			false,
			[ 'is_list' => true, 'delimiter' => ';' ]
		);

		$this->assertStringContainsString( 'value="alpha; beta"', $html );
	}
}
