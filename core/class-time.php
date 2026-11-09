<?php
/**
 * The one place WP Career Board stores, converts and shows times.
 *
 * Wbcom Time Standard (docs/standards/time.md): every time is stored in UTC,
 * shown in one timezone chosen here, the same way on admin screens, member
 * pages, emails, REST and edit fields. Pro and add-ons call these helpers and
 * never format a time themselves; CI fails on a raw wp_date(), date_i18n(),
 * mysql2date() or get_date_from_gmt() anywhere else (bin/check-time-formatter.php).
 *
 * @package WP_Career_Board
 * @since   1.8.3
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time helpers.
 *
 * @since 1.8.3
 */
final class Time {

	/**
	 * The timezone times are shown in.
	 *
	 * @since 1.8.3
	 *
	 * @param int $user_id Who the time is shown to (the recipient for an email); 0 = current user.
	 * @return \DateTimeZone
	 */
	public static function display_timezone( int $user_id = 0 ): \DateTimeZone {
		/**
		 * The timezone WP Career Board shows times in. Return a member's own
		 * timezone to show each member their local time; every surface, email
		 * and edit field follows.
		 *
		 * @since 1.8.3
		 *
		 * @param \DateTimeZone $timezone The site's timezone.
		 * @param int           $user_id  Who the time is shown to.
		 */
		$timezone = apply_filters( 'wcb_display_timezone', wp_timezone(), $user_id > 0 ? $user_id : get_current_user_id() );

		return $timezone instanceof \DateTimeZone ? $timezone : wp_timezone();
	}

	/**
	 * A stored UTC time as a Unix timestamp; null for NULL, empty, the zero
	 * date and anything unparseable. A naive string is read as UTC.
	 *
	 * @since 1.8.3
	 *
	 * @param string|int|null $utc A stored UTC datetime, or a Unix timestamp.
	 * @return int|null
	 */
	public static function utc_timestamp( $utc ): ?int {
		if ( is_int( $utc ) ) {
			return $utc > 0 ? $utc : null;
		}
		$utc = null === $utc ? '' : trim( (string) $utc );
		if ( '' === $utc || 0 === strncmp( $utc, '0000-00-00', 10 ) ) {
			return null;
		}
		$timestamp = strtotime( $utc . ( preg_match( '/(Z|[+-]\d{2}:?\d{2}|UTC|GMT)$/i', $utc ) ? '' : ' UTC' ) );

		return false === $timestamp || $timestamp <= 0 ? null : $timestamp;
	}

