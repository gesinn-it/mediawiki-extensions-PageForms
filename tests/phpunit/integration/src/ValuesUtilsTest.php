<?php

use MediaWiki\Extension\PageForms\ValuesUtils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\ValuesUtils
 * @group PF
 */
class ValuesUtilsTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\PageForms\ValuesUtils::getAutocompleteValues
	 */
	public function testGetAutocompleteValuesReturnsEmptyArrayWhenSourceIsNull(): void {
		$this->assertSame( [], ValuesUtils::getAutocompleteValues( null, 'property' ) );
	}
}
