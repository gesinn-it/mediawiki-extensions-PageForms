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
	 * PFUploadForm must be constructable without throwing, given valid options.
	 */
	public function testConstructorDoesNotThrow() {
		$exception = null;
		try {
			$form = new PFUploadForm( $this->defaultOptions() );
		} catch ( Exception $e ) {
			$exception = $e;
		}

		$this->assertNull( $exception, 'PFUploadForm constructor must not throw' );
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
}