	/**
	 * A stored UTC time shown to a person: THE formatter for every time shown.
	 *
	 * @since 1.8.3
	 *
	 * @param string|int|null $utc      A stored UTC datetime (naive = UTC) or a Unix timestamp.
	 * @param string          $fallback Shown when there is no usable time.
	 * @param string|null     $format   PHP date format; the site's date format by default.
	 * @param int             $user_id  Who it is shown to (see display_timezone()).
	 * @return string
	 */
	public static function date_label( $utc, string $fallback = '—', ?string $format = null, int $user_id = 0 ): string {
		$timestamp = self::utc_timestamp( $utc );
		if ( null === $timestamp ) {
			return $fallback;
		}

		return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, self::display_timezone( $user_id ) ); // time-ok: the formatter itself.
	}

	/**
	 * A stored UTC time as date and time, in the site's date and time formats.
	 *
	 * @since 1.8.3
	 *
	 * @param string|int|null $utc      A stored UTC datetime or Unix timestamp.
	 * @param string          $fallback Shown when there is no usable time.
	 * @param int             $user_id  Who it is shown to.
	 * @return string
	 */
	public static function datetime_label( $utc, string $fallback = '—', int $user_id = 0 ): string {
		return self::date_label( $utc, $fallback, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $user_id );
	}

	/**
	 * A calendar date with no time of day (a resume start date, a custom date
	 * field), printed as stored: it is not an instant, so it never moves with
	 * the display timezone.
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $ymd      `Y-m-d` (a time part is ignored).
	 * @param string      $fallback Shown when there is no usable date.
	 * @param string|null $format   PHP date format; the site's date format by default.
	 * @return string
	 */
	public static function calendar_date_label( ?string $ymd, string $fallback = '—', ?string $format = null ): string {
		$ymd       = null === $ymd ? '' : substr( trim( $ymd ), 0, 10 );
		$timestamp = '' === $ymd || '0000-00-00' === $ymd ? false : strtotime( $ymd . ' 00:00:00 UTC' );
		if ( false === $timestamp ) {
			return $fallback;
		}

		return (string) wp_date( $format ?? (string) get_option( 'date_format' ), $timestamp, new \DateTimeZone( 'UTC' ) ); // time-ok: calendar date, no zone.
	}

	/**
	 * What an editor typed (in the display timezone) as a UTC storage string.
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $local `Y-m-d\TH:i` or any parseable local datetime.
	 * @return string|null UTC `Y-m-d H:i:s`, or null when blank or unparseable.
	 */
	public static function datetime_input_to_utc( ?string $local ): ?string {
		if ( null === $local || '' === trim( $local ) ) {
			return null;
		}
		$dt = date_create( $local, self::display_timezone() );

		return false === $dt ? null : $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * A stored UTC time for an edit field: the exact inverse of datetime_input_to_utc().
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $utc Stored UTC datetime.
	 * @return string `Y-m-d\TH:i` in the display timezone, or ''.
	 */
	public static function datetime_local_input( ?string $utc ): string {
		$timestamp = self::utc_timestamp( $utc );

		return null === $timestamp ? '' : ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( self::display_timezone() )->format( 'Y-m-d\TH:i' );
	}

	/**
	 * A whole-day end date (a job deadline) as the UTC instant it ends:
	 * 23:59:59 on that day in the display timezone (standard rule 2).
	 *
	 * Already-stored UTC datetimes pass through, so every writer can call this
	 * on whatever it received.
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $value `Y-m-d` from a date picker or import, or a UTC datetime.
	 * @return string UTC `Y-m-d H:i:s`, or '' for none.
	 */
	public static function end_of_day_utc( ?string $value ): string {
		$value = null === $value ? '' : trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return (string) self::datetime_input_to_utc( $value . ' 23:59:59' );
		}
		$timestamp = self::utc_timestamp( $value );

		return null === $timestamp ? '' : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * A stored deadline for a date picker: its day in the display timezone.
	 *
	 * @since 1.8.3
	 *
	 * @param string|null $utc Stored UTC deadline.
	 * @return string `Y-m-d`, or ''.
	 */
	public static function date_input( ?string $utc ): string {
		return substr( self::datetime_local_input( $utc ), 0, 10 );
	}

	/**
	 * Today in the display timezone, as `Y-m-d`.
	 *
	 * @since 1.8.3
	 * @return string
	 */
	public static function today(): string {
		return ( new \DateTimeImmutable( 'now', self::display_timezone() ) )->format( 'Y-m-d' );
	}

	/**
	 * A stored UTC time as the REST API sends it: ISO 8601 with its offset.
	 *
	 * @since 1.8.3
	 *
	 * @param string $utc A stored UTC datetime.
	 * @return string ISO 8601, or the value unchanged when it is not a time.
	 */
	public static function rest_datetime( string $utc ): string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $utc ) ) {
			return $utc;
		}
		$timestamp = self::utc_timestamp( $utc );

		return null === $timestamp ? $utc : gmdate( 'c', $timestamp );
	}

	/**
	 * Every stored-UTC value in a wcb/v1 response as ISO 8601 with offset.
	 *
	 * Hooked on rest_request_after_callbacks, which runs for HTTP and internal
	 * (rest_do_request, preload) requests alike, so a new route gets it too. Only keys ending
	 * in `_at`, plus `deadline` / `closes_at`, holding a naive `Y-m-d H:i:s`
	 * are converted; anything else is left alone.
	 *
	 * @since 1.8.3
	 *
	 * @param mixed            $response Response.
	 * @param array<mixed>     $handler  Route handler (unused).
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function iso_response_dates( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $response instanceof \WP_REST_Response || $response->is_error() || 0 !== strpos( (string) $request->get_route(), '/wcb/v1/' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( is_array( $data ) ) {
			$response->set_data( self::iso_dates( $data ) );
		}

		return $response;
	}

	/**
	 * Walk a response array and convert its time keys.
	 *
	 * @since 1.8.3
	 *
	 * @param array<mixed> $data Response data.
	 * @return array<mixed>
	 */
	private static function iso_dates( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::iso_dates( $value );
			} elseif ( is_string( $value ) && is_string( $key ) && preg_match( '/(_at|^deadline)$/', $key ) ) {
				$data[ $key ] = self::rest_datetime( $value );
			}
		}

		return $data;
	}

	/**
	 * SQL for a stored UTC column moved into the display timezone, for
	 * grouping reports by day: wrap it in DATE() or DATE_FORMAT().
	 *
	 * Shortcut: one offset for the whole range, so in a daylight-saving week
	 * rows within an hour of midnight can land on the next or previous day.
	 *
	 * @since 1.8.3
	 *
	 * @param string $column A column name from code, never from input.
	 * @return string
	 */
	public static function display_time_sql( string $column ): string {
		$offset = self::display_timezone()->getOffset( new \DateTimeImmutable( 'now' ) );
		$abs    = abs( $offset );

		return sprintf( "CONVERT_TZ( %s, '+00:00', '%s%02d:%02d' )", $column, $offset < 0 ? '-' : '+', intdiv( $abs, 3600 ), intdiv( $abs % 3600, 60 ) );
	}

	/**
	 * One-time: move a table column written in site time to UTC.
	 *
	 * Old rows were written with current_time( 'mysql' ) in the site's own
	 * timezone, so they convert with wp_timezone(), never display_timezone()
	 * (standard rule 10). Each row takes the offset in force when it was
	 * written: one UPDATE whose CASE walks the zone's transitions (daylight
	 * saving) and reads every row's original value, so no row moves twice
	 * and no MySQL timezone tables are needed. A converted table/column is
	 * recorded in the `wcb_utc_converted` option, so a rerun (a failed
	 * upgrade, a restored option, Pro running its own step) changes nothing.
	 *
	 * ponytail: rows written in the repeated hour of a fall-back night cannot
	 * be told apart and take the earlier offset.
	 *
	 * @since 1.8.3
	 *
	 * @param string $table  Table name with prefix, from code.
	 * @param string $column DATETIME column, from code.
	 * @return bool False when the UPDATE failed, so the caller keeps its
	 *              upgrade step pending and retries; true otherwise.
	 */
	public static function local_column_to_utc( string $table, string $column ): bool {
		global $wpdb;
		$done = (array) get_option( 'wcb_utc_converted', array() );
		$key  = $table . '.' . $column;
		if ( in_array( $key, $done, true ) ) {
			return true;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off upgrade on a plugin table; names come from code.
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return true;
		}
		$oldest = (string) $wpdb->get_var( "SELECT MIN( {$column} ) FROM {$table}" );
		$sql    = '' === $oldest ? '' : self::local_to_utc_case( $column, $oldest );
		$ok = '' === $sql || false !== $wpdb->query( "UPDATE {$table} SET {$column} = {$sql}" );
		// phpcs:enable
		if ( $ok ) {
			update_option( 'wcb_utc_converted', array_merge( $done, array( $key ) ), false );
		}
		return $ok;
	}

	/**
	 * The SQL that turns a site-local column value into UTC, offset by offset.
	 *
	 * @since 1.8.3
	 *
	 * @param string $column Column name, from code.
	 * @param string $oldest Oldest local value in the column.
	 * @return string SQL expression, or '' when the zone is UTC throughout.
	 */
	private static function local_to_utc_case( string $column, string $oldest ): string {
		$zone  = wp_timezone();
		$start = strtotime( $oldest . ' UTC' ) - DAY_IN_SECONDS;
		$trans = $zone->getTransitions( $start, time() + DAY_IN_SECONDS );
		if ( ! is_array( $trans ) || array() === $trans ) {
			return '';
		}
		$whens  = array();
		$offset = (int) $trans[0]['offset'];
		foreach ( array_slice( $trans, 1 ) as $t ) {
			// The local wall time at which this offset stopped, as rows were written then.
			$until   = gmdate( 'Y-m-d H:i:s', (int) $t['ts'] + $offset );
			$whens[] = sprintf( "WHEN %s < '%s' THEN DATE_SUB( %s, INTERVAL %d SECOND )", $column, $until, $column, $offset );
			$offset  = (int) $t['offset'];
		}
		$last = sprintf( 'DATE_SUB( %s, INTERVAL %d SECOND )', $column, $offset );
		if ( array() === $whens ) {
			// One offset for the whole range (no daylight saving, or every row in one season).
			return 0 === $offset ? '' : $last;
		}
		return sprintf( 'CASE %s ELSE %s END', implode( ' ', $whens ), $last );
	}

	/**
	 * The display timezone for scripts (`timeZone` in Intl), so JavaScript
	 * never uses the browser's own.
	 *
	 * @since 1.8.3
	 * @return string IANA name or a numeric offset such as "+05:30".
	 */
	public static function js_timezone(): string {
		return self::display_timezone()->getName();
	}
}
