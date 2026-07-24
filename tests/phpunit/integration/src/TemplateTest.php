<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use IDBAccessObject;
use MediaWiki\Extension\PageForms\Template;
use MediaWiki\Extension\PageForms\TemplateField;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use TemplateTestInjectionProbe;
use Title;

require_once __DIR__ . '/TemplateTestInjectionProbe.php';

/**
 * Integration tests for Template::loadTemplateParams().
 *
 * @covers \MediaWiki\Extension\PageForms\Template
 *
 * @group Database
 */
class TemplateTest extends MediaWikiIntegrationTestCase {

	/**
	 * A valid serialized array payload is unserialized as-is.
	 */
	public function testLoadTemplateParamsWithValidArrayPayload() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateValidParams01',
			serialize( [ 'Field1' => [ 'mandatory' => true ] ] )
		);

		$template = Template::newFromName( $title->getText() );

		$this->assertSame( [ 'Field1' => [ 'mandatory' => true ] ], $template->getTemplateParams() );
	}

	/**
	 * A malformed (non-serialized) payload does not throw or emit warnings;
	 * getTemplateParams() falls back to null.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/12
	 */
	public function testLoadTemplateParamsWithInvalidPayloadDoesNotThrow() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateInvalidParams01',
			'this is not a serialized value'
		);

		$template = Template::newFromName( $title->getText() );

		$this->assertNull( $template->getTemplateParams() );
	}

	/**
	 * A payload attempting PHP object injection via unserialize() must not
	 * be instantiated as an object; allowed_classes is disabled so the
	 * result is either false/null, never an instance of the injected class.
	 *
	 * @see https://github.com/gesinn-it/mediawiki-extensions-PageForms/issues/129
	 */
	public function testLoadTemplateParamsWithObjectInjectionPayloadIsNotInstantiated() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateObjectInjectionParams01',
			serialize( new TemplateTestInjectionProbe() )
		);

		$template = Template::newFromName( $title->getText() );

		$this->assertNotInstanceOf( TemplateTestInjectionProbe::class, $template->getTemplateParams() );
	}

	/**
	 * loadTemplateParams() and loadTemplateFields() both return early when
	 * the template name cannot form a valid Title (Title::makeTitleSafe()
	 * returns null).
	 */
	public function testNewFromNameWithInvalidTemplateNameReturnsEmptyTemplate() {
		$template = Template::newFromName( '<invalid>' );

		$this->assertNull( $template->getTemplateParams() );
		$this->assertSame( [], $template->getTemplateFields() );
	}

	/**
	 * Covers the #arraymap, normal property call, #set/#set_internal/#subobject,
	 * and #declare regex branches of loadTemplateFieldsSMWAndOther() in a single
	 * template body, since each branch only records fields not already found.
	 */
	public function testLoadTemplateFieldsParsesAllPropertySettingSyntaxes() {
		$title = Title::makeTitle( NS_TEMPLATE, 'PFTestTemplateUnitAllSyntaxes01' );
		$this->editPage( $title, <<<WIKITEXT
{{#arraymap:{{{PFTestTemplateUnitAuthors01|}}}|,|x|[[PFTestTemplateUnitHasAuthor01::x]]}}
[[PFTestTemplateUnitHasTitle01::{{{PFTestTemplateUnitTitle01|}}}]]
{{#set:PFTestTemplateUnitHasDate01={{{PFTestTemplateUnitDate01|}}}}}
{{#declare:PFTestTemplateUnitHasColor01=PFTestTemplateUnitColor01}}
WIKITEXT
		);

		$template = Template::newFromName( $title->getText() );

		$this->assertNotNull( $template->getFieldNamed( 'PFTestTemplateUnitAuthors01' ) );
		$this->assertSame(
			'PFTestTemplateUnitHasAuthor01',
			$template->getFieldNamed( 'PFTestTemplateUnitAuthors01' )->getSemanticProperty()
		);
		$this->assertTrue( $template->getFieldNamed( 'PFTestTemplateUnitAuthors01' )->isList() );

		$this->assertNotNull( $template->getFieldNamed( 'PFTestTemplateUnitTitle01' ) );
		$this->assertSame(
			'PFTestTemplateUnitHasTitle01',
			$template->getFieldNamed( 'PFTestTemplateUnitTitle01' )->getSemanticProperty()
		);

		$this->assertNotNull( $template->getFieldNamed( 'PFTestTemplateUnitDate01' ) );
		$this->assertSame(
			'PFTestTemplateUnitHasDate01',
			$template->getFieldNamed( 'PFTestTemplateUnitDate01' )->getSemanticProperty()
		);

		$this->assertNotNull( $template->getFieldNamed( 'PFTestTemplateUnitColor01' ) );
		$this->assertSame(
			'PFTestTemplateUnitHasColor01',
			$template->getFieldNamed( 'PFTestTemplateUnitColor01' )->getSemanticProperty()
		);
	}

	/**
	 * A field name declared via #template_params that was already found by
	 * parsing the template text (e.g. via a normal property call) must be
	 * skipped instead of being overwritten with the #template_params version.
	 */
	public function testLoadTemplateFieldsSkipsTemplateParamsFieldAlreadyFoundInText() {
		$title = $this->insertTemplatePageWithParamsProp(
			'PFTestTemplateUnitParamsOverlap01',
			serialize( [ 'PFTestTemplateUnitOverlapField01' => [ 'label' => 'Should be ignored' ] ] ),
			'[[PFTestTemplateUnitHasOverlap01::{{{PFTestTemplateUnitOverlapField01|}}}]]'
		);

		$template = Template::newFromName( $title->getText() );

		$field = $template->getFieldNamed( 'PFTestTemplateUnitOverlapField01' );
		$this->assertNotNull( $field );
		// The property found while parsing the template text must survive,
		// proving the #template_params entry for the same field was skipped.
		$this->assertSame( 'PFTestTemplateUnitHasOverlap01', $field->getSemanticProperty() );
	}

	/**
	 * loadPropertySettingInTemplate() is exercised indirectly above; this
	 * test asserts its documented contract directly: field name, label
	 * (auto-capitalized), property name and list-ness are all recorded.
	 */
	public function testLoadPropertySettingInTemplateCreatesFieldWithExpectedAttributes() {
		$template = Template::newFromName( 'PFTestTemplateUnitDirectCall01' );
		$template->loadPropertySettingInTemplate( 'pftestfield01', 'PFTestTemplateUnitHasDirect01', true );

		$field = $template->getFieldNamed( 'pftestfield01' );
		$this->assertNotNull( $field );
		$this->assertSame( 'PFTestTemplateUnitHasDirect01', $field->getSemanticProperty() );
		$this->assertTrue( $field->isList() );
		$this->assertSame( 'Pftestfield01', $field->getLabel() );
	}

	public function testSetConnectingPropertyIsReflectedInCreateText() {
		$template = new Template( 'PFTestTemplateUnitConnecting01', [] );
		$template->setConnectingProperty( 'PFTestTemplateUnitHasConnection01' );

		$text = $template->createText();

		$this->assertStringContainsString( 'PFTestTemplateUnitHasConnection01', $text );
	}

	/**
	 * createTextForField() only prepends/appends the field-start/field-end
	 * markers when hook handlers populate them by reference; by default
	 * they stay empty and are left out.
	 */
	public function testCreateTextForFieldIncludesHookSuppliedStartAndEndMarkers() {
		$this->setTemporaryHook( 'PageForms::TemplateFieldStart', static function ( $field, &$fieldStart ) {
			$fieldStart = 'PFTestTemplateUnitFieldStartMarker01';
		} );
		$this->setTemporaryHook( 'PageForms::TemplateFieldEnd', static function ( $field, &$fieldEnd ) {
			$fieldEnd = 'PFTestTemplateUnitFieldEndMarker01';
		} );

		$field = TemplateField::create( 'PFTestTemplateUnitField01', 'PFTestTemplateUnitField01' );
		$template = new Template( 'PFTestTemplateUnitHooked01', [ $field ] );

		$text = $template->createTextForField( $field );

		$this->assertStringContainsString( 'PFTestTemplateUnitFieldStartMarker01', $text );
		$this->assertStringContainsString( 'PFTestTemplateUnitFieldEndMarker01', $text );
	}

	public function testCreateTextPlainFormatWithNonemptyDisplayField() {
		$field = TemplateField::create(
			'PFTestTemplateUnitSubtitle01', 'PFTestTemplateUnitSubtitle01', null, false, null, 'nonempty'
		);
		$template = new Template( 'PFTestTemplateUnitPlainNonempty01', [ $field ] );
		$template->setFormat( 'plain' );

		$text = $template->createText();

		$this->assertStringContainsString( "'''PFTestTemplateUnitSubtitle01:'''", $text );
		$this->assertStringContainsString( '{{#if:', $text );
	}

	public function testCreateTextSectionsFormatWithNonemptyDisplayField() {
		$field = TemplateField::create(
			'PFTestTemplateUnitSection01', 'PFTestTemplateUnitSection01', null, false, null, 'nonempty'
		);
		$template = new Template( 'PFTestTemplateUnitSectionsNonempty01', [ $field ] );
		$template->setFormat( 'sections' );

		$text = $template->createText();

		$this->assertStringContainsString( '==PFTestTemplateUnitSection01==', $text );
		$this->assertStringContainsString( '{{#if:', $text );
	}

	/**
	 * Two standard-format fields with display=nonempty: the second one
	 * (index > 0) must emit the "{{!}}-" row separator inside the #if wrapper.
	 */
	public function testCreateTextStandardFormatNonemptySecondFieldAddsRowSeparator() {
		$fields = [
			TemplateField::create( 'PFTestTemplateUnitFirst01', 'PFTestTemplateUnitFirst01', null, false, null, 'nonempty' ),
			TemplateField::create( 'PFTestTemplateUnitSecond01', 'PFTestTemplateUnitSecond01', null, false, null, 'nonempty' ),
		];
		$template = new Template( 'PFTestTemplateUnitStandardNonempty01', $fields );

		$text = $template->createText();

		$this->assertStringContainsString( '{{!}}-', $text );
	}

	/**
	 * A field with a semantic property and internalObjText set (via
	 * setConnectingProperty) is appended to the #subobject call rather than
	 * to a top-level #set call.
	 */
	public function testCreateTextConnectingPropertyAppendsListFieldToSubobject() {
		$field = TemplateField::create(
			'PFTestTemplateUnitListField01', 'PFTestTemplateUnitListField01', 'PFTestTemplateUnitHasList01', true
		);
		$template = new Template( 'PFTestTemplateUnitSubobjectList01', [ $field ] );
		$template->setConnectingProperty( 'PFTestTemplateUnitHasConn01' );

		$text = $template->createText();

		$this->assertStringContainsString( '{{#subobject:', $text );
		$this->assertStringContainsString( 'PFTestTemplateUnitHasList01=', $text );
		$this->assertStringContainsString( '|+sep=,', $text );
	}

	/**
	 * A field with a semantic property and internalObjText set (via
	 * setConnectingProperty) that is not a list is appended as a plain
	 * property assignment (not "#list=").
	 */
	public function testCreateTextConnectingPropertyAppendsNonListFieldToSubobject() {
		$field = TemplateField::create(
			'PFTestTemplateUnitSingleField01', 'PFTestTemplateUnitSingleField01', 'PFTestTemplateUnitHasSingle01', false
		);
		$template = new Template( 'PFTestTemplateUnitSubobjectSingle01', [ $field ] );
		$template->setConnectingProperty( 'PFTestTemplateUnitHasConn02' );

		$text = $template->createText();

		$this->assertStringContainsString( '{{#subobject:', $text );
		$this->assertStringContainsString( 'PFTestTemplateUnitHasSingle01=', $text );
		$this->assertStringNotContainsString( 'PFTestTemplateUnitHasSingle01#list=', $text );
	}

	/**
	 * A field with display=hidden and a semantic property (no connecting
	 * property set) is routed into a top-level #set call instead of the
	 * table body.
	 */
	public function testCreateTextHiddenFieldWithListPropertyUsesSetList() {
		$field = TemplateField::create(
			'PFTestTemplateUnitHiddenList01', 'PFTestTemplateUnitHiddenList01', 'PFTestTemplateUnitHasHidden01', true, null, 'hidden'
		);
		$template = new Template( 'PFTestTemplateUnitHiddenSet01', [ $field ] );

		$text = $template->createText();

		$this->assertStringContainsString( '{{#set:', $text );
		$this->assertStringContainsString( 'PFTestTemplateUnitHasHidden01#list=', $text );
	}

	public function testCreateTextHiddenFieldWithNonListPropertyUsesSet() {
		$field = TemplateField::create(
			'PFTestTemplateUnitHiddenSingle01', 'PFTestTemplateUnitHiddenSingle01', 'PFTestTemplateUnitHasHiddenSingle01', false,
			null, 'hidden'
		);
		$template = new Template( 'PFTestTemplateUnitHiddenSetSingle01', [ $field ] );

		$text = $template->createText();

		$this->assertStringContainsString( '{{#set:', $text );
		$this->assertStringContainsString( 'PFTestTemplateUnitHasHiddenSingle01=', $text );
		$this->assertStringNotContainsString( 'PFTestTemplateUnitHasHiddenSingle01#list=', $text );
	}

	/**
	 * A field with a semantic property, display=nonempty and no connecting
	 * property emits the value inside the #if wrapper's closing braces.
	 */
	public function testCreateTextNonemptyFieldWithPropertyClosesIfWrapper() {
		$field = TemplateField::create(
			'PFTestTemplateUnitNonemptyProp01', 'PFTestTemplateUnitNonemptyProp01', 'PFTestTemplateUnitHasNonemptyProp01',
			false, null, 'nonempty'
		);
		$template = new Template( 'PFTestTemplateUnitNonemptyPropTpl01', [ $field ] );

		$text = $template->createText();

		$this->assertStringContainsString( '{{!}} ', $text );
		$this->assertStringContainsString( "\n}}\n", $text );
	}

	/**
	 * A field with a semantic property, display=nonempty and a connecting
	 * property (internalObjText set) prints the "{{!}}" separator before
	 * the field value, since the header column already emitted "{{!}}-".
	 */
	public function testCreateTextConnectingPropertyNonemptyFieldUsesSeparator() {
		$field = TemplateField::create(
			'PFTestTemplateUnitConnNonempty01', 'PFTestTemplateUnitConnNonempty01', 'PFTestTemplateUnitHasConnNonempty01',
			false, null, 'nonempty'
		);
		$template = new Template( 'PFTestTemplateUnitConnNonemptyTpl01', [ $field ] );
		$template->setConnectingProperty( 'PFTestTemplateUnitHasConnMarker01' );

		$text = $template->createText();

		$this->assertStringContainsString( '{{!}} ', $text );
		$this->assertStringContainsString( '{{#subobject:', $text );
	}

	/**
	 * A field with a semantic property and no special display value falls
	 * through to the plain "value\n" branch (no #if wrapper, no #set call).
	 */
	public function testCreateTextFieldWithPropertyAndDefaultDisplay() {
		$field = TemplateField::create(
			'PFTestTemplateUnitDefaultDisplay01', 'PFTestTemplateUnitDefaultDisplay01', 'PFTestTemplateUnitHasDefault01'
		);
		$template = new Template( 'PFTestTemplateUnitDefaultDisplayTpl01', [ $field ] );

		$text = $template->createText();

		$this->assertStringContainsString( '[[PFTestTemplateUnitHasDefault01::', $text );
		$this->assertStringNotContainsString( '{{#set:', $text );
	}

	/**
	 * The aggregating-property block adapts its label markup per format:
	 * plain uses bold text, sections uses a heading.
	 */
	public function testCreateTextAggregatingPropertyPlainFormat() {
		$template = new Template( 'PFTestTemplateUnitAggPlain01', [] );
		$template->setFormat( 'plain' );
		$template->setAggregatingInfo( 'PFTestTemplateUnitHasAgg01', 'PFTestTemplateUnitAggLabel01' );

		$text = $template->createText();

		$this->assertStringContainsString( "'''PFTestTemplateUnitAggLabel01:'''", $text );
	}

	public function testCreateTextAggregatingPropertySectionsFormat() {
		$template = new Template( 'PFTestTemplateUnitAggSections01', [] );
		$template->setFormat( 'sections' );
		$template->setAggregatingInfo( 'PFTestTemplateUnitHasAgg02', 'PFTestTemplateUnitAggLabel02' );

		$text = $template->createText();

		$this->assertStringContainsString( '==PFTestTemplateUnitAggLabel02==', $text );
	}

	/**
	 * The #template_params header (non-fullWikiText path) skips fields
	 * with a blank name instead of emitting a bare "|" separator for them.
	 */
	public function testCreateTextTemplateParamsHeaderSkipsBlankFieldName() {
		$fields = [
			TemplateField::create( '', null ),
			TemplateField::create( 'PFTestTemplateUnitParamsHeader01', 'PFTestTemplateUnitParamsHeader01' ),
		];
		$template = new Template( 'PFTestTemplateUnitParamsHeaderTpl01', $fields );

		$text = $template->createText();

		$this->assertStringContainsString( 'PFTestTemplateUnitParamsHeader01', $text );
		$this->assertStringNotContainsString( '||', $text );
	}

	private function insertTemplatePageWithParamsProp(
		string $pageName, string $serializedParams, string $templateContent = 'Template content.'
	): Title {
		$title = Title::makeTitle( NS_TEMPLATE, $pageName );
		$this->editPage( $title, $templateContent );

		$pageId = $title->getArticleID( IDBAccessObject::READ_LATEST );
		if ( version_compare( MW_VERSION, '1.41', '>=' ) ) {
			$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		} else {
			// MW < 1.41: getConnectionProvider() did not exist; use getDBLoadBalancer()
			$dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_PRIMARY );
		}
		$dbw->upsert(
			'page_props',
			[ 'pp_page' => $pageId, 'pp_propname' => 'PageFormsTemplateParams', 'pp_value' => $serializedParams ],
			[ [ 'pp_page', 'pp_propname' ] ],
			[ 'pp_value' => $serializedParams ],
			__METHOD__
		);

		return $title;
	}
}
