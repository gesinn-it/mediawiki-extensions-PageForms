<?php

use MediaWiki\Extension\PageForms\FormField;
use MediaWiki\Extension\PageForms\FormFieldCurrentValue;
use MediaWiki\Extension\PageForms\TemplateField;
use PHPUnit\Framework\TestCase;

/**
 * The translate tags are tested directly, since the branch of get() that calls the cleanup
 * requires the Translate extension, which is not installed in this test environment. The value of
 * a field for a request is tested with FormField::getCurrentValue() in FormFieldTest.
 *
 * @covers \MediaWiki\Extension\PageForms\FormFieldCurrentValue
 * @group PF
 */
class FormFieldCurrentValueTest extends TestCase {

	private function newCurrentValue(): FormFieldCurrentValue {
		return new FormFieldCurrentValue( FormField::create( $this->createMock( TemplateField::class ) ) );
	}

	public function testCleanupRemovesDuplicateAdjacentTags() {
		$field = $this->newCurrentValue();
		$value = "<!--T:1--><!--T:2-->Some text";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( '<!--T:2-->Some text', $value );
	}

	public function testCleanupRemovesTrailingTagBeforeCloseTag() {
		$field = $this->newCurrentValue();
		$value = "Some text<!--T:1--></translate>";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( 'Some text</translate>', $value );
	}

	public function testCleanupAddsNewlineBeforeTemplateCall() {
		$field = $this->newCurrentValue();
		$value = "<!--T:1-->{{SomeTemplate}}";
		$field->cleanupTranslateTags( $value );
		$this->assertSame( "<!--T:1-->\n{{SomeTemplate}}", $value );
	}

	/**
	 * Pathological input with 205 distinct adjacent tags must not loop
	 * forever - the safety-valve break after 200 iterations
	 * must trigger. Tags need distinct numbers:
	 * str_replace() on an identical repeated tag would collapse the whole
	 * chain in a single iteration, never reaching the guard.
	 */
	public function testCleanupDuplicateTagLoopBreaksAfter200Iterations() {
		$field = $this->newCurrentValue();
		$value = '';
		for ( $n = 1; $n <= 205; $n++ ) {
			$value .= "<!--T:$n-->";
		}
		$field->cleanupTranslateTags( $value );
		// Loop breaks after 202 iterations, leaving the remaining un-merged tags.
		$this->assertStringContainsString( '<!--T:203-->', $value );
	}

	/**
	 * Same safety-valve, for the "add newline before template call" loop.
	 * Each tag+template pair only needs one
	 * pass, so 205 distinct pairs are required to exceed the 200-iteration
	 * guard within a single call.
	 */
	public function testCleanupNewlineLoopBreaksAfter200Iterations() {
		$field = $this->newCurrentValue();
		$value = '';
		for ( $n = 1; $n <= 205; $n++ ) {
			$value .= "<!--T:$n-->{{Foo$n}}";
		}
		$field->cleanupTranslateTags( $value );
		$this->assertStringContainsString( "<!--T:1-->\n{{Foo1}}", $value );
	}
}
