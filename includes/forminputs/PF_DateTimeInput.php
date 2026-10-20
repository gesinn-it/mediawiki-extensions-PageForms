<?php
/**
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\FormDateUtils;

/**
 * @ingroup PFFormInput
 */
class PFDateTimeInput extends PFDateInput {

	public static function getName(): string {
		return 'datetime';
	}

	public static function getDefaultPropTypes() {
		return [];
	}

	public static function getOtherPropTypesHandled() {
		return [ '_dat' ];
	}

	public function getInputClass() {
		return 'dateTimeInput';
	}

	private static function timeInputHtml(
		string $inputName, string $part, string $class, $value, int $size, bool $isDisabled
	): string {
		return '<input tabindex="' . FormCounters::current()->tabIndex . '" name="' . htmlspecialchars( $inputName ) .
			"[$part]" . '" type="text" class="' . $class . '" value="' . htmlspecialchars( (string)$value ) .
			'" size="' . $size . '" ' . ( $isDisabled ? 'disabled' : '' ) . '/>';
	}

	public function getHTML( $datetime, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		global $wgPageForms24HourTime;
		$counters = FormCounters::current();

		$include_timezone = array_key_exists( 'include timezone', $other_args );

		$hour = null;
		$minute = null;
		$second = '00';
		$ampm24h = '';
		$timezone = '';

		// Can show up here either as an array or a string,
		// depending on whether it came from user input or a
		// wiki page.
		if ( is_array( $datetime ) ) {
			$hour = $datetime['hour'] ?? null;
			$minute = $datetime['minute'] ?? null;
			$second = $datetime['second'] ?? $second;
			if ( !$wgPageForms24HourTime ) {
				$ampm24h = $datetime['ampm24h'] ?? '';
			}
			$timezone = $datetime['timezone'] ?? '';
		} elseif ( trim( (string)$datetime ) !== '' ) {
			// We get only the time data here - the main
			// date data is handled by the call to
			// parent::getMainHTML().
			$parsed = FormDateUtils::parse( (string)$datetime, true );
			if ( $parsed !== null && $parsed->hasTime() ) {
				$hour = $parsed->getHour();
				$minute = sprintf( '%02d', $parsed->getMinute() ?? 0 );
				$second = sprintf( '%02d', $parsed->getSecond() ?? 0 );
				if ( !$wgPageForms24HourTime ) {
					$ampm24h = $hour >= 12 ? 'PM' : 'AM';
					$hour = $hour % 12 ?: 12;
				}
				$timezone = $parsed->getTimezone() ?? '';
			}
		}

		$text = parent::getMainHTML( $datetime, $input_name, $is_mandatory, $is_disabled, $other_args );
		$disabled_text = ( $is_disabled ) ? 'disabled' : '';
		$text .= "\t&#160;" . self::timeInputHtml( $input_name, 'hour', 'hoursInput', $hour, 2, $is_disabled );
		$counters->tabIndex++;
		$text .= ':' . self::timeInputHtml( $input_name, 'minute', 'minutesInput', $minute, 2, $is_disabled );
		$counters->tabIndex++;
		$text .= ':' . self::timeInputHtml( $input_name, 'second', 'secondsInput', $second, 2, $is_disabled ) . "\n";

		if ( !$wgPageForms24HourTime ) {
			$counters->tabIndex++;
			$text .= ' <select tabindex="' . $counters->tabIndex .
				'" name="' . htmlspecialchars( $input_name ) . "[ampm24h]\" class=\"ampmInput\" $disabled_text>\n";
			foreach ( [ '', 'AM', 'PM' ] as $value ) {
				$text .= "				<option value=\"$value\"";
				if ( $value === $ampm24h ) {
					$text .= " selected=\"selected\"";
				}
				$text .= ">$value</option>\n";
			}
			$text .= "	</select>\n";
		}

		if ( $include_timezone ) {
			$counters->tabIndex++;
			$text .= '<input tabindex="' . $counters->tabIndex . '" name="' . htmlspecialchars( $input_name ) .
				'[timezone]" type="text" value="' . htmlspecialchars( (string)$timezone ) . '" size="3" ' .
				( $is_disabled ? 'disabled' : '' ) . '/>' . "\n";
		}

		$spanClass = self::buildSpanClass( $this->getInputClass(), $is_mandatory );

		// ==== GESINN PATCH BEGIN ====
		// Added support for the 'class' parameter on PF_DateTimeInput:
		// User-defined CSS classes supplied via other_args['class'] are now
		// appended to the span element, allowing custom styling.
		if ( array_key_exists( 'class', $other_args ) ) {
			$spanClass .= ' ' . $other_args['class'];
		}
		// ==== GESINN PATCH END ====

		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );

		return $text;
	}

	public static function getParameters() {
		$params = parent::getParameters();
		$params[] = [
			'name' => 'include timezone',
			'type' => 'boolean',
			'description' => wfMessage( 'pf_forminputs_includetimezone' )->text()
		];
		return $params;
	}

	/**
	 * Returns the HTML code to be included in the output page for this input.
	 * @return string
	 */
	public function getHtmlText(): string {
		return $this->getHTML(
			$this->mCurrentValue,
			$this->mInputName,
			$this->mIsMandatory,
			$this->mIsDisabled,
			$this->mOtherArgs
		);
	}
}
