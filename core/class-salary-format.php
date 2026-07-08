<?php
/**
 * Canonical salary / money formatter.
 *
 * Before 1.5.1 the same logic existed three times, all emitting hardcoded
 * English and all bypassing number_format_i18n():
 *
 *   - api/endpoints/class-jobs-endpoint.php::format_salary()   (fed salary_label into REST + mobile)
 *   - blocks/job-listings/render.php  $wcb_format_salary closure
 *   - blocks/job-listings/view.js     wcbFormatSalaryShort()   (a JS reimplementation)
 *
 * Every fragment a human reads is now translatable, amounts run through
 * number_format_i18n(), and the currency symbol's POSITION is a translatable
 * format string rather than a hardcoded prefix -- fr_FR/de_DE/sv_SE render
 * "1 000 €", not "€1000". The currency catalog only carries {name, symbol},
 * so position cannot be derived from data; letting translators reorder the
 * placeholders is the correct gettext answer.
 *
 * @package WP_Career_Board
 * @since   1.5.1
 */

declare( strict_types=1 );

namespace WCB\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Formats salary figures for display, REST payloads and the mobile app.
 */
final class SalaryFormat {

	/**
	 * Resolve a currency code to its display symbol.
	 *
	 * Falls back to the uppercased code plus a space (e.g. "PLN ") when the
	 * catalog has no entry, which is the same behaviour the three legacy
	 * formatters had.
	 *
	 * @param string $currency ISO 4217 code.
	 * @return string
	 */
	public static function symbol( string $currency ): string {
		$catalog = \WCB\Admin\AdminSettings::get_currency_catalog();
		$code    = strtoupper( $currency );

		return isset( $catalog[ $code ]['symbol'] )
			? (string) $catalog[ $code ]['symbol']
			: $code . ' ';
	}

	/**
	 * The site locale as a BCP-47 tag, for Intl.NumberFormat on the JS side.
	 *
	 * Seed this into wp_interactivity_state() so view.js never calls
	 * toLocaleString() with no locale argument (which formats against the
	 * BROWSER locale, not the site's).
	 *
	 * @return string
	 */
	public static function locale(): string {
		return str_replace( '_', '-', get_user_locale() );
	}

	/**
	 * Abbreviate a figure, localising the digits and the unit.
	 *
	 * @param int $value Raw amount.
	 * @return string
	 */
	public static function abbreviate( int $value ): string {
		if ( $value >= 1000000 ) {
			$n = round( $value / 1000000, 1 );
			$n = floor( $n ) === $n ? (string) (int) $n : number_format_i18n( $n, 1 );
			/* translators: %s: number of millions, already localised. Abbreviation for millions appended to a salary figure. */
			return sprintf( _x( '%sM', 'millions abbreviation', 'wp-career-board' ), $n );
		}

		if ( $value >= 1000 ) {
			/* translators: %s: number of thousands, already localised. Abbreviation for thousands appended to a salary figure. */
			return sprintf( _x( '%sk', 'thousands abbreviation', 'wp-career-board' ), number_format_i18n( (int) round( $value / 1000 ) ) );
		}

		return number_format_i18n( $value );
	}

	/**
	 * Combine a currency symbol with an already-localised amount.
	 *
	 * The placeholders are numbered so a translator can swap them: a French
	 * locale ships "%2$s %1$s" to render "1 000 €".
	 *
	 * @param string $symbol Currency symbol.
	 * @param string $amount Localised amount.
	 * @return string
	 */
	public static function money( string $symbol, string $amount ): string {
		/* translators: 1: currency symbol, 2: localised amount. Swap the order (and add a space) for locales that place the symbol after the amount, e.g. "%2$s %1$s". */
		return sprintf( _x( '%1$s%2$s', 'currency symbol then amount', 'wp-career-board' ), $symbol, $amount );
	}

