<?php

use MediaWiki\MediaWikiServices;

/**
 * @covers \PFFormStart
 * @group Database
 *
 * @author gesinn-it-wam
 */
class PFFormStartTest extends SpecialPageTestBase {

	use IntegrationTestHelpers;

	public function setUp(): void {
		parent::setUp();
		$this->requireLanguageCodeEn();
	}

	/**
	 * Create an instance of the special page being tested.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		return MediaWikiServices::getInstance()->getSpecialPageFactory()->getPage( 'FormStart' );
	}

	public function testEmptyQuery() {
		[ $html ] = $this->executeSpecialPage( '' );

		$this->assertStringContainsString( 'No forms have been defined on this site.', $html );
	}

	public function testUrlPathTargetRedirectsWithoutTrailingForm() {
		[ $html ] = $this->executeSpecialPage( 'SomeForm/SomeTarget' );

		$this->assertStringContainsString( '<meta http-equiv="refresh"', $html );
		$this->assertStringNotContainsString( '<form action=', $html );
	}

	public function testUrlPathTargetWithInvalidTitleShowsError() {
		[ $html ] = $this->executeSpecialPage( 'SomeForm/Some<Target' );

		$this->assertStringContainsString( 'pf_formstart_badtitle', $html );
	}

	public function testReturntoValueIsUrlEncodedInRedirect() {
		$request = new FauxRequest( [ 'returnto' => 'A&B' ] );

		[ $html ] = $this->executeSpecialPage( 'SomeForm/SomeNonexistentTarget', $request );

		$this->assertStringContainsString( 'returnto=A%26B', $html );
		$this->assertStringNotContainsString( 'returnto=A&B', $html );
	}

}
