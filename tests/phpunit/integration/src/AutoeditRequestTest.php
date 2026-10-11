<?php

use MediaWiki\Extension\PageForms\AutoeditRequest;

/**
 * @covers \MediaWiki\Extension\PageForms\AutoeditRequest
 * @group PF
 * @group Database
 */
class AutoeditRequestTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideActions
	 */
	public function testTheOptionsThatAskForAnActionChooseIt(
		array $options, int $expectedAction, bool $expectedIsAutoEdit
	): void {
		$request = AutoeditRequest::fromOptions( [ 'form' => 'AEReqForm', 'target' => 'AEReqTarget' ] + $options );

		$this->assertSame( $expectedAction, $request->action );
		$this->assertSame( $expectedIsAutoEdit, $request->isAutoEdit );
	}

	public static function provideActions(): array {
		return [
			'nothing asks for an action' => [ [], AutoeditRequest::ACTION_FORMEDIT, false ],
			'save button' => [ [ 'wpSave' => '1' ], AutoeditRequest::ACTION_SAVE, false ],
			'preview button' => [ [ 'wpPreview' => '1' ], AutoeditRequest::ACTION_PREVIEW, false ],
			'show changes button' => [ [ 'wpDiff' => '1' ], AutoeditRequest::ACTION_DIFF, false ],
			'action pfautoedit' => [ [ 'action' => 'pfautoedit' ], AutoeditRequest::ACTION_SAVE, true ],
			'action preview' => [ [ 'action' => 'preview' ], AutoeditRequest::ACTION_PREVIEW, false ],
			'any other action' => [ [ 'action' => 'formedit' ], AutoeditRequest::ACTION_FORMEDIT, false ],
			'save button wins over preview' => [
				[ 'wpSave' => '1', 'wpPreview' => '1', 'wpDiff' => '1' ], AutoeditRequest::ACTION_SAVE, false
			],
			'preview button wins over diff' => [
				[ 'wpPreview' => '1', 'wpDiff' => '1' ], AutoeditRequest::ACTION_PREVIEW, false
			],
			'a button wins over the action' => [
				[ 'wpPreview' => '1', 'action' => 'pfautoedit' ], AutoeditRequest::ACTION_PREVIEW, false
			],
		];
	}

	public function testTheButtonThatChoseTheActionIsNotAnOption(): void {
		$options = AutoeditRequest::fromOptions( [ 'wpPreview' => '1', 'other' => 'kept' ] )->options;

		$this->assertArrayNotHasKey( 'wpPreview', $options );
		$this->assertSame( 'kept', $options['other'] );
	}

	/**
	 * #autoedit takes the base of a save when it reads the page, not from the form it was rendered with.
	 */
	public function testAnAutoeditRequestDropsTheBaseRevisionItWasRenderedWith(): void {
		$options = AutoeditRequest::fromOptions( [
			'action' => 'pfautoedit', 'wpEdittime' => '20260101000000', 'editRevId' => '7',
		] )->options;

		$this->assertArrayNotHasKey( 'wpEdittime', $options );
		$this->assertArrayNotHasKey( 'editRevId', $options );
	}

	public function testAFormSaveKeepsTheBaseRevisionItWasRenderedWith(): void {
		$options = AutoeditRequest::fromOptions( [
			'wpSave' => '1', 'wpEdittime' => '20260101000000', 'editRevId' => '7',
		] )->options;

		$this->assertSame( '20260101000000', $options['wpEdittime'] );
		$this->assertSame( '7', $options['editRevId'] );
	}

	public function testTitleIsTheTargetIfThereIsNoTarget(): void {
		$options = AutoeditRequest::fromOptions( [ 'form' => 'AEReqForm', 'title' => 'AEReqPage' ] )->options;

		$this->assertSame( 'AEReqPage', $options['target'] );
		$this->assertArrayNotHasKey( 'title', $options );
	}

	public function testTitleDoesNotReplaceATarget(): void {
		$options = AutoeditRequest::fromOptions( [ 'target' => 'AEReqTarget', 'title' => 'AEReqPage' ] )->options;

		$this->assertSame( 'AEReqTarget', $options['target'] );
		$this->assertSame( 'AEReqPage', $options['title'] );
	}

	public function testFormAndTargetAreAlwaysSet(): void {
		$options = AutoeditRequest::fromOptions( [] )->options;

		$this->assertSame( '', $options['form'] );
		$this->assertSame( '', $options['target'] );
	}

	/**
	 * @dataProvider provideHookQueries
	 */
	public function testTheHookQueryNamesTheFormAndTheTarget( array $options, ?string $expected ): void {
		$this->assertSame( $expected, AutoeditRequest::fromOptions( $options )->hookQuery );
	}

	public static function provideHookQueries(): array {
		return [
			'no form' => [ [ 'target' => 'AEReqTarget' ], null ],
			'form only' => [ [ 'form' => 'AEReqForm' ], 'AEReqForm' ],
			'form and target' => [ [ 'form' => 'AEReqForm', 'target' => 'AEReqTarget' ], 'AEReqForm/AEReqTarget' ],
			'form and the target that title names' => [
				[ 'form' => 'AEReqForm', 'title' => 'AEReqPage' ], 'AEReqForm/AEReqPage'
			],
		];
	}

	public function testFormAndTargetNamesAreNormalized(): void {
		$options = AutoeditRequest::fromOptions( [ 'form' => 'ae req form', 'target' => 'ae req page' ] )->options;

		$this->assertSame( 'Ae req form', $options['form'] );
		$this->assertSame( 'Ae req page', $options['target'] );
	}

	public function testANameThatIsNoTitleIsLeftAsItIs(): void {
		$options = AutoeditRequest::fromOptions( [ 'form' => 'AEReq[Form]', 'target' => '' ] )->options;

		$this->assertSame( 'AEReq[Form]', $options['form'] );
		$this->assertSame( '', $options['target'] );
	}

	public function testTheQueryOptionIsUnpackedIntoTheOptions(): void {
		$options = AutoeditRequest::fromOptions( [
			'form' => 'AEReqForm',
			'query' => 'AEReqTpl%5Bfield%5D=value&AEReqTpl%5Bother%5D=x',
			'AEReqTpl' => [ 'kept' => 'k' ],
		] )->options;

		$this->assertArrayNotHasKey( 'query', $options );
		$this->assertSame( 'value', $options['AEReqTpl']['field'] );
		$this->assertSame( 'x', $options['AEReqTpl']['other'] );
		$this->assertSame( 'k', $options['AEReqTpl']['kept'] );
	}

	public function testTheQueryOptionCanNameTheTargetAndTheAction(): void {
		$request = AutoeditRequest::fromOptions( [ 'query' => 'target=AEReqQueryTarget&wpPreview=1' ] );

		$this->assertSame( 'AEReqQueryTarget', $request->options['target'] );
		// The action is chosen from the options of the request, after the query was unpacked.
		$this->assertSame( AutoeditRequest::ACTION_PREVIEW, $request->action );
	}

	public function testParseQueryStringKeepsAPlusSign(): void {
		$data = AutoeditRequest::parseQueryString( [], 'AEReqTpl%5Bfield%5D=a+b' );

		$this->assertSame( 'a+b', $data['AEReqTpl']['field'] );
	}

	public function testParseQueryStringDecodesTheKeysAndTheValues(): void {
		$data = AutoeditRequest::parseQueryString( [ 'before' => 'b' ], 'a%20key=a%26b%3Dc' );

		$this->assertSame( 'a&b=c', $data['a key'] );
		$this->assertSame( 'b', $data['before'] );
	}

	public function testParseQueryStringFollowsANestedQuery(): void {
		$data = AutoeditRequest::parseQueryString( [], 'query=AEReqTpl%255Bfield%255D%3Dnested&top=1' );

		$this->assertSame( 'nested', $data['AEReqTpl']['field'] );
		$this->assertSame( '1', $data['top'] );
	}

	public function testParseQueryStringIgnoresAnEmptyNestedQuery(): void {
		$this->assertSame( [ 'top' => '1' ], AutoeditRequest::parseQueryString( [], 'query&top=1' ) );
	}

	public function testParseQueryStringIgnoresEmptyParameters(): void {
		$this->assertSame( [ 'a' => '1', 'b' => '2' ], AutoeditRequest::parseQueryString( [], '&a=1&&b=2&' ) );
	}
}
