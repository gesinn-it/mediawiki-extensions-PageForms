<?php

use MediaWiki\Extension\PageForms\FormSectionHtmlBuilder;
use OOUI\BlankTheme;

/**
 * @covers \MediaWiki\Extension\PageForms\FormSectionHtmlBuilder
 * @group Database
 */
class FormSectionHtmlBuilderTest extends MediaWikiIntegrationTestCase {

	private FormSectionHtmlBuilder $builder;
	private User $user;

	protected function setUp(): void {
		parent::setUp();
		\OOUI\Theme::setSingleton( new BlankTheme() );
		$this->builder = new FormSectionHtmlBuilder();
		$this->user = $this->getTestUser()->getUser();

		global $wgPageFormsFieldNum, $wgPageFormsTabIndex;
		$wgPageFormsFieldNum = 1;
		$wgPageFormsTabIndex = 1;
	}

	// ── Helper ────────────────────────────────────────────────────────────────

	private function buildHtml(
		array $tagComponents,
		string $formDefSection = '',
		int $bracketsEndLoc = 0,
		bool $sourceIsPage = false,
		?string &$existingContent = null,
		array $requestData = [],
		bool $formIsDisabled = false
	): string {
		$request = new FauxRequest( $requestData );
		$wikiPage = new PFWikiPage();
		$ref = &$existingContent;
		return $this->builder->buildHtml(
			$tagComponents,
			$formDefSection,
			$bracketsEndLoc,
			$sourceIsPage,
			$ref,
			$request,
			$wikiPage,
			$formIsDisabled,
			$this->user
		);
	}

	// ── Request path (source_is_page = false) ─────────────────────────────────

