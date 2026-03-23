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

	public function testSetIsMandatory() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setIsMandatory( true );
		$this->assertTrue( $ps->isMandatory() );
	}

	public function testSetIsHidden() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setIsHidden( true );
		$this->assertTrue( $ps->isHidden() );
	}

	public function testSetIsRestricted() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setIsRestricted( true );
		$this->assertTrue( $ps->isRestricted() );
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

	public function testNewFromFormTagMandatory() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'mandatory' ],
			$this->makeUser()
		);
		$this->assertTrue( $ps->isMandatory() );
	}

	public function testNewFromFormTagHidden() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'hidden' ],
			$this->makeUser()
		);
		$this->assertTrue( $ps->isHidden() );
	}

	public function testNewFromFormTagRestrictedUserWithPermission() {
		// User has editrestrictedfields → section is NOT restricted (mIsRestricted = false)
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'restricted' ],
			$this->makeUser( true )
		);
		$this->assertFalse( $ps->isRestricted() );
	}

	public function testNewFromFormTagRestrictedUserWithoutPermission() {
		// User lacks editrestrictedfields → section IS restricted
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'restricted' ],
			$this->makeUser( false )
		);
		$this->assertTrue( $ps->isRestricted() );
	}

	public function testNewFromFormTagHideIfEmpty() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'hide if empty' ],
			$this->makeUser()
		);
		$this->assertTrue( $ps->isHideIfEmpty() );
	}

	public function testNewFromFormTagAutogrow() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'autogrow' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'autogrow' => true ], $ps->getSectionArgs() );
	}

	public function testNewFromFormTagLevel() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'level=3' ],
			$this->makeUser()
		);
		$this->assertSame( '3', $ps->getSectionLevel() );
	}

	public function testNewFromFormTagRows() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'rows=10' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'rows' => '10' ], $ps->getSectionArgs() );
	}

	public function testNewFromFormTagCols() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'cols=80' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'cols' => '80' ], $ps->getSectionArgs() );
	}

	public function testNewFromFormTagClass() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'class=my-class' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'class' => 'my-class' ], $ps->getSectionArgs() );
	}

	public function testNewFromFormTagEditor() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'editor=wikieditor' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'editor' => 'wikieditor' ], $ps->getSectionArgs() );
	}

	public function testNewFromFormTagPlaceholder() {
		$ps = PFPageSection::newFromFormTag(
			[ 'section', 'Overview', 'placeholder=Enter text here' ],
			$this->makeUser()
		);
		$this->assertSame( [ 'placeholder' => 'Enter text here' ], $ps->getSectionArgs() );
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

	public function testCreateMarkupBasic() {
		$ps = PFPageSection::create( 'Introduction' );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '==Introduction==', $markup );
		$this->assertStringContainsString( '{{{section|Introduction|level=2}}}', $markup );
	}

	public function testCreateMarkupLevel3() {
		$ps = PFPageSection::create( 'Details' );
		$ps->setSectionLevel( 3 );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '===Details===', $markup );
		$this->assertStringContainsString( '{{{section|Details|level=3}}}', $markup );
	}

	public function testCreateMarkupEmptyLevelDefaultsTo2() {
		$ps = PFPageSection::create( 'Intro' );
		$ps->setSectionLevel( '' );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '==Intro==', $markup );
		$this->assertStringContainsString( 'level=2', $markup );
	}

	public function testCreateMarkupMandatory() {
		$ps = PFPageSection::create( 'Required' );
		$ps->setIsMandatory( true );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '|mandatory', $markup );
	}

	public function testCreateMarkupRestricted() {
		$ps = PFPageSection::create( 'Restricted' );
		$ps->setIsRestricted( true );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '|restricted', $markup );
	}

	public function testCreateMarkupHidden() {
		$ps = PFPageSection::create( 'Hidden' );
		$ps->setIsHidden( true );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '|hidden', $markup );
	}

	public function testCreateMarkupWithStringArg() {
		$ps = PFPageSection::create( 'Rows' );
		$ps->setSectionArgs( 'rows', '5' );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '|rows=5', $markup );
	}

	public function testCreateMarkupWithBooleanArg() {
		$ps = PFPageSection::create( 'Autogrow' );
		$ps->setSectionArgs( 'autogrow', true );
		$markup = $ps->createMarkup();

		$this->assertStringContainsString( '|autogrow', $markup );
		$this->assertStringNotContainsString( '|autogrow=', $markup );
	}
}
