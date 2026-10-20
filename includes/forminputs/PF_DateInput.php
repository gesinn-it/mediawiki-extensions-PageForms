<?php
/**
 * @ingroup PF
 */

use MediaWiki\Extension\PageForms\FormCounters;
use MediaWiki\Extension\PageForms\FormDateUtils;

/**
 * @ingroup PFFormInput
 */
class PFDateInput extends PFFormInput {

	public static function getName(): string {
		return 'date';
	}

	public static function getDefaultPropTypes() {
		return [ '_dat' => [] ];
	}

	/**
	 * @param int|string|null $cur_month The selected month: its number, or its name in a known language
	 * @param string $input_name
	 * @param bool $is_disabled
	 * @return string
	 */
	public static function monthDropdownHTML( $cur_month, $input_name, $is_disabled ) {
		$counters = FormCounters::current();

		$selected = null;
		if ( $cur_month !== null && $cur_month !== '' ) {
			$selected = ctype_digit( (string)$cur_month )
				? (int)$cur_month
				: FormDateUtils::getParser()->monthNumber( (string)$cur_month );
		}

		// The value of an option is the number of the month, the label is the name in the language of the user.
		$optionsText = Html::element( 'option', [ 'value' => null ], '' );
		foreach ( FormDateUtils::getMonthNamesForUser() as $index => $name ) {
			$optionAttrs = [ 'value' => str_pad( (string)( $index + 1 ), 2, '0', STR_PAD_LEFT ) ];
			if ( $selected === $index + 1 ) {
				$optionAttrs['selected'] = 'selected';
			}
			$optionsText .= Html::element( 'option', $optionAttrs, $name );
		}
		$selectAttrs = [
			'class' => 'monthInput',
			'name' => $input_name . '[month]',
			'tabindex' => $counters->tabIndex
		];
		if ( $is_disabled ) {
			$selectAttrs['disabled'] = 'disabled';
		}
		return Html::rawElement( 'select', $selectAttrs, $optionsText );
	}

	public function getInputClass() {
		return 'dateInput';
	}

	/**
	 * @deprecated since PageForms 6.x - use FormDateUtils::parse() instead, which keeps partial dates
	 *  and returns nothing if the text is not a date.
	 * @param string $date
	 * @param bool $includeTime
	 * @return array|null Year, month, day and for $includeTime the time as HH:MM:SS
	 * @codeCoverageIgnore
	 */
	public static function parseDate( $date, $includeTime = false ) {
		$parsed = FormDateUtils::parse( (string)$date, (bool)$includeTime );
		if ( $parsed === null ) {
			return null;
		}
		$parts = [ $parsed->getYear(), $parsed->getMonth(), $parsed->getDay() ];
		if ( $includeTime ) {
			$parts[] = sprintf(
				'%02d:%02d:%02d', $parsed->getHour() ?? 0, $parsed->getMinute() ?? 0, $parsed->getSecond() ?? 0
			);
		}
		return $parts;
	}

	/**
	 * A small text input of a date. Its value and name are escaped, because they can be what a person typed.
	 */
	protected static function textInputHtml(
		string $class, string $name, string $value, int $size, bool $isDisabled
	): string {
		return '<input tabindex="' . FormCounters::current()->tabIndex . '" class="' . $class .
			'" name="' . htmlspecialchars( $name ) . '" type="text" value="' . htmlspecialchars( $value ) .
			'" size="' . $size . '" ' . ( $isDisabled ? 'disabled' : '' ) . '/>';
	}

	public static function getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$counters = FormCounters::current();

		$year = $month = $day = $raw = null;
		// Can show up here either as an array or a string,
		// depending on whether it came from user input or a
		// wiki page.
		if ( is_array( $date ) ) {
			$year = $date['year'] ?? null;
			$month = $date['month'] ?? null;
			$day = $date['day'] ?? null;
			$raw = $date['raw'] ?? null;
		} elseif ( trim( (string)$date ) !== '' ) {
			$parsed = FormDateUtils::parse( (string)$date );
			if ( $parsed !== null ) {
				$year = $parsed->getYear();
				$month = $parsed->getMonth();
				$day = $parsed->getDay();
			} else {
				// Not a date PF can read: it is kept as it is, unless a date is entered.
				$raw = (string)$date;
			}
		}

		$inputs = [
			'day' => self::textInputHtml( 'dayInput', $input_name . '[day]', (string)$day, 2, $is_disabled ),
			'month' => self::monthDropdownHTML( $month, $input_name, $is_disabled ),
			'year' => self::textInputHtml( 'yearInput', $input_name . '[year]', (string)$year, 4, $is_disabled ),
		];
		$text = '';
		foreach ( FormDateUtils::getFieldOrder() as $field ) {
			$text .= $inputs[$field] . "\n";
		}
		if ( $raw !== null && $raw !== '' ) {
			$text .= Html::element( 'input', [ 'type' => 'hidden', 'name' => $input_name . '[raw]', 'value' => $raw ] );
			$text .= Html::element(
				'span',
				[ 'class' => 'pfDateUnreadable' ],
				wfMessage( 'pf-date-unreadable', $raw )->text()
			) . "\n";
		}
		return $text;
	}

	public function getHTML( $date, $input_name, $is_mandatory, $is_disabled, array $other_args ) {
		$text = self::getMainHTML( $date, $input_name, $is_mandatory, $is_disabled, $other_args );
		$spanClass = self::buildSpanClass( $this->getInputClass(), $is_mandatory );

		// ==== GESINN PATCH BEGIN ====
		// Added support for the 'class' parameter on PF_DateInput:
		// User-defined CSS classes supplied via other_args['class'] are now
		// appended to the span element, allowing custom styling.
		if ( array_key_exists( 'class', $other_args ) ) {
			$spanClass .= ' ' . $other_args['class'];
		}
		// ==== GESINN PATCH END ====

		$text = Html::rawElement( 'span', [ 'class' => $spanClass ], $text );
		return $text;
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
