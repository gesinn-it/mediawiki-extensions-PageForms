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

	public function testPlusModifierReturnPageValueWhenAlreadyPresentInMiddle(): void {
		$result = $this->resolver->applyValModifier( 'B', '+', 'A,B,C', ',' );
		$this->assertSame( 'A,B,C', $result );
	}

	public function testPlusModifierDetectsFirstElementAsAlreadyPresent(): void {
		// Bug fix: the old regex #(,|\^)...# used literal-caret not start-anchor,
		// so 'apple' at the start of 'apple,banana' was not detected as already present.
		$result = $this->resolver->applyValModifier( 'apple', '+', 'apple,banana', ',' );
		$this->assertSame( 'apple,banana', $result );
	}

	public function testPlusModifierDetectsLastElementAsAlreadyPresent(): void {
		$result = $this->resolver->applyValModifier( 'C', '+', 'A,B,C', ',' );
		$this->assertSame( 'A,B,C', $result );
	}

	public function testPlusModifierHandlesRegexMetacharsInCurValue(): void {
		// Bug fix: curValue was interpolated into regex without preg_quote().
		// 'foo(bar)' contains unbalanced parens that would break the regex.
		$result = $this->resolver->applyValModifier( 'foo(bar)', '+', 'alpha', ',' );
		$this->assertSame( 'alpha,foo(bar)', $result );
	}

	public function testPlusModifierDoesNotDuplicateWhenCurValueContainsDot(): void {
		// 'foo.bar' — dot is a regex metachar; without preg_quote it matches any char.
		$result = $this->resolver->applyValModifier( 'foo.bar', '+', 'foo.bar,other', ',' );
		$this->assertSame( 'foo.bar,other', $result );
	}

	public function testMinusModifierUsesDelimiterForCurValue(): void {
		// Bug fix: minus modifier now splits curValue by $delimiter, not hardcoded comma.
		$result = $this->resolver->applyValModifier( 'B;C', '-', 'A;B;C;D', ';' );
		$this->assertSame( 'A;D', $result );
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
		// Both slots must be set to the same non-empty date string
		$this->assertNotEmpty( $cvt );
		$this->assertNotSame( 'now', $cvt );
		$this->assertSame( $cvt, $cv );
	}

	public function testResolveNowForDatetimeInputType(): void {
		$formField = $this->makeFormFieldWithDefault( 'now' );
		$formField->setInputType( 'datetime' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertNotEmpty( $cvt );
		$this->assertSame( $cvt, $cv );
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
		$this->assertSame( $cvt, $cv );
	}

	public function testResolveNowSkippedForDatepickerType(): void {
		// 'datepicker' handles 'now' itself — resolveDefaultValue must leave both slots unchanged
		$formField = $this->makeFormFieldWithDefault( 'now' );
		$formField->setInputType( 'datepicker' );
		$user = $this->getTestUser()->getUser();

		[ $cv, $cvt ] = $this->resolver->resolveDefaultValue(
			$formField, '', '', false, $user
		);
		$this->assertSame( '', $cv );
		$this->assertSame( '', $cvt );
	}

	public function testResolveUuidMultipleInstanceMergesExistingClass(): void {
		$formField = $this->makeFormFieldWithDefault( 'uuid' );
		$formField->setFieldArg( 'class', 'important-field' );
		$user = $this->getTestUser()->getUser();

		$this->resolver->resolveDefaultValue( $formField, '', '', true, $user );

		$this->assertSame( 'important-field new-uuid', $formField->getFieldArg( 'class' ) );
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
