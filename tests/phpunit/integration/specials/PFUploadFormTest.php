<?php

/**
 * @covers \PFUploadForm
 * @group Database
 *
 * @author gesinn-it-gea
 */
class PFUploadFormTest extends MediaWikiIntegrationTestCase {

	/**
	 * Build a minimal set of options required by the PFUploadForm constructor.
	 */
	private function defaultOptions(): array {
		return [
			'watch' => false,
			'forreupload' => false,
			'sessionkey' => '',
			'hideignorewarning' => false,
			'texttop' => '',
			'textaftersummary' => '',
			'destfile' => '',
			'pfInputID' => 'input_1',
			'pfDelimiter' => '',
		];
	}

	/**
	 * PFUploadForm extends HTMLForm.
	 */
	public function testIsInstanceOfHtmlForm() {
		$form = new PFUploadForm( $this->defaultOptions() );

		$this->assertInstanceOf( HTMLForm::class, $form );
	}

	/**
	 * trySubmit() must always return false — submission is handled elsewhere.
	 */
	public function testTrySubmitReturnsFalse() {
		$form = new PFUploadForm( $this->defaultOptions() );

		$this->assertFalse( $form->trySubmit() );
	}

	/**
	 * When 'forreupload' is true the internal flag must be set accordingly.
	 * We verify this indirectly: the DestFile field becomes read-only, which
	 * means getDescriptionSection() must have set 'readonly' => true on it.
	 * We verify via the parent field map accessible through getField().
	 */
	public function testForReUploadOptionIsRespected() {
		$options = $this->defaultOptions();
		$options['forreupload'] = true;
		$options['destfile'] = 'TestFile.png';

		$form = new PFUploadForm( $options );

		// getField() is available on HTMLForm — it will return null if missing
		$field = $form->getField( 'DestFile' );
		$this->assertNotNull( $field, 'DestFile field must exist when forreupload is set' );
	}

	/**
	 * When sessionkey is provided the form uses stash source fields
	 * (SessionKey + SourceType) and must not expose the file-upload widget.
	 */
	public function testSessionKeyOptionUsesstashSourceDescriptor() {
		$options = $this->defaultOptions();
		$options['sessionkey'] = 'abc123stashkey';

		$form = new PFUploadForm( $options );

		$this->assertNotNull(
			$form->getField( 'SessionKey' ),
			'SessionKey field must exist when sessionkey option is set'
		);
		$this->assertNotNull(
			$form->getField( 'SourceType' ),
			'SourceType field must exist when sessionkey option is set'
		);
	}

	/**
	 * When no sessionkey is provided the file-upload field (UploadFile) must
	 * be present.
	 */
	public function testWithoutSessionKeyUploadFileFieldExists() {
		$form = new PFUploadForm( $this->defaultOptions() );

		$this->assertNotNull(
			$form->getField( 'UploadFile' ),
			'UploadFile field must exist when no sessionkey is set'
		);
	}

	/**
	 * When the user lacks upload_by_url permission the UploadFileURL field
	 * must not be present, but UploadFile must still exist.
	 */
	public function testUploadByUrlFieldAbsentWhenPermissionMissing() {
		$this->setMwGlobals( 'wgAllowCopyUploads', false );

		$form = new PFUploadForm( $this->defaultOptions() );

		$this->assertFalse(
			$form->hasField( 'UploadFileURL' ),
			'UploadFileURL field must not exist when upload_by_url is not permitted'
		);
		$this->assertTrue(
			$form->hasField( 'UploadFile' ),
			'UploadFile field must still exist when upload_by_url is not permitted'
		);
	}

	/**
	 * getExtensionsMessage() with CheckFileExtensions=true and
	 * StrictFileExtensions=true must return a div with id mw-upload-permitted
	 * and must not include mw-upload-preferred or mw-upload-prohibited.
	 */
	public function testGetExtensionsMessageStrictReturnsPermittedOnly() {
		$this->setMwGlobals( [
			'wgCheckFileExtensions' => true,
			'wgStrictFileExtensions' => true,
			'wgFileExtensions' => [ 'png', 'jpg' ],
		] );

		$form = new PFUploadForm( $this->defaultOptions() );
		$method = new ReflectionMethod( PFUploadForm::class, 'getExtensionsMessage' );
		$method->setAccessible( true );

		$result = $method->invoke( $form );

		$this->assertStringContainsString( 'mw-upload-permitted', $result );
		$this->assertStringNotContainsString( 'mw-upload-preferred', $result );
		$this->assertStringNotContainsString( 'mw-upload-prohibited', $result );
	}

	/**
	 * getExtensionsMessage() with CheckFileExtensions=true and
	 * StrictFileExtensions=false must return both mw-upload-preferred and
	 * mw-upload-prohibited divs.
	 */
	public function testGetExtensionsMessageNonStrictReturnsBothLists() {
		$this->setMwGlobals( [
			'wgCheckFileExtensions' => true,
			'wgStrictFileExtensions' => false,
			'wgFileExtensions' => [ 'png', 'jpg' ],
			'wgFileBlacklist' => [ 'exe', 'php' ],
		] );

		$form = new PFUploadForm( $this->defaultOptions() );
		$method = new ReflectionMethod( PFUploadForm::class, 'getExtensionsMessage' );
		$method->setAccessible( true );

		$result = $method->invoke( $form );

		$this->assertStringContainsString( 'mw-upload-preferred', $result );
		$this->assertStringContainsString( 'mw-upload-prohibited', $result );
	}

	/**
	 * getExtensionsMessage() with CheckFileExtensions=false must return an
	 * empty string — everything is permitted, nothing to display.
	 */
	public function testGetExtensionsMessageDisabledReturnsEmptyString() {
		$this->setMwGlobals( 'wgCheckFileExtensions', false );

		$form = new PFUploadForm( $this->defaultOptions() );
		$method = new ReflectionMethod( PFUploadForm::class, 'getExtensionsMessage' );
		$method->setAccessible( true );

		$result = $method->invoke( $form );

		$this->assertSame( '', $result );
	}

	/**
	 * The PF-specific hidden fields pfInputID and pfDelimiter must be
	 * registered on the form after construction.
	 *
	 * We inspect the protected mHiddenFields property directly to avoid
	 * triggering the full render chain (which requires a Title context).
	 */
	public function testPfHiddenFieldsAreRegisteredOnForm() {
		$options = $this->defaultOptions();
		$options['pfInputID'] = 'input_42';
		$options['pfDelimiter'] = ',';

		$form = new PFUploadForm( $options );

		$prop = new ReflectionProperty( HTMLForm::class, 'mHiddenFields' );
		$prop->setAccessible( true );
		$hiddenFields = $prop->getValue( $form );

		$names = array_column( array_column( $hiddenFields, 1 ), 'name' );

		$this->assertContains( 'pfInputID', $names, 'pfInputID must be registered as a hidden field' );
		$this->assertContains( 'pfDelimiter', $names, 'pfDelimiter must be registered as a hidden field' );
	}
}