	public function testRequestPathProducesTextarea(): void {
		$html = $this->buildHtml( [ 'section', 'Background', 'level=2' ] );

		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'name="_section[Background]"', $html );
	}

	public function testRequestPathWithValueFromRequest(): void {
		$html = $this->buildHtml(
			[ 'section', 'Background', 'level=2' ],
			requestData: [ '_section' => [ 'Background' => 'Some content here' ] ]
		);

		$this->assertStringContainsString( 'Some content here', $html );
	}

	public function testRequestPathMissingKeyProducesEmptyTextarea(): void {
		$html = $this->buildHtml( [ 'section', 'Background', 'level=2' ] );

		$this->assertStringContainsString( '<textarea', $html );
		// no value between tags
		$this->assertStringContainsString( '></textarea>', $html );
	}

	// ── hidden attribute ─────────────────────────────────────────────────────

	public function testHiddenSectionProducesHiddenInput(): void {
		$html = $this->buildHtml( [ 'section', 'Notes', 'level=2', 'hidden' ] );

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'type="hidden"', $html );
		$this->assertStringContainsString( 'name="_section[Notes]"', $html );
		$this->assertStringNotContainsString( '<textarea', $html );
	}

	// ── mandatory attribute ───────────────────────────────────────────────────

	public function testMandatorySectionHasMandatoryClass(): void {
		$html = $this->buildHtml( [ 'section', 'Required', 'level=2', 'mandatory' ] );

		$this->assertStringContainsString( 'mandatoryFieldSpan', $html );
	}

	// ── restricted / disabled ─────────────────────────────────────────────────

	public function testRestrictedSectionProducesDisabledTextarea(): void {
		// User does not have editrestrictedfields → isRestricted() = true
		$restrictedUser = $this->createMock( User::class );
		$restrictedUser->method( 'isAllowed' )->willReturn( false );

		$request = new FauxRequest();
		$wikiPage = new PFWikiPage();
		$existingContent = null;

		$html = $this->builder->buildHtml(
			[ 'section', 'Private', 'level=2', 'restricted' ],
			'',
			0,
			false,
			$existingContent,
			$request,
			$wikiPage,
			false,
			$restrictedUser
		);

		$this->assertStringContainsString( 'disabled', $html );
	}

	public function testFormDisabledProducesDisabledTextarea(): void {
		$html = $this->buildHtml(
			[ 'section', 'Intro', 'level=2' ],
			formIsDisabled: true
		);

		$this->assertStringContainsString( 'disabled', $html );
	}

	// ── source_is_page path ───────────────────────────────────────────────────

	public function testSourceIsPageExtractsSectionText(): void {
		$existingContent = "== Background ==\nThis is the background text.\n";
		$html = $this->buildHtml(
			[ 'section', 'Background', 'level=2' ],
			sourceIsPage: true,
			existingContent: $existingContent
		);

		$this->assertStringContainsString( 'This is the background text.', $html );
	}

	public function testSourceIsPageRemovesExtractedTextFromContent(): void {
		// Two sections in both page content and form definition so the look-ahead
		// can find the boundary and stop extraction before "Other".
		$existingContent = "== Background ==\nBackground text.\n== Other ==\nOther text.\n";
		$formDef = ' {{{section|Background|level=2}}}{{{section|Other|level=2}}}';
		$firstTagEnd = strpos( $formDef, '}}}' ) + 3;

		$request = new FauxRequest();
		$wikiPage = new PFWikiPage();

		$this->builder->buildHtml(
			[ 'section', 'Background', 'level=2' ],
			$formDef,
			$firstTagEnd,
			true,
			$existingContent,
			$request,
			$wikiPage,
			false,
			$this->user
		);

		// The Background section and its header must be gone; Other section must remain
		$this->assertStringNotContainsString( 'Background text.', $existingContent );
		$this->assertStringContainsString( 'Other', $existingContent );
	}

	public function testSourceIsPageWithNoMatchUsesEntireRemainingContent(): void {
		// When the section header is not found, section_start_loc = 0, so the entire
		// remaining page content is used as the section text (existing behaviour).
		$existingContent = "Some other content without the section.\n";
		$html = $this->buildHtml(
			[ 'section', 'MissingSection', 'level=2' ],
			sourceIsPage: true,
			existingContent: $existingContent
		);

		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'Some other content without the section.', $html );
	}

	public function testSourceIsPageAppendsTrailingNewlineIfMissing(): void {
		// Content without trailing newline — T72202
		$existingContent = "== Background ==\nBackground text.";
		$html = $this->buildHtml(
			[ 'section', 'Background', 'level=2' ],
			sourceIsPage: true,
			existingContent: $existingContent
		);

		$this->assertStringContainsString( 'Background text.', $html );
	}

	// ── look-ahead: next section boundary ────────────────────────────────────

	public function testLookAheadStopsAtNextSectionInPageContent(): void {
		// Two sections in the page; form has two section tags.
		// When extracting "Intro", the look-ahead must stop at "== Details =="
		// so that "Details text" is NOT included in the Intro textarea.
		$existingContent = "== Intro ==\nIntro text.\n== Details ==\nDetails text.\n";

		// form_def_section contains both section tags after the current one
		$formDef = ' {{{section|Intro|level=2}}}{{{section|Details|level=2}}}';
		// brackets_end_loc points past the end of the first tag
		$firstTagEnd = strpos( $formDef, '}}}' ) + 3;

		$request = new FauxRequest();
		$wikiPage = new PFWikiPage();

		$html = $this->builder->buildHtml(
			[ 'section', 'Intro', 'level=2' ],
			$formDef,
			$firstTagEnd,
			true,
			$existingContent,
			$request,
			$wikiPage,
			false,
			$this->user
		);

		$this->assertStringContainsString( 'Intro text.', $html );
		$this->assertStringNotContainsString( 'Details text.', $html );
	}

	public function testLookAheadFindsNextSectionTagAtOffsetZero(): void {
		// The next '{{{' tag starts at offset 0 of $form_def_section (brackets_end_loc = 0).
		// strpos() returns int 0 for this match; a loose `== false` comparison would
		// misinterpret this as "not found" and fall through to end-of-content handling,
		// swallowing "Details text." into the "Intro" section instead of stopping before it.
		$existingContent = "== Intro ==\nIntro text.\n== Details ==\nDetails text.\n";

		$formDef = '{{{section|Details|level=2}}}';

		$request = new FauxRequest();
		$wikiPage = new PFWikiPage();

		$html = $this->builder->buildHtml(
			[ 'section', 'Intro', 'level=2' ],
			$formDef,
			0,
			true,
			$existingContent,
			$request,
			$wikiPage,
			false,
			$this->user
		);

		$this->assertStringContainsString( 'Intro text.', $html );
		$this->assertStringNotContainsString( 'Details text.', $html );
	}
}
