<?php

use MediaWiki\Extension\PageForms\ValuesUtils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\ValuesUtils
 * @group PF
 */
class ValuesUtilsTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsReturnsUnchangedWhenNoDuplicates(): void {
		$input = [ 'paris_fr' => 'Alpha', 'paris_tx' => 'Beta' ];
		$result = ValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Alpha', $result['paris_fr'] );
		$this->assertSame( 'Beta', $result['paris_tx'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::disambiguateLabels
	 */
	public function testDisambiguateLabelsSuffixesDuplicateValues(): void {
		$input = [ 'Paris_FR' => 'Paris', 'Paris_TX' => 'Paris' ];
		$result = ValuesUtils::disambiguateLabels( $input );
		$this->assertSame( 'Paris (Paris_FR)', $result['Paris_FR'] );
		$this->assertSame( 'Paris (Paris_TX)', $result['Paris_TX'] );
	}

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesReturnsEmptyArrayWhenSourceIsNull(): void {
		$this->assertSame( [], ValuesUtils::getAutocompleteValues( null, 'property' ) );
	}
}
