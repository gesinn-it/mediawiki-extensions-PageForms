<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers PFPageSection
 */
class PFPageSectionTest extends TestCase {

	// ── create() ──────────────────────────────────────────────────────────

	public function testCreateReturnsPFPageSection() {
		$ps = PFPageSection::create( 'Introduction' );

		$this->assertInstanceOf( PFPageSection::class, $ps );
		$this->assertSame( 'Introduction', $ps->getSectionName() );
	}

	public function testCreateHasDefaultLevel2() {
		$ps = PFPageSection::create( 'Intro' );

		$this->assertSame( 2, $ps->getSectionLevel() );
	}

	public function testCreateHasDefaultsForFlags() {
		$ps = PFPageSection::create( 'Intro' );

		$this->assertFalse( $ps->isMandatory() );
		$this->assertFalse( $ps->isHidden() );
		$this->assertFalse( $ps->isRestricted() );
		$this->assertFalse( $ps->isHideIfEmpty() );
		$this->assertSame( [], $ps->getSectionArgs() );
	}

	// ── Setters / getters ────────────────────────────────────────────────

	public function testSetSectionLevel() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setSectionLevel( 3 );

		$this->assertSame( 3, $ps->getSectionLevel() );
	}

	/**
	 * @dataProvider provideFlagAccessors
	 */
	public function testSetAFlag( string $setter, string $getter ) {
		$ps = PFPageSection::create( 'Intro' );
		$ps->$setter( true );

		$this->assertTrue( $ps->$getter() );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provideFlagAccessors(): array {
		return [
			'mandatory' => [ 'setIsMandatory', 'isMandatory' ],
			'hidden' => [ 'setIsHidden', 'isHidden' ],
			'restricted' => [ 'setIsRestricted', 'isRestricted' ],
		];
	}

	public function testSetSectionArgs() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setSectionArgs( 'rows', '5' );

		$this->assertSame( [ 'rows' => '5' ], $ps->getSectionArgs() );
	}

	// ── newFromFormTag() ─────────────────────────────────────────────────

	private function makeUser( bool $canEdit = true ): User {
		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )
			->with( 'editrestrictedfields' )
			->willReturn( $canEdit );
		return $user;
	}

	public function testNewFromFormTagBasic() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview' ],
			$this->makeUser()
		);

		$this->assertSame( 'Overview', $ps->getSectionName() );
		$this->assertSame( 2, $ps->getSectionLevel() );
		$this->assertFalse( $ps->isMandatory() );
		$this->assertFalse( $ps->isHidden() );
		$this->assertFalse( $ps->isRestricted() );
		$this->assertFalse( $ps->isHideIfEmpty() );
	}

	/**
	 * @dataProvider provideFlagComponents
	 */
	public function testNewFromFormTagReadsAFlag(
		string $component, bool $userMayEditRestricted, string $getter, bool $expected
	) {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', $component ],
			$this->makeUser( $userMayEditRestricted )
		);

		$this->assertSame( $expected, $ps->$getter() );
	}

	/**
	 * @return array<string, array{0: string, 1: bool, 2: string, 3: bool}>
	 */
	public static function provideFlagComponents(): array {
		return [
			'mandatory' => [ 'mandatory', true, 'isMandatory', true ],
			'hidden' => [ 'hidden', true, 'isHidden', true ],
			'hide if empty' => [ 'hide if empty', true, 'isHideIfEmpty', true ],
			// A user who has editrestrictedfields may edit the section, so it is not restricted for them.
			'restricted, user with permission' => [ 'restricted', true, 'isRestricted', false ],
			'restricted, user without permission' => [ 'restricted', false, 'isRestricted', true ],
		];
	}

	/**
	 * @dataProvider provideArgumentComponents
	 * @param string $component
	 * @param array $expectedArgs
	 */
	public function testNewFromFormTagReadsAnArgument( string $component, array $expectedArgs ) {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', $component ],
			$this->makeUser()
		);

		$this->assertSame( $expectedArgs, $ps->getSectionArgs() );
	}

	/**
	 * @return array<string, array{0: string, 1: array}>
	 */
	public static function provideArgumentComponents(): array {
		return [
			'autogrow' => [ 'autogrow', [ 'autogrow' => true ] ],
			'rows' => [ 'rows=10', [ 'rows' => '10' ] ],
			'cols' => [ 'cols=80', [ 'cols' => '80' ] ],
			'class' => [ 'class=my-class', [ 'class' => 'my-class' ] ],
			'editor' => [ 'editor=wikieditor', [ 'editor' => 'wikieditor' ] ],
			'placeholder' => [ 'placeholder=Enter text here', [ 'placeholder' => 'Enter text here' ] ],
		];
	}

	public function testNewFromFormTagLevel() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'level=3' ],
			$this->makeUser()
		);

		$this->assertSame( '3', $ps->getSectionLevel() );
	}

	public function testNewFromFormTagUnknownComponentIsIgnored() {
		// An unknown key=value component must not throw or set unexpected args
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'unknownkey=somevalue' ],
			$this->makeUser()
		);

		$this->assertSame( [], $ps->getSectionArgs() );
		$this->assertFalse( $ps->isMandatory() );
	}

	// ── createMarkup() ───────────────────────────────────────────────────

}
