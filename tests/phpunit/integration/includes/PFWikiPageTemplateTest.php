<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFWikiPageTemplate
 */
class PFWikiPageTemplateTest extends TestCase {

	public function testGetNameReturnsConstructorValue() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$this->assertSame( 'MyTemplate', $template->getName() );
	}

	public function testGetParamsReturnsEmptyArrayByDefault() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$this->assertSame( [], $template->getParams() );
	}

	public function testAddParamAppendsParamWithNameAndValue() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$template->addParam( 'field1', 'value1' );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'field1', $params[0]->getName() );
		$this->assertSame( 'value1', $params[0]->getValue() );
	}

	public function testAddParamAppendsMultipleParamsInOrder() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$template->addParam( 'field1', 'value1' );
		$template->addParam( 'field2', 'value2' );

		$params = $template->getParams();
		$this->assertCount( 2, $params );
		$this->assertSame( 'field1', $params[0]->getName() );
		$this->assertSame( 'field2', $params[1]->getName() );
	}

	public function testAddUnhandledParamReplacesBlankExistingValue() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$template->addParam( 'field1', '' );

		$template->addUnhandledParam( 'field1', 'newValue' );

		$params = $template->getParams();
		$this->assertCount( 1, $params, 'No new param should be appended - the existing one is updated' );
		$this->assertSame( 'newValue', $params[0]->getValue() );
	}

	public function testAddUnhandledParamLeavesNonBlankExistingValueUntouched() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$template->addParam( 'field1', 'existingValue' );

		$template->addUnhandledParam( 'field1', 'newValue' );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'existingValue', $params[0]->getValue() );
	}

	public function testAddUnhandledParamAppendsNewParamWhenNoneExists() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );

		$template->addUnhandledParam( 'field1', 'value1' );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'field1', $params[0]->getName() );
		$this->assertSame( 'value1', $params[0]->getValue() );
	}

	public function testAddUnhandledParamsDoesNothingWhenFlagIsFalse() {
		$template = new PFWikiPageTemplate( 'MyTemplate', false );
		$request = new FauxRequest( [ '_unhandled_MyTemplate_field1' => 'value1' ] );

		$template->addUnhandledParams( $request );

		$this->assertSame( [], $template->getParams() );
	}

	public function testAddUnhandledParamsPicksUpMatchingRequestKeysWhenFlagIsTrue() {
		$template = new PFWikiPageTemplate( 'MyTemplate', true );
		$request = new FauxRequest( [ '_unhandled_MyTemplate_field1' => 'value1' ] );

		$template->addUnhandledParams( $request );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'field1', $params[0]->getName() );
		$this->assertSame( 'value1', $params[0]->getValue() );
	}

	public function testAddUnhandledParamsReplacesSpacesWithUnderscoresInTemplateNamePrefix() {
		$template = new PFWikiPageTemplate( 'My Template', true );
		$request = new FauxRequest( [ '_unhandled_My_Template_field1' => 'value1' ] );

		$template->addUnhandledParams( $request );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'field1', $params[0]->getName() );
	}

	public function testAddUnhandledParamsUrldecodesParamName() {
		$template = new PFWikiPageTemplate( 'MyTemplate', true );
		$request = new FauxRequest( [ '_unhandled_MyTemplate_field%20one' => 'value1' ] );

		$template->addUnhandledParams( $request );

		$params = $template->getParams();
		$this->assertCount( 1, $params );
		$this->assertSame( 'field one', $params[0]->getName() );
	}

	public function testAddUnhandledParamsIgnoresKeysNotMatchingPrefix() {
		$template = new PFWikiPageTemplate( 'MyTemplate', true );
		$request = new FauxRequest( [
			'some_other_key' => 'value1',
			'_unhandled_OtherTemplate_field1' => 'value2',
		] );

		$template->addUnhandledParams( $request );

		$this->assertSame( [], $template->getParams() );
	}

	public function testAddUnhandledParamsPicksUpMultipleMatchingKeys() {
		$template = new PFWikiPageTemplate( 'MyTemplate', true );
		$request = new FauxRequest( [
			'_unhandled_MyTemplate_field1' => 'value1',
			'_unhandled_MyTemplate_field2' => 'value2',
			'_unhandled_OtherTemplate_field3' => 'value3',
		] );

		$template->addUnhandledParams( $request );

		$params = $template->getParams();
		$this->assertCount( 2, $params );
		$names = array_map( static fn ( $p ) => $p->getName(), $params );
		$this->assertContains( 'field1', $names );
		$this->assertContains( 'field2', $names );
	}
}
