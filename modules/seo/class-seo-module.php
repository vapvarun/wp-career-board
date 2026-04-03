<?php
/**
 * SEO Module — JobPosting schema.org markup and OG meta tags for job listings.
 *
 * Outputs schema.org JSON-LD on single job pages and yields to Yoast SEO or
 * RankMath when those plugins are active (they own schema output).
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs JobPosting structured data and Open Graph meta tags on single job pages.
 *
 * @since 1.0.0
 */
class SeoModule {

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wp_head', array( $this, 'inject_schema' ) );
		add_action( 'wp_head', array( $this, 'inject_og_tags' ) );
		add_action( 'init', array( $this, 'maybe_disable_schema' ) );
	}

	/**
	 * Remove schema output when Yoast SEO or RankMath is active.
	 *
	 * Those plugins handle schema themselves — removing here prevents duplicates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_disable_schema(): void {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			remove_action( 'wp_head', array( $this, 'inject_schema' ) );
		}
	}

	/**
	 * Output JobPosting JSON-LD schema on single job pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function inject_schema(): void {
		if ( ! is_singular( 'wcb_job' ) ) {
			return;
		}

		$job = get_post();
		if ( ! $job instanceof \WP_Post ) {
			return;
		}

		$salary_min   = get_post_meta( $job->ID, '_wcb_salary_min', true );
		$salary_max   = get_post_meta( $job->ID, '_wcb_salary_max', true );
		$raw_currency = (string) get_post_meta( $job->ID, '_wcb_salary_currency', true );
		$currency     = $raw_currency ? $raw_currency : 'USD';
		$deadline     = (string) get_post_meta( $job->ID, '_wcb_deadline', true );
		$remote       = (bool) get_post_meta( $job->ID, '_wcb_remote', true );

		$deadline_ts   = '' !== $deadline ? strtotime( $deadline ) : false;
		$valid_through = ( false !== $deadline_ts ) ? gmdate( 'c', $deadline_ts ) : null;

		$schema = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => get_the_title( $job ),
			'description'        => wp_strip_all_tags( $job->post_content ),
			'datePosted'         => get_post_time( 'c', true, $job ),
			'validThrough'       => $valid_through,
			'employmentType'     => $this->get_employment_types( $job->ID ),
			'jobLocationType'    => $remote ? 'TELECOMMUTE' : null,
			'hiringOrganization' => $this->get_hiring_org( $job ),
		);

		if ( $salary_min || $salary_max ) {
			$schema['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => $currency,
				'value'    => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => $salary_min ? (float) $salary_min : null,
					'maxValue' => $salary_max ? (float) $salary_max : null,
					'unitText' => 'YEAR',
				),
			);
		}

		$schema = array_filter(
			$schema,
			static function ( $value ): bool {
				return null !== $value && array() !== $value;
			}
		);

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $json ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode produces safe JSON output.
			echo '<script type="application/ld+json">' . $json . "</script>\n";
		}
	}

	/**
	 * Output Open Graph meta tags on single job pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function inject_og_tags(): void {
		if ( ! is_singular( 'wcb_job' ) ) {
			return;
		}

		$job = get_post();
		if ( ! $job instanceof \WP_Post ) {
			return;
		}

		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( get_the_title( $job ) ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<meta property="og:description" content="' . esc_attr( wp_trim_words( wp_strip_all_tags( $job->post_content ), 30 ) ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $job ) ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Map wcb_job_type taxonomy terms to schema.org employment type values.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Post ID of the wcb_job.
	 * @return string[]
	 */
	private function get_employment_types( int $job_id ): array {
		$map = array(
			'full-time'  => 'FULL_TIME',
			'part-time'  => 'PART_TIME',
			'contract'   => 'CONTRACTOR',
			'internship' => 'INTERN',
			'freelance'  => 'CONTRACTOR',
		);

		$terms = wp_get_object_terms( $job_id, 'wcb_job_type', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$types = array();
		foreach ( $terms as $slug ) {
			$slug = (string) $slug;
			if ( isset( $map[ $slug ] ) ) {
				$types[] = $map[ $slug ];
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Build the hiringOrganization schema block from the job author's company.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $job The wcb_job post object.
	 * @return array<string, string>
	 */
	private function get_hiring_org( \WP_Post $job ): array {
		$author  = (int) $job->post_author;
		$comp_id = $author ? (int) get_user_meta( $author, '_wcb_company_id', true ) : 0;
		$name    = $comp_id ? (string) get_the_title( $comp_id ) : (string) get_bloginfo( 'name' );

		return array(
			'@type'  => 'Organization',
			'name'   => $name,
			'sameAs' => (string) get_bloginfo( 'url' ),
		);
	}
}
