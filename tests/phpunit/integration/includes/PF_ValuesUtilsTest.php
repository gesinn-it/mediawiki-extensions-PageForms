<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers \PFValuesUtils
 * @group PF
 */
class PFValuesUtilsTest extends TestCase {
	private const GLOBAL_UNSET = '__PF_GLOBAL_UNSET__';

	private $oldUseDisplayTitle;
	private $oldMaxLocalAutocompleteValues;
	private $oldAutocompleteValues;

	protected function setUp(): void {
		parent::setUp();

		$this->oldUseDisplayTitle = array_key_exists( 'wgPageFormsUseDisplayTitle', $GLOBALS )
			? $GLOBALS['wgPageFormsUseDisplayTitle']
			: self::GLOBAL_UNSET;
		$this->oldMaxLocalAutocompleteValues = array_key_exists( 'wgPageFormsMaxLocalAutocompleteValues', $GLOBALS )
			? $GLOBALS['wgPageFormsMaxLocalAutocompleteValues']
			: self::GLOBAL_UNSET;
		$this->oldAutocompleteValues = array_key_exists( 'wgPageFormsAutocompleteValues', $GLOBALS )
			? $GLOBALS['wgPageFormsAutocompleteValues']
			: self::GLOBAL_UNSET;

		$GLOBALS['wgPageFormsUseDisplayTitle'] = true;
		$GLOBALS['wgPageFormsMaxLocalAutocompleteValues'] = 100;
		$GLOBALS['wgPageFormsAutocompleteValues'] = [];
	}

	protected function tearDown(): void {
		$this->restoreGlobal( 'wgPageFormsUseDisplayTitle', $this->oldUseDisplayTitle );
		$this->restoreGlobal( 'wgPageFormsMaxLocalAutocompleteValues', $this->oldMaxLocalAutocompleteValues );
		$this->restoreGlobal( 'wgPageFormsAutocompleteValues', $this->oldAutocompleteValues );

		parent::tearDown();
	}

	/**
	 * @covers \PFValuesUtils::maybeDisambiguateAutocompleteLabels
	 */
	public function testMaybeDisambiguateAutocompleteLabelsOnlyForMappedSources() {
		$labels = [
			'FooAlpha' => 'Paris',
			'FooBeta' => 'Paris'
		];
		$expected = [
			'FooAlpha' => 'Paris (FooAlpha)',
			'FooBeta' => 'Paris (FooBeta)'
		];

		foreach ( [ 'category', 'concept', 'namespace', 'property' ] as $sourceType ) {
			$this->assertSame( $expected, PFValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, $sourceType ) );
		}

		$GLOBALS['wgPageFormsUseDisplayTitle'] = false;
		$this->assertSame( $labels, PFValuesUtils::maybeDisambiguateAutocompleteLabels( $labels, 'category' ) );
	}

	/**
	 * @covers \PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues
	 */
	public function testGetRemoteDataTypeAndPossiblySetAutocompleteValuesStoresDisambiguatedCategoryLabels() {
		$fieldArgs = [
			'possible_values' => [
				'FooAlpha' => 'Paris',
				'FooBeta' => 'Paris'
			]
		];

		$remoteDataType = PFValuesUtils::getRemoteDataTypeAndPossiblySetAutocompleteValues(
			'category',
			'DisambiguationCategory',
			$fieldArgs,
			'disambiguation-settings'
		);

		$this->assertNull( $remoteDataType );
		$this->assertArrayHasKey( 'disambiguation-settings', $GLOBALS['wgPageFormsAutocompleteValues'] );
		$this->assertSame(
			[
				'FooAlpha' => 'Paris (FooAlpha)',
				'FooBeta' => 'Paris (FooBeta)'
			],
			$GLOBALS['wgPageFormsAutocompleteValues']['disambiguation-settings']
		);
	}

	private function restoreGlobal( $globalName, $value ): void {
		if ( $value === self::GLOBAL_UNSET ) {
			unset( $GLOBALS[$globalName] );
			return;
		}

		$GLOBALS[$globalName] = $value;
	}
}