	/**
	 * The "/yr", "/mo", "/hr" suffix for a pay period.
	 *
	 * @param string $type yearly|monthly|hourly.
	 * @return string
	 */
	public static function period_suffix( string $type ): string {
		switch ( $type ) {
			case 'monthly':
				/* translators: Abbreviation for "per month", appended to a salary figure. */
				return _x( '/mo', 'salary period', 'wp-career-board' );
			case 'hourly':
				/* translators: Abbreviation for "per hour", appended to a salary figure. */
				return _x( '/hr', 'salary period', 'wp-career-board' );
			default:
				/* translators: Abbreviation for "per year", appended to a salary figure. */
				return _x( '/yr', 'salary period', 'wp-career-board' );
		}
	}

	/**
	 * Format a salary range for display.
	 *
	 * Returns an empty string when neither bound is set, matching the legacy
	 * formatters so callers that test for truthiness keep working.
	 *
	 * @param string|int $min      Minimum figure, or empty.
	 * @param string|int $max      Maximum figure, or empty.
	 * @param string     $currency ISO 4217 code.
	 * @param string     $type     yearly|monthly|hourly.
	 * @return string
	 */
	public static function format( $min, $max, string $currency = 'USD', string $type = 'yearly' ): string {
		$min = (int) $min;
		$max = (int) $max;

		if ( ! $min && ! $max ) {
			return '';
		}

		$symbol = self::symbol( $currency );
		$suffix = self::period_suffix( $type );

		$fmt = static fn ( int $n ): string => self::money( $symbol, self::abbreviate( $n ) );

		if ( $min && $max ) {
			/* translators: 1: minimum salary, 2: maximum salary. En dash separator; change it if your locale uses another range mark. */
			$body = sprintf( _x( '%1$s–%2$s', 'salary range', 'wp-career-board' ), $fmt( $min ), $fmt( $max ) );
		} elseif ( $min ) {
			/* translators: %s: minimum salary. Trailing marker meaning "and above". */
			$body = sprintf( _x( '%s+', 'open-ended salary minimum', 'wp-career-board' ), $fmt( $min ) );
		} else {
			/* translators: %s: maximum salary. */
			$body = sprintf( __( 'Up to %s', 'wp-career-board' ), $fmt( $max ) );
		}

		// Join figure and period suffix through a format string so a translator
		// can reposition the suffix (or drop the separator) rather than being
		// locked into "figure then suffix".
		/* translators: 1: salary figure, 2: pay-period suffix such as "/yr". */
		return sprintf( _x( '%1$s%2$s', 'salary figure then period', 'wp-career-board' ), $body, $suffix );
	}

	/**
	 * Strings a view.js needs to format figures client-side (filter sliders,
	 * where the value changes without a server round-trip).
	 *
	 * Seed under the block's `i18n` key; read via t(). `locale` is seeded
	 * separately at state root so Intl.NumberFormat can be given a locale.
	 *
	 * @return array<string,string>
	 */
	public static function js_strings(): array {
		return array(
			/* translators: 1: currency symbol, 2: localised amount. */
			'moneyFormat'     => _x( '%1$s%2$s', 'currency symbol then amount', 'wp-career-board' ),
			/* translators: 1: salary figure, 2: pay-period suffix such as "/yr". */
			'salaryJoinFormat' => _x( '%1$s%2$s', 'salary figure then period', 'wp-career-board' ),
			/* translators: %s: number of thousands. */
			'salaryThousand'  => _x( '%sk', 'thousands abbreviation', 'wp-career-board' ),
			/* translators: %s: number of millions. */
			'salaryMillion'   => _x( '%sM', 'millions abbreviation', 'wp-career-board' ),
			/* translators: 1: minimum salary, 2: maximum salary. */
			'salaryRange'     => _x( '%1$s–%2$s', 'salary range', 'wp-career-board' ),
			/* translators: %s: minimum salary. */
			'salaryOpenMin'   => _x( '%s+', 'open-ended salary minimum', 'wp-career-board' ),
			/* translators: %s: maximum salary. */
			'salaryUpTo'      => __( 'Up to %s', 'wp-career-board' ),
			'salaryPerYear'   => self::period_suffix( 'yearly' ),
			'salaryPerMonth'  => self::period_suffix( 'monthly' ),
			'salaryPerHour'   => self::period_suffix( 'hourly' ),
		);
	}
}
