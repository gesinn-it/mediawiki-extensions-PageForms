<?php

use MediaWiki\Extension\PageForms\AutoeditFormResolver;

/**
 * @covers \MediaWiki\Extension\PageForms\AutoeditFormResolver
 * @group PF
 * @group Database
 */
class AutoeditFormResolverTest extends MediaWikiIntegrationTestCase {

	private function newResolver(): AutoeditFormResolver {
		return new AutoeditFormResolver( RequestContext::getMain() );
	}

	private function insertForm( string $name, string $text = '{{{standard input|save}}}' ): Title {
		return $this->insertPage( Title::makeTitle( PF_NS_FORM, $name ), $text )['title'];
	}

	// -------------------------------------------------------------------------
	// findDefaultFormName
	// -------------------------------------------------------------------------

	public function testTheOnlyAlternativeFormIsTheForm(): void {
		$this->assertSame(
			'AEResolverAltForm',
			$this->newResolver()->findDefaultFormName( '', [ 'AEResolverAltForm' ] )
		);
	}

	public function testAnAlternativeFormNeedsNoTarget(): void {
		$this->assertSame(
			'AEResolverAltForm',
			$this->newResolver()->findDefaultFormName( 'AEResolverTarget', [ 5 => 'AEResolverAltForm' ] )
		);
	}

	public function testSeveralAlternativeFormsAreRefusedAsADebugMessage(): void {
		try {
			$this->newResolver()->findDefaultFormName( 'AEResolverTarget', [ 'AEResolverFormA', 'AEResolverFormB' ] );
			$this->fail( 'Expected an MWException' );
		} catch ( MWException $e ) {
			$this->assertSame( PFAutoeditAPI::DEBUG, $e->getCode() );
			$this->assertSame( wfMessage( 'pf_autoedit_toomanyformsfound' )->parse(), $e->getMessage() );
		}
	}

	public function testTheDefaultFormOfTheTargetIsTheForm(): void {
		$this->insertPage( 'AEResolverTargetWithDefault', '{{#default_form:AEResolverDefaultForm}}' );

		$this->assertSame(
			'AEResolverDefaultForm',
			$this->newResolver()->findDefaultFormName( 'AEResolverTargetWithDefault', [] )
		);
	}

	public function testSeveralDefaultFormsOfTheTargetAreRefused(): void {
		// Several default forms come from the categories of the target.
		foreach ( [ 'A', 'B' ] as $letter ) {
			$this->insertPage(
				Title::makeTitleSafe( NS_CATEGORY, "AEResolverCat$letter" ),
				"{{#default_form:AEResolverForm$letter}}"
			);
		}
		$this->insertPage(
			'AEResolverTargetWithTwoDefaults',
			"[[Category:AEResolverCatA]]\n[[Category:AEResolverCatB]]"
		);

		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_toomanyformsfound' )->parse() );

		$this->newResolver()->findDefaultFormName( 'AEResolverTargetWithTwoDefaults', [] );
	}

	public function testATargetWithoutADefaultFormHasNoForm(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_noformfound' )->parse() );

		$this->newResolver()->findDefaultFormName( 'AEResolverTargetWithoutDefault', [] );
	}

	public function testWithoutAFormAndATargetThereIsNothingToFind(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_notargetspecified' )->parse() );

		$this->newResolver()->findDefaultFormName( '', [] );
	}

	public function testATargetThatIsNoTitleIsRefused(): void {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_invalidtargetspecified', 'AEResolver[Bad]' )->parse() );

		$this->newResolver()->findDefaultFormName( 'AEResolver[Bad]', [] );
	}

	// -------------------------------------------------------------------------
	// followRedirects
	// -------------------------------------------------------------------------

	public function testFollowRedirectsLeadsToTheFormTheRedirectChainEndsAt(): void {
		$this->insertForm( 'AEResolverRedirectFinal' );
		$this->insertForm( 'AEResolverRedirectMiddle', '#REDIRECT [[Form:AEResolverRedirectFinal]]' );
		$start = $this->insertForm( 'AEResolverRedirectStart', '#REDIRECT [[Form:AEResolverRedirectMiddle]]' );

		$title = $this->newResolver()->followRedirects( $start, 'AEResolverRedirectStart' );

		$this->assertSame( 'AEResolverRedirectFinal', $title->getText() );
	}

	public function testFollowRedirectsGivesUpOnALoop(): void {
		$a = $this->insertForm( 'AEResolverLoopA', '#REDIRECT [[Form:AEResolverLoopB]]' );
		$this->insertForm( 'AEResolverLoopB', '#REDIRECT [[Form:AEResolverLoopA]]' );

		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_redirectlimitexeeded', 'AEResolverLoopA' )->parse() );

		$this->newResolver()->followRedirects( $a, 'AEResolverLoopA' );
	}

	public function testFollowRedirectsGivesUpOnAChainThatIsTooLong(): void {
		$this->insertForm( 'AEResolverLongFinal' );
		$next = 'AEResolverLongFinal';
		// Six redirects in front of the form: one more than are followed.
		for ( $i = 1; $i <= 6; $i++ ) {
			$title = $this->insertForm( "AEResolverLong$i", "#REDIRECT [[Form:$next]]" );
			$next = "AEResolverLong$i";
		}

		$this->expectException( MWException::class );
		$this->expectExceptionMessage( wfMessage( 'pf_autoedit_redirectlimitexeeded', 'AEResolverLong6' )->parse() );

		$this->newResolver()->followRedirects( $title, 'AEResolverLong6' );
	}

	public function testFollowRedirectsLeavesAFormThatIsNoRedirectAlone(): void {
		$form = $this->insertForm( 'AEResolverNoRedirect' );

		$this->assertSame( $form, $this->newResolver()->followRedirects( $form, 'AEResolverNoRedirect' ) );
	}
}
