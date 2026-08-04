<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\MultipleTemplateHtmlBuilder;
use MediaWiki\Extension\PageForms\TemplateInForm;
use PHPUnit\Framework\TestCase;

/**
 * @covers MediaWiki\Extension\PageForms\MultipleTemplateHtmlBuilder
 */
class MultipleTemplateHtmlBuilderTest extends TestCase {

	private MultipleTemplateHtmlBuilder $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new MultipleTemplateHtmlBuilder();
	}

	// -----------------------------------------------------------------------
	// multipleTemplateStartHTML
	// -----------------------------------------------------------------------

	public function testStartHtmlContainsWrapper(): void {
		$tif = $this->makeTif( null, null, null );
		$html = $this->builder->multipleTemplateStartHTML( $tif );

		$this->assertStringContainsString( 'class="multipleTemplateWrapper"', $html );
		$this->assertStringContainsString( 'class="multipleTemplateList"', $html );
	}

	public function testStartHtmlWithMinMaxInstances(): void {
		$tif = $this->makeTif( 2, 5, null );
		$html = $this->builder->multipleTemplateStartHTML( $tif );

		// Html::openElement() lowercases attribute names per HTML spec
		$this->assertStringContainsString( 'minimuminstances="2"', $html );
		$this->assertStringContainsString( 'maximuminstances="5"', $html );
	}

	public function testStartHtmlWithDisplayedFieldsWhenMinimized(): void {
		$tif = $this->makeTif( null, null, 'Field1,Field2' );
		$html = $this->builder->multipleTemplateStartHTML( $tif );

		$this->assertStringContainsString( 'data-displayed-fields-when-minimized="Field1,Field2"', $html );
	}

	public function testStartHtmlWithoutOptionalAttributes(): void {
		$tif = $this->makeTif( null, null, null );
		$html = $this->builder->multipleTemplateStartHTML( $tif );

		$this->assertStringNotContainsString( 'minimumInstances', $html );
		$this->assertStringNotContainsString( 'maximumInstances', $html );
		$this->assertStringNotContainsString( 'data-displayed-fields-when-minimized', $html );
	}

	// -----------------------------------------------------------------------
	// multipleTemplateInstanceTableHTML
	// -----------------------------------------------------------------------

	public function testInstanceTableHtmlStructure(): void {
		$html = $this->builder->multipleTemplateInstanceTableHTML( false, '<input/>' );

		$this->assertStringContainsString( 'class="multipleTemplateInstanceTable"', $html );
		$this->assertStringContainsString( 'class="instanceRearranger"', $html );
		$this->assertStringContainsString( 'class="instanceMain"', $html );
		$this->assertStringContainsString( '<input/>', $html );
		$this->assertStringContainsString( 'class="instanceAddAbove"', $html );
		$this->assertStringContainsString( 'class="instanceRemove"', $html );
	}

	public function testInstanceTableHtmlDisabledHasNoButtons(): void {
		$html = $this->builder->multipleTemplateInstanceTableHTML( true, 'content' );

		$this->assertStringNotContainsString( 'addAboveButton', $html );
		$this->assertStringNotContainsString( 'removeButton', $html );
	}

	public function testInstanceTableHtmlEnabledHasButtons(): void {
		$html = $this->builder->multipleTemplateInstanceTableHTML( false, 'content' );

		$this->assertStringContainsString( 'addAboveButton', $html );
		$this->assertStringContainsString( 'removeButton', $html );
	}

	// -----------------------------------------------------------------------
	// multipleTemplateInstanceHTML
	// -----------------------------------------------------------------------

	public function testInstanceHtmlContainsInstanceClasses(): void {
		global $wgPageFormsCalendarHTML;
		$wgPageFormsCalendarHTML = [];

		$tif = $this->makeTifForInstance( 'MyTemplate', 1 );
		$section = '<div id="foo_[num]">content [num]</div>';
		$html = $this->builder->multipleTemplateInstanceHTML( $tif, false, $section );

		$this->assertStringContainsString( 'multipleTemplateInstance', $html );
		$this->assertStringContainsString( 'multipleTemplate"', $html );
	}

	public function testInstanceHtmlReplacesNumPlaceholder(): void {
		global $wgPageFormsCalendarHTML;
		$wgPageFormsCalendarHTML = [];

		$tif = $this->makeTifForInstance( 'MyTemplate', 3 );
		$section = 'value[num]end';
		$this->builder->multipleTemplateInstanceHTML( $tif, false, $section );

		$this->assertStringContainsString( '[3a]', $section );
		$this->assertStringNotContainsString( '[num]', $section );
	}

	public function testInstanceHtmlAddsDataOrigId(): void {
		global $wgPageFormsCalendarHTML;
		$wgPageFormsCalendarHTML = [];

		$tif = $this->makeTifForInstance( 'MyTemplate', 1 );
		$section = '<input id="myField_[num]">';
		$this->builder->multipleTemplateInstanceHTML( $tif, false, $section );

		$this->assertStringContainsString( 'data-origID=', $section );
	}

	public function testInstanceHtmlSetsCalendarGlobal(): void {
		global $wgPageFormsCalendarHTML;
		$wgPageFormsCalendarHTML = [];

		$tif = $this->makeTifForInstance( 'CalTemplate', 2 );
		$section = 'text[num]more';
		$this->builder->multipleTemplateInstanceHTML( $tif, false, $section );

		$this->assertArrayHasKey( 'CalTemplate', $wgPageFormsCalendarHTML );
		$this->assertStringContainsString( '[cf]', $wgPageFormsCalendarHTML['CalTemplate'] );
	}

	// -----------------------------------------------------------------------
	// multipleTemplateEndHTML
	// -----------------------------------------------------------------------

	public function testEndHtmlContainsClosingStructure(): void {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		\OOUI\Theme::setSingleton( new \OOUI\BlankTheme() );

		$tif = $this->makeTifForEnd( 'Add another' );
		$html = $this->builder->multipleTemplateEndHTML( $tif, false, 'section content' );

		$this->assertStringContainsString( 'multipleTemplateStarter', $html );
		$this->assertStringContainsString( '<!-- multipleTemplateList -->', $html );
		$this->assertStringContainsString( '<!-- multipleTemplateWrapper -->', $html );
		$this->assertStringContainsString( 'pfErrorMessages', $html );
	}

	public function testEndHtmlContainsAdderButton(): void {
		global $wgPageFormsTabIndex;
		$wgPageFormsTabIndex = 1;

		\OOUI\Theme::setSingleton( new \OOUI\BlankTheme() );

		$tif = $this->makeTifForEnd( 'Add row' );
		$html = $this->builder->multipleTemplateEndHTML( $tif, false, '' );

		$this->assertStringContainsString( 'multipleTemplateAdder', $html );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function makeTif( ?int $min, ?int $max, ?string $displayedFields ): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getMinInstancesAllowed' )->willReturn( $min );
		$tif->method( 'getMaxInstancesAllowed' )->willReturn( $max );
		$tif->method( 'getDisplayedFieldsWhenMinimized' )->willReturn( $displayedFields );
		return $tif;
	}

	private function makeTifForInstance( string $templateName, int $instanceNum ): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getTemplateName' )->willReturn( $templateName );
		$tif->method( 'getInstanceNum' )->willReturn( $instanceNum );
		return $tif;
	}

	private function makeTifForEnd( string $addButtonText ): TemplateInForm {
		$tif = $this->createMock( TemplateInForm::class );
		$tif->method( 'getAddButtonText' )->willReturn( $addButtonText );
		return $tif;
	}
}
