<?php

declare( strict_types=1 );

use OOUI\BlankTheme;

/**
 * @covers \PFOpenLayersInput
 * @group Database
 */
class PFOpenLayersInputMapLookupHtmlTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );

		global $wgPageFormsTabIndex, $wgPageFormsFieldNum, $wgPageFormsMapsWithFeeders;
		$wgPageFormsTabIndex = 1;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsMapsWithFeeders = [];
	}

	private function getHtml(
		string $curValue = '',
		bool $isMandatory = false,
		bool $isDisabled = false,
		array $otherArgs = []
	): string {
		return PFOpenLayersInput::mapLookupHTML(
			$curValue,
			'TestField',
			$isMandatory,
			$isDisabled,
			$otherArgs,
			'500px',
			'500px'
		);
	}

	public function testGetHtmlDefaultClassOnCoordsInput(): void {
		$html = $this->getHtml();

		$this->assertStringContainsString( 'class="pfCoordsInput"', $html );
	}

	public function testGetHtmlClassOverrideIsAppendedToCoordsInput(): void {
		$html = $this->getHtml( '', false, false, [ 'class' => 'customOlClass' ] );

		$this->assertStringContainsString( 'class="pfCoordsInput customOlClass"', $html );
	}

	public function testGetHtmlMandatoryAppendsMandatoryFieldSpanToCoordsInput(): void {
		$html = $this->getHtml( '', true );

		$this->assertStringContainsString( 'class="pfCoordsInput mandatoryFieldSpan"', $html );
	}

	public function testGetHtmlMandatoryWithClassOverridePreservesOrder(): void {
		$html = $this->getHtml( '', true, false, [ 'class' => 'customOlClass' ] );

		$this->assertStringContainsString(
			'class="pfCoordsInput customOlClass mandatoryFieldSpan"',
			$html
		);
	}

	/**
	 * Pins existing behavior: disabling the input appends 'pfCoordsInputDisabled'
	 * to a local $className variable that is never written back into the
	 * coordsInput's class attribute after it is set - so the class string on
	 * the rendered <input> does not change when disabled, only the disabled
	 * attribute is added.
	 */
	public function testGetHtmlDisabledDoesNotChangeCoordsInputClassButAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', false, true );

		$this->assertStringContainsString( 'class="pfCoordsInput"', $html );
		$this->assertStringContainsString( 'disabled=""', $html );
		$this->assertStringNotContainsString( 'pfCoordsInputDisabled', $html );
	}

	public function testGetHtmlMandatoryAndDisabledPreservesOrderAndAddsDisabledAttribute(): void {
		$html = $this->getHtml( '', true, true, [ 'class' => 'customOlClass' ] );

		$this->assertStringContainsString(
			'class="pfCoordsInput customOlClass mandatoryFieldSpan"',
			$html
		);
		$this->assertStringContainsString( 'disabled=""', $html );
	}
}
