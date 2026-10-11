<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Unit;

use MediaWiki\Extension\PageForms\TemplateSubmittedValues;
use PHPUnit\Framework\TestCase;
use WebRequest;

/**
 * @covers \MediaWiki\Extension\PageForms\TemplateSubmittedValues
 */
class TemplateSubmittedValuesTest extends TestCase {

	/**
	 * A request that holds the given arrays under their names and nothing else.
	 *
	 * @param array<string, array> $data
	 * @return WebRequest
	 */
	private function newRequest( array $data ): WebRequest {
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getArray' )->willReturnCallback(
			static fn ( $key ) => $data[$key] ?? null
		);
		return $request;
	}

	/**
	 * @dataProvider provideSubmittedValues
	 * @param string $templateName
	 * @param bool $allowsMultiple
	 * @param int $instanceNum
	 * @param int|null $numSeenInstancesOnPage How many instances the page has, if it calls the template
	 * @param array<string, array> $request
	 * @param array $expected
	 */
	public function testRead(
		string $templateName, bool $allowsMultiple, int $instanceNum, ?int $numSeenInstancesOnPage,
		array $request, array $expected
	): void {
		$values = new TemplateSubmittedValues();

		$values->read(
			$this->newRequest( $request ), $templateName, $allowsMultiple, $instanceNum, $numSeenInstancesOnPage
		);

		$this->assertEquals( $expected, $values->get() );
	}

	/**
	 * @return array<string, array>
	 */
	public static function provideSubmittedValues(): array {
		return [
			'a single instance' => [
				'My Template', false, 0, null,
				[ 'My_Template' => [ 'field1' => 'value1', 'field2' => 'value2' ] ],
				[ 'field1' => 'value1', 'field2' => 'value2' ],
			],
			'the numbered instance of multiple instances' => [
				'My Template', true, 1, null,
				[ 'My_Template' => [
					'0' => [ 'field1' => 'value1', 'field2' => 'value2' ],
					'1' => [ 'field1' => 'value3', 'field2' => 'value4' ],
				] ],
				[ 'field1' => 'value3', 'field2' => 'value4' ],
			],
			'no data for the template' => [ 'Nonexistent Template', false, 0, null, [], [] ],
			'a spreadsheet unescapes the values' => [
				'PFTestTemplateInFormSpreadsheet01', true, 0, null,
				[
					'PFTestTemplateInFormSpreadsheet01' => [ '0' => [ 'field1' => 'a &lt;b&gt; c' ] ],
					'spreadsheet_templates' => [ 'PFTestTemplateInFormSpreadsheet01' => true ],
				],
				[ 'field1' => 'a <b> c' ],
			],
			'an instance that is already on the page is returned early' => [
				'PFTestTemplateInFormExistingInstance01', true, 0,
				1,
				[ 'PFTestTemplateInFormExistingInstance01' => [
					'0' => [ 'field1' => 'value0' ],
					'1' => [ 'field1' => 'value1' ],
				] ],
				[ 'field1' => 'value0' ],
			],
			// The instances seen on the page are 3 when the instance number is 2: keys 0 and 1 are both
			// below that but neither equals the instance number, so the loop removes both without an
			// early match and falls through to the "still in existing templates" check.
			'the submitted instances are all still on the page' => [
				'PFTestTemplateInFormStillExisting01', true, 2,
				3,
				[ 'PFTestTemplateInFormStillExisting01' => [
					'0' => [ 'field1' => 'value0' ],
					'1' => [ 'field1' => 'value1' ],
				] ],
				[],
			],
			'a new instance is taken after the instances on the page' => [
				'PFTestTemplateInFormNewInstance01', true, 2,
				3,
				[ 'PFTestTemplateInFormNewInstance01' => [
					'0' => [ 'field1' => 'value0' ],
					'1' => [ 'field1' => 'value1' ],
					'2' => [ 'field1' => 'value2' ],
				] ],
				[ 'field1' => 'value2' ],
			],
			'a new instance is looked up by its position among the keys' => [
				'PFTestTemplateInFormNewInstance02', true, 1,
				null,
				[ 'PFTestTemplateInFormNewInstance02' => [
					'num' => '2',
					'a' => [ 'field1' => 'valueA' ],
					'b' => [ 'field1' => 'valueB' ],
				] ],
				[ 'field1' => 'valueB' ],
			],
			'a key with a letter suffix counts as an existing instance' => [
				'PFTestTemplateInFormSuffix01', true, 1,
				2,
				[ 'PFTestTemplateInFormSuffix01' => [
					'0' => [ 'field1' => 'value0' ],
					'1a' => [ 'field1' => 'value1' ],
				] ],
				[ 'field1' => 'value1' ],
			],
			'there is no new instance beyond the submitted ones' => [
				'PFTestTemplateInFormNoMore01', true, 3,
				null,
				[ 'PFTestTemplateInFormNoMore01' => [ '0' => [ 'field1' => 'value0' ] ] ],
				[],
			],
			'the values of an earlier instance are reset' => [
				'PFTestTemplateInFormReset01', true, 1,
				null,
				[ 'PFTestTemplateInFormReset01' => [ 'num' => '1' ] ],
				[],
			],
			'dots in the template name are read as underscores' => [
				'My.Template Name', false, 0, null,
				[ 'My_Template_Name' => [ 'field1' => 'value1' ] ],
				[ 'field1' => 'value1' ],
			],
		];
	}

	public function testReadCountsTheInstancesOfTheRequest(): void {
		$values = new TemplateSubmittedValues();

		$values->read( $this->newRequest( [ 'T' => [
			'num' => '3',
			'0' => [ 'f' => 'a' ],
			'1' => [ 'f' => 'b' ],
		] ] ), 'T', true, 0, null );

		$this->assertSame( 2, $values->numInstances() );
	}

	public function testHasValuesIsFalseBeforeAnythingIsRead(): void {
		$values = new TemplateSubmittedValues();

		$this->assertFalse( $values->hasValues() );
		$this->assertSame( [], $values->get() );
		$this->assertSame( 0, $values->numInstances() );
	}

	public function testHasValuesIsTrueForTheValuesOfAnInstance(): void {
		$values = new TemplateSubmittedValues();

		$values->read( $this->newRequest( [ 'T' => [ 'f' => 'a' ] ] ), 'T', false, 0, null );

		$this->assertTrue( $values->hasValues() );
	}

	public function testTheFirstInstanceKeepsItsValuesIfTheRequestHasNone(): void {
		$values = new TemplateSubmittedValues();
		$values->read( $this->newRequest( [ 'T' => [ 'f' => 'a' ] ] ), 'T', false, 0, null );

		$values->read( $this->newRequest( [] ), 'T', false, 0, null );

		$this->assertSame( [ 'f' => 'a' ], $values->get() );
	}

	public function testTheValuesOfAnEarlierInstanceAreForgottenForALaterOne(): void {
		$values = new TemplateSubmittedValues();
		$values->read( $this->newRequest( [ 'T' => [ 'f' => 'a' ] ] ), 'T', false, 0, null );

		$values->read( $this->newRequest( [] ), 'T', false, 1, null );

		$this->assertSame( [], $values->get() );
	}

	public function testAnInstanceThatWasNotSentAsFieldsHasNoValues(): void {
		$values = new TemplateSubmittedValues();

		$values->read( $this->newRequest( [ 'T' => [ 'f' => 'a' ] ] ), 'T', true, 0, null );

		$this->assertSame( [], $values->get() );
		$this->assertFalse( $values->hasValues() );
	}
}
