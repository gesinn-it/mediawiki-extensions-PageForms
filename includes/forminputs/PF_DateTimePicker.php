<?php

/**
 * @author Stephan Gambke
 * @author Sam Wilson
 * @author Amr El-Absy
 * @ingroup PageForms
 */

/**
 * @ingroup PageForms
 */

use MediaWiki\Extension\PageForms\FormDateUtils;
use MediaWiki\Widget\DateTimeInputWidget;

class PFDateTimePicker extends PFFormInput {

	public static function getName(): string {
		return 'datetimepicker';
	}

	/**
	 * @param string $input_number The number of the input in the form.
	 * @param string $cur_value The current value of the input field.
	 * @param string $input_name The name of the input.
	 * @param bool $disabled Is this input disabled?
	 * @param array $other_args An associative array of other parameters that were present in the
	 *  input definition.
	 */
	/**
	 * The picker can only hold a complete date. Anything else that is stored (a year, a month,
	 * or text that is not a date) is shown in a text field instead, so that it is not lost.
	 * @var string|null
	 */
	protected $mTextValue = null;

	public function __construct( $input_number, $cur_value, $input_name, $disabled, array $other_args ) {
		if ( trim( (string)$cur_value ) !== '' ) {
			$parsed = FormDateUtils::parse( (string)$cur_value, true );
			$pickerValue = $parsed === null ? null : $parsed->toPickerDateTime();
			if ( $pickerValue === null ) {
				$this->mTextValue = $parsed === null ? (string)$cur_value : $parsed->toIso();
				$pickerValue = '';
			}
			$cur_value = $pickerValue;
		}
		parent::__construct( $input_number, $cur_value, $input_name, $disabled, $other_args );
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 *
	 * Ideally this HTML code should provide a basic functionality even if the
	 * browser is not JavaScript capable. I.e. even without JavaScript the user
	 * should be able to input values.
	 * @return string
	 */
	public function getHtmlText(): string {
		$inputID = 'input_' . $this->mInputNumber;
		if ( $this->mTextValue !== null ) {
			$text = PFDatePickerInput::textFallbackHtml(
				$this->mInputName, $this->mTextValue, (bool)$this->mIsDisabled, $inputID
			);
		} else {
			$widget = new DateTimeInputWidget( [
				'type' => 'datetime',
				'name' => $this->mInputName,
				'value' => $this->mCurrentValue,
				'id' => $inputID,
				'classes' => [ 'pfDateTimePicker', 'pfPicker' ],
				'infusable' => true
			] );
			$text = $widget->toString();
		}

		// Unfortunately, DateTimeInputWidget only allows 24-hour
		// (as opposed to AM/PM) data entry, so we'll add a label to
		// explain the situation. DateTimeInputWidget unfortunately
		// also doesn't allow a 'label' parameter, so we have to add it
		// manually. The 'for' seems to have no effect, sadly, but
		// we'll add it anyway.
		$text .= Html::element( 'label',
			[
				'for' => $inputID,
				'class' => 'oo-ui-labelWidget oo-ui-inline-help',
				'style' => 'margin-top: 4px;'
			],
			wfMessage( 'pf-datetimepicker-24hour' )->parse()
		);

		// We need a wrapper div so that OOUI won't override
		// any classes added by "show on select".
		$wrapperClass = 'pfPickerWrapper';
		if ( isset( $this->mOtherArgs[ 'mandatory' ] ) ) {
			$wrapperClass .= ' mandatory';
		}

		// ==== GESINN PATCH BEGIN ====
		// Added support for the 'class' parameter on PF_DateTimePicker:
		// User-defined CSS classes supplied via other_args['class'] are now
		// appended to the wrapper element, allowing custom styling.
		if ( isset( $this->mOtherArgs['class'] ) ) {
			$wrapperClass .= ' ' . $this->mOtherArgs['class'];
		}
		// ==== GESINN PATCH END ====

		return Html::rawElement( 'div', [ 'class' => $wrapperClass ], $text );
	}

	/**
	 * Returns the set of SMW property types which this input can
	 * handle, but for which it isn't the default input.
	 * @return string[]
	 */
	public static function getOtherPropTypesHandled() {
		return [ '_str', '_dat' ];
	}

	/**
	 * Returns the set of parameters for this form input.
	 * @return array[]
	 */
	public static function getParameters() {
		$params = array_merge(
			parent::getParameters(),
			PFDatePickerInput::getParameters()
		);

		$params['mintime'] = [
			'name' => 'mintime',
			'type' => 'string',
			'description' => wfMessage( 'pageforms-timepicker-mintime' )->text(),
		];
		$params['maxtime'] = [
			'name' => 'maxtime',
			'type' => 'string',
			'description' => wfMessage( 'pageforms-timepicker-maxtime' )->text(),
		];
		$params['interval'] = [
			'name' => 'interval',
			'type' => 'int',
			'description' => wfMessage( 'pageforms-timepicker-interval' )->text(),
		];

		return $params;
	}

	/**
	 * Returns the names of the resource modules this input type uses.
	 *
	 * Returns the names of the modules as an array or - if there is only one
	 * module - as a string.
	 *
	 * @return null|string|array
	 */
	public function getResourceModuleNames() {
		return [ 'ext.pageforms.datetimepicker' ];
	}

}
