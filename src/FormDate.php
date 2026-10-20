<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

/**
 * A date as stored in the wikitext of a page: a year, optionally with a month, a day
 * and a time. Parts that are not known stay empty instead of being filled with a default,
 * so a year-only or year-and-month value survives a trip through a form unchanged.
 *
 * The value is written as ISO 8601 (`2026`, `2026-09`, `2026-09-30`, `2026-09-30 14:05:00`),
 * which does not depend on the language of the wiki or of the user.
 *
 * Instances are immutable and always valid; create() returns null for impossible dates.
 *
 * @ingroup PF
 */
class FormDate {

	public const PRECISION_YEAR = 'year';
	public const PRECISION_MONTH = 'month';
	public const PRECISION_DAY = 'day';

	private function __construct(
		private int $year,
		private ?int $month,
		private ?int $day,
		private ?int $hour,
		private ?int $minute,
		private ?int $second,
		private ?string $timezone
	) {
	}

	/**
	 * A time needs a day: without one, the time is dropped. Hour and minute belong together,
	 * seconds default to zero.
	 *
	 * @return self|null null if the parts do not make a date
	 */
	public static function create(
		int $year,
		?int $month = null,
		?int $day = null,
		?int $hour = null,
		?int $minute = null,
		?int $second = null,
		?string $timezone = null
	): ?self {
		if ( $year < 1 || $year > 9999 ) {
			return null;
		}
		if ( $month === null ) {
			// A day needs a month.
			return $day === null ? new self( $year, null, null, null, null, null, null ) : null;
		}
		if ( $month < 1 || $month > 12 ) {
			return null;
		}
		if ( $day === null ) {
			return new self( $year, $month, null, null, null, null, null );
		}
		if ( !checkdate( $month, $day, $year ) ) {
			return null;
		}
		if ( $hour === null && $minute === null ) {
			return new self( $year, $month, $day, null, null, null, null );
		}
		if ( $hour === null || $minute === null ) {
			return null;
		}
		$second ??= 0;
		if ( $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59 ) {
			return null;
		}
		$timezone = $timezone === '' ? null : $timezone;
		return new self( $year, $month, $day, $hour, $minute, $second, $timezone );
	}

	public function getYear(): int {
		return $this->year;
	}

	public function getMonth(): ?int {
		return $this->month;
	}

	public function getDay(): ?int {
		return $this->day;
	}

	public function getHour(): ?int {
		return $this->hour;
	}

	public function getMinute(): ?int {
		return $this->minute;
	}

	public function getSecond(): ?int {
		return $this->second;
	}

	public function getTimezone(): ?string {
		return $this->timezone;
	}

	public function hasTime(): bool {
		return $this->hour !== null;
	}

	/**
	 * @return string One of the PRECISION_* constants: the most detailed part of the date that is known.
	 */
	public function getPrecision(): string {
		if ( $this->day !== null ) {
			return self::PRECISION_DAY;
		}
		return $this->month !== null ? self::PRECISION_MONTH : self::PRECISION_YEAR;
	}

	/**
	 * The value as it is written to the wikitext.
	 */
	public function toIso(): string {
		$iso = sprintf( '%04d', $this->year );
		if ( $this->month === null ) {
			return $iso;
		}
		$iso .= sprintf( '-%02d', $this->month );
		if ( $this->day === null ) {
			return $iso;
		}
		$iso .= sprintf( '-%02d', $this->day );
		if ( $this->hour === null ) {
			return $iso;
		}
		$iso .= sprintf( ' %02d:%02d:%02d', $this->hour, $this->minute ?? 0, $this->second ?? 0 );
		return $this->timezone === null ? $iso : "$iso {$this->timezone}";
	}

	/**
	 * The value for a date picker, which can only hold a complete date.
	 *
	 * @return string|null `YYYY-MM-DD`, or null if the day is not known
	 */
	public function toPickerDate(): ?string {
		if ( $this->month === null || $this->day === null ) {
			return null;
		}
		return sprintf( '%04d-%02d-%02d', $this->year, $this->month, $this->day );
	}

	/**
	 * The value for a date-and-time picker, which can only hold a complete date.
	 *
	 * @return string|null `YYYY-MM-DDTHH:MM:SS` (midnight if there is no time), or null if the day is not known
	 */
	public function toPickerDateTime(): ?string {
		$date = $this->toPickerDate();
		if ( $date === null ) {
			return null;
		}
		return sprintf( '%sT%02d:%02d:%02d', $date, $this->hour ?? 0, $this->minute ?? 0, $this->second ?? 0 );
	}

}
