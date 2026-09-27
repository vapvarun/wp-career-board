<?php
/**
 * Jobs postmeta helpers.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers postmeta for the wcb_job CPT so values are exposed in the REST API.
 *
 * @since 1.0.0
 */
final class JobsMeta {

	/**
	 * Boot the meta registration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register each job postmeta key for REST API exposure.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta(): void {
		// Each sanitizer runs on every update_post_meta() for the key, so REST,
		// the admin meta box, both importers and the wizard share one rule set.
		// A raw value from any writer once reached the JobPosting JSON-LD as a
		// stored XSS (salary_currency).
		$meta_fields = array(
			'_wcb_deadline'        => array( 'string', array( self::class, 'sanitize_date' ) ),
			'_wcb_salary_min'      => array( 'number', array( self::class, 'sanitize_amount' ) ),
			'_wcb_salary_max'      => array( 'number', array( self::class, 'sanitize_amount' ) ),
			'_wcb_salary_currency' => array( 'string', array( self::class, 'sanitize_currency' ) ),
			'_wcb_remote'          => array( 'string', array( self::class, 'sanitize_flag' ) ),
			'_wcb_featured'        => array( 'string', array( self::class, 'sanitize_flag' ) ),
			'_wcb_board_id'        => array( 'integer', array( self::class, 'sanitize_board_id' ) ),
			'_wcb_salary_type'     => array( 'string', array( self::class, 'sanitize_salary_type' ) ),
			'_wcb_apply_url'       => array( 'string', 'esc_url_raw' ),
			'_wcb_apply_email'     => array( 'string', 'sanitize_email' ),
			'_wcb_company_id'      => array( 'integer', 'absint' ),
			'_wcb_company_name'    => array( 'string', 'sanitize_text_field' ),
		);

		foreach ( $meta_fields as $key => $schema ) {
			register_post_meta(
				'wcb_job',
				$key,
				array(
					// The apply email is left out of core REST on purpose: the
					// wcb/v1 response already strips it, and /wp/v2/wcb_job
					// handed it out in bulk to anyone.
					'show_in_rest'      => '_wcb_apply_email' !== $key,
					'single'            => true,
					'type'              => $schema[0],
					'sanitize_callback' => $schema[1],
					'auth_callback'     => static function (): bool {
						return wp_is_ability_granted( 'wcb/post-jobs' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
					},
				)
			);
		}
	}

	/**
	 * Keep a `Y-m-d` date, drop anything else.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_date( mixed $value ): string {
		$value = trim( (string) $value );
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return ( $date && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Keep a non-negative number, drop anything else.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_amount( mixed $value ): string {
		return ( is_numeric( $value ) && (float) $value >= 0 ) ? (string) ( 0 + $value ) : '';
	}

	/**
	 * Keep a currency code from the catalog, else the site default.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_currency( mixed $value ): string {
		$value = strtoupper( trim( (string) $value ) );
		if ( array_key_exists( $value, \WCB\Admin\AdminSettings::get_currency_catalog() ) ) {
			return $value;
		}
		return strtoupper( \WCB\Admin\Settings::string( 'salary_currency', 'USD' ) );
	}

	/**
	 * Normalise a boolean flag to '1' / '0'.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_flag( mixed $value ): string {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
	}

	/**
	 * Keep a salary period from the allowed set.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_salary_type( mixed $value ): string {
		return in_array( $value, array( 'yearly', 'monthly', 'hourly' ), true ) ? (string) $value : 'yearly';
	}

	/**
	 * Keep an existing board ID, else the default board.
	 *
	 * @since  1.8.0
	 * @param  mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_board_id( mixed $value ): int {
		$board_id = absint( $value );
		if ( $board_id > 0 && 'wcb_board' === get_post_type( $board_id ) ) {
			return $board_id;
		}
		return (int) \WCB\Modules\Boards\BoardsModule::get_default_board_id();
	}
}
