<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms\Tests\Integration;

use MediaWiki\Extension\PageForms\FieldValueResolver;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FieldValueResolver
 * @group Database
 */
class FieldValueResolverTest extends MediaWikiIntegrationTestCase {

	private FieldValueResolver $resolver;

	protected function setUp(): void {
		parent::setUp();
		$this->resolver = new FieldValueResolver();
	}

	// ------------------------------------------------------------------ applyValModifier

	public function testPlusModifierAppendsWhenNotPresent(): void {
		$result = $this->resolver->applyValModifier( 'B', '+', 'A', ',' );
		$this->assertSame( 'A,B', $result );
	}

	public function testPlusModifierSkipsAppendWhenPageValueEmpty(): void {
		$result = $this->resolver->applyValModifier( 'B', '+', '', ',' );
		$this->assertSame( 'B', $result );
	}

	public function testPlusModifierReturnPageValueWhenAlreadyPresent(): void {
		// 'B' already contained in page_value; the regex matches ^,B$ or ^B,
		$result = $this->resolver->applyValModifier( 'B', '+', 'A,B,C', ',' );
		$this->assertSame( 'A,B,C', $result );
	}

	public function testMinusModifierRemovesSingleValue(): void {
		$result = $this->resolver->applyValModifier( 'B', '-', 'A,B,C', ',' );
		$this->assertSame( 'A,C', $result );
	}

	public function testMinusModifierRemovesMultipleValues(): void {
		$result = $this->resolver->applyValModifier( 'B,C', '-', 'A,B,C,D', ',' );
		$this->assertSame( 'A,D', $result );
	}

	public function testMinusModifierReturnsHackStringWhenResultEmpty(): void {
		$result = $this->resolver->applyValModifier( 'A', '-', 'A', ',' );
		$this->assertSame( '{{subst:lc: }}', $result );
	}

	public function testUnknownModifierReturnsOriginalCurValue(): void {
		// Any modifier other than '+'/'-' falls through and returns $curValue unchanged.
		$result = $this->resolver->applyValModifier( 'X', '?', 'A,B', ',' );
		$this->assertSame( 'X', $result );
	}

	public function testPlusModifierUsesCustomDelimiter(): void {
		$result = $this->resolver->applyValModifier( 'B', '+', 'A', ';' );
		$this->assertSame( 'A;B', $result );
	}

	// ------------------------------------------------------------------ resolveDefaultValue

	/**
	 * resolveDefaultValue() for an unrecognised default token must return inputs unchanged.
	 */
	public function testResolveDefaultValueUnknownTokenIsNoop(): void {
		$formField = $this->makeFormFieldWithDefault( 'static-value' );
		$user = $this->createMock( \User::class );

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, 'existing', 'existing-template', false, $user
		);
		// 'static-value' is not a magic token, so nothing changes
		$this->assertSame( 'existing', $cv );
		$this->assertSame( 'existing-template', $cvt );
	}

	public function testResolveCurrentUserRegistered(): void {
		$user = $this->getTestUser()->getUser();
		$formField = $this->makeFormFieldWithDefault( 'current user' );

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertSame( $user->getName(), $cv );
		$this->assertSame( $user->getName(), $cvt );
	}

	public function testResolveCurrentUserAnonymousGivesEmpty(): void {
		$user = $this->getServiceContainer()->getUserFactory()->newAnonymous();
		$formField = $this->makeFormFieldWithDefault( 'current user' );

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertSame( '', $cv );
		$this->assertSame( '', $cvt );
	}

	public function testResolveCurrentUserNotAppliedWhenCurValueAlreadySet(): void {
		$user = $this->getTestUser()->getUser();
		$formField = $this->makeFormFieldWithDefault( 'current user' );

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, 'alice', 'alice', false, $user
		);
		// cur_value is not '' or 'current user', so no substitution happens
		$this->assertSame( 'alice', $cv );
	}

	public function testResolveUuidSingleInstance(): void {
		$formField = $this->makeFormFieldWithDefault( 'uuid' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		// UUID format: 8-4-4-4-12 hex chars
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
		if ( method_exists( $this, 'assertMatchesRegularExpression' ) ) {
			$this->assertMatchesRegularExpression( $pattern, $cv );
		} else {
			$this->assertRegExp( $pattern, $cv );
		}
		$this->assertSame( $cv, $cvt );
	}

	public function testResolveUuidMultipleInstanceAddsClass(): void {
		$formField = $this->makeFormFieldWithDefault( 'uuid' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', true, $user
		);
		// No UUID generated — JS handles it; class arg set instead
		$this->assertSame( '', $cv );
		$this->assertSame( 'new-uuid', $formField->getFieldArg( 'class' ) );
	}

	public function testResolveNowForDateInputType(): void {
		$formField = $this->makeFormFieldWithDefault( 'now' );
		$formField->setInputType( 'date' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		// The date string should be non-empty and not equal to 'now'
		$this->assertNotEmpty( $cvt );
		$this->assertNotSame( 'now', $cvt );
	}

	public function testResolveNowForDatetimeInputType(): void {
		$formField = $this->makeFormFieldWithDefault( 'now' );
		$formField->setInputType( 'datetime' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertNotEmpty( $cvt );
	}

	public function testResolveNowForDatPropertyTypeWithEmptyInputType(): void {
		$templateField = new \PFTemplateField();
		$propTypeRef = new \ReflectionProperty( \PFTemplateField::class, 'mPropertyType' );
		$propTypeRef->setAccessible( true );
		$propTypeRef->setValue( $templateField, '_dat' );

		$formField = \PFFormField::create( $templateField );
		$defaultRef = new \ReflectionProperty( \PFFormField::class, 'mDefaultValue' );
		$defaultRef->setAccessible( true );
		$defaultRef->setValue( $formField, 'now' );

		$user = $this->getTestUser()->getUser();
		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertNotEmpty( $cvt );
	}

	public function testResolveNowSkippedForDatepickerType(): void {
		// 'datepicker' handles 'now' itself — resolveDefaultValue must leave it alone
		$formField = $this->makeFormFieldWithDefault( 'now' );
		$formField->setInputType( 'datepicker' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		// $cvt must remain unchanged (empty string passed in)
		$this->assertSame( '', $cvt );
	}

	// ------------------------------------------------------------------ helpers

	/**
	 * Creates a minimal PFFormField with a given default value.
	 * mDefaultValue is private; use reflection to set it as the only setter
	 * is buried inside the form-definition parser.
	 */
	private function makeFormFieldWithDefault( string $defaultValue ): \PFFormField {
		$formField = \PFFormField::create( new \PFTemplateField() );
		$ref = new \ReflectionProperty( \PFFormField::class, 'mDefaultValue' );
		$ref->setAccessible( true );
		$ref->setValue( $formField, $defaultValue );
		return $formField;
	}
}
