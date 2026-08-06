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

	// ---- DisplayTitle resolution for disabled Page-type fields ----

	public function testGetHtmlDisabledPageValueShowsDisplayTitleNotCanonicalValue(): void {
		$html = $this->getHtml(
			'Requirement:PFTITestReqUuid01',
			false,
			true,
			[ 'possible_values' => [ 'Requirement:PFTITestReqUuid01' => 'PFTI Test Requirement Title 01' ] ]
		);

		$this->assertStringContainsString( 'value="PFTI Test Requirement Title 01"', $html );
		$this->assertStringNotContainsString( 'value="Requirement:PFTITestReqUuid01"', $html );
	}

	public function testGetHtmlEnabledPageValueKeepsCanonicalValueForSubmission(): void {
		$html = $this->getHtml(
			'Requirement:PFTITestReqUuid01',
			false,
			false,
			[ 'possible_values' => [ 'Requirement:PFTITestReqUuid01' => 'PFTI Test Requirement Title 01' ] ]
		);

		$this->assertStringContainsString( 'value="Requirement:PFTITestReqUuid01"', $html );
	}

	public function testGetHtmlDisabledPageValueOutsidePossibleValuesFallsBackToBareTitle(): void {
		$html = $this->getHtml(
			'Requirement:PFTITestReqUuidNotInList',
			false,
			true,
			[ 'possible_values' => [ 'Requirement:PFTITestReqUuid01' => 'PFTI Test Requirement Title 01' ] ]
		);

		// No page named "Requirement:PFTITestReqUuidNotInList" exists in this test's
		// DB, so it has no DisplayTitle page property to fall back to - the raw
		// stored value is kept as-is (see PFValuesUtils::addDisplayTitlesForPageValues()).
		$this->assertStringContainsString( 'value="Requirement:PFTITestReqUuidNotInList"', $html );
	}

	public function testGetHtmlDisabledListOfPageValuesShowsDisplayTitlesForEach(): void {
		$html = PFTextInput::getHTML(
			'Requirement:PFTITestReqUuid01@@Requirement:PFTITestReqUuid02',
			'PFTIField01',
			false,
			true,
			[
				'is_list' => true,
				'delimiter' => '@@',
				'possible_values' => [
					'Requirement:PFTITestReqUuid01' => 'PFTI Test Requirement Title 01',
					'Requirement:PFTITestReqUuid02' => 'PFTI Test Requirement Title 02',
				],
			]
		);

		$this->assertStringContainsString(
			'value="PFTI Test Requirement Title 01@@ PFTI Test Requirement Title 02"',
			$html
		);
	}

	public function testGetHtmlDisabledNonPageValueIsUnaffectedByNumericPossibleValues(): void {
		$html = $this->getHtml(
			'PFTITestPlainValue01',
			false,
			true,
			[ 'possible_values' => [ 'PFTITestPlainValue01', 'PFTITestPlainValue02' ] ]
		);

		$this->assertStringContainsString( 'value="PFTITestPlainValue01"', $html );
	}
}
