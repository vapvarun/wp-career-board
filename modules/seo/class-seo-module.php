<?php
/**
 * SEO Module - Google for Jobs markup, company Organization markup and
 * social sharing tags.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Seo;

use WCB\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured data and Open Graph tags for job and company pages.
 *
 * JobPosting is built from the same job data the REST API returns
 * (JobsEndpoint::prepare_item_for_response_array), so the page markup and
 * the app never disagree. Yoast SEO and Rank Math do not emit JobPosting for
 * our post type, so the markup stays on alongside them; each output has its
 * own switch under Settings > Jobs.
 *
 * @since 1.0.0
 */
class SeoModule {

	/**
	 * Term meta on wcb_location: the country of that location.
	 *
	 * @var string
	 */
	public const COUNTRY_META = '_wcb_country';

	/**
	 * Job type slugs mapped to Google employmentType values.
	 *
	 * @var array<string, string>
	 */
	private const EMPLOYMENT_TYPES = array(
		'full-time'  => 'FULL_TIME',
		'part-time'  => 'PART_TIME',
		'contract'   => 'CONTRACTOR',
		'freelance'  => 'CONTRACTOR',
		'internship' => 'INTERN',
		'temporary'  => 'TEMPORARY',
		'volunteer'  => 'VOLUNTEER',
		'per-diem'   => 'PER_DIEM',
	);

	/**
	 * Salary type mapped to schema.org unitText.
	 *
	 * @var array<string, string>
	 */
	private const SALARY_UNITS = array(
		'hourly'  => 'HOUR',
		'monthly' => 'MONTH',
		'yearly'  => 'YEAR',
	);

	/**
	 * Boot the module.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'wp_head', array( $this, 'inject_schema' ) );
		add_action( 'wp_head', array( $this, 'inject_og_tags' ) );
		add_action( 'init', array( $this, 'register_country_meta' ) );
		add_action( 'wcb_location_add_form_fields', array( $this, 'country_field_add' ) );
		add_action( 'wcb_location_edit_form_fields', array( $this, 'country_field_edit' ) );
		add_action( 'created_wcb_location', array( $this, 'save_country' ) );
		add_action( 'edited_wcb_location', array( $this, 'save_country' ) );
	}

	/**
	 * The board's default country: the setting, else the site locale's region.
	 *
	 * @since 1.8.0
	 * @return string Country name or ISO 3166-1 alpha-2 code, or ''.
	 */
	public static function default_country(): string {
		$country = trim( Settings::string( 'default_country', '' ) );
		if ( '' !== $country ) {
			return $country;
		}
		$parts = explode( '_', get_locale() );
		return isset( $parts[1] ) && 2 === strlen( $parts[1] ) ? strtoupper( $parts[1] ) : '';
	}

	/**
	 * Output JobPosting or Organization JSON-LD.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function inject_schema(): void {
		if ( ! Settings::bool( 'job_schema_enabled', true ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( is_singular( 'wcb_job' ) ) {
			$schema = self::job_posting( $post );
		} elseif ( is_singular( 'wcb_company' ) ) {
			$schema = self::organization( $post->ID );
		} else {
			return;
		}
		if ( ! $schema ) {
			return;
		}
		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( false !== $json ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG encodes < and > so no value can close the script tag.
			echo '<script type="application/ld+json">' . $json . "</script>\n";
		}
	}

	/**
	 * JobPosting for a job, or [] when it takes no applications.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post $job Job post.
	 * @return array<string, mixed>
	 */
	public static function job_posting( \WP_Post $job ): array {
		// An ended job keeps its page but must not be offered as a JobPosting.
		if ( ! \WCB\Core\JobDeadline::accepts_applications( $job->ID ) ) {
			return array();
		}
		$data = ( new \WCB\Api\Endpoints\JobsEndpoint() )->prepare_item_for_response_array( $job );

		// Google rejects a JobPosting with no place to work: a location, a typed
		// location, or Remote. Leave such a job out (owner decision, 1.8.0); the
		// Jobs list marks it so the owner can fix it.
		list( $places, $remote ) = self::locations( $job, (bool) $data['remote'] );
		if ( ! $places && ! $remote ) {
			return array();
		}

		$company_id = \WCB\Core\CompanyMetaShape::for_job( $job );
		$org        = $company_id ? self::organization( $company_id ) : array();
		unset( $org['@context'], $org['description'], $org['address'] );
		if ( ! $org ) {
			$org = array(
				'@type' => 'Organization',
				'name'  => self::plain( '' !== $data['company'] ? (string) $data['company'] : (string) get_bloginfo( 'name' ) ),
			);
		}

		$default = self::default_country();

		$schema = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'JobPosting',
			'title'              => self::plain( (string) $data['title'] ),
			'description'        => wpautop( wp_kses_post( (string) $data['description'] ) ),
			'datePosted'         => get_post_time( 'c', true, $job ),
			'validThrough'       => self::valid_through( (string) $data['closes_at'] ),
			'employmentType'     => array_values( array_unique( array_intersect_key( self::EMPLOYMENT_TYPES, array_flip( (array) $data['job_types'] ) ) ) ),
			'hiringOrganization' => $org,
			'identifier'         => array(
				'@type' => 'PropertyValue',
				'name'  => $org['name'],
				'value' => (string) $job->ID,
			),
			'directApply'        => '' === (string) $data['apply_url'],
			'url'                => (string) $data['permalink'],
			'jobLocation'        => $places,
		);

		if ( $remote ) {
			$schema['jobLocationType'] = 'TELECOMMUTE';
			if ( '' !== $default ) {
				$schema['applicantLocationRequirements'] = array(
					'@type' => 'Country',
					'name'  => $default,
				);
			}
		}

		$min = (float) $data['salary_min'];
		$max = (float) $data['salary_max'];
		if ( $min > 0 || $max > 0 ) {
			$value = array( '@type' => 'QuantitativeValue' );
			if ( $min > 0 && $max > 0 && $min !== $max ) {
				$value['minValue'] = $min;
				$value['maxValue'] = $max;
			} else {
				$value['value'] = max( $min, $max );
			}
			$value['unitText']    = self::SALARY_UNITS[ (string) $data['salary_type'] ] ?? 'YEAR';
			$schema['baseSalary'] = array(
				'@type'    => 'MonetaryAmount',
				'currency' => (string) $data['salary_currency'],
				'value'    => $value,
			);
		}

		/**
		 * Filter the JobPosting structured data for a job.
		 *
		 * Return an empty array to leave a job out of Google for Jobs.
		 *
		 * @since 1.8.0
		 *
		 * @param array    $schema JobPosting data.
		 * @param \WP_Post $job    Job post.
		 * @param array    $data   The job's REST data.
		 */
		$schema = (array) apply_filters( 'wcb_job_posting_schema', self::compact( $schema ), $job, $data );
		return $schema;
	}

	/**
	 * Organization for a company page (also the job's hiringOrganization).
	 *
	 * @since 1.8.0
	 *
	 * @param int $company_id Company post ID.
	 * @return array<string, mixed>
	 */
	public static function organization( int $company_id ): array {
		if ( 'wcb_company' !== get_post_type( $company_id ) ) {
			return array();
		}
		$meta = \WCB\Core\CompanyMetaShape::serialize( $company_id );
		return self::compact(
			array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Organization',
				'name'        => self::plain( get_the_title( $company_id ) ),
				'url'         => (string) get_permalink( $company_id ),
				'sameAs'      => esc_url_raw( (string) get_post_meta( $company_id, '_wcb_website', true ) ),
				'logo'        => (string) get_the_post_thumbnail_url( $company_id, 'medium' ),
				'description' => $meta['tagline'],
				'address'     => '' !== $meta['hq'] ? self::place( $meta['hq'], self::default_country() )['address'] : '',
			)
		);
	}

	/**
	 * Where a job is done, as Google needs it: Places, and whether it is remote.
	 *
	 * The reserved Remote term means remote, not a place called "Remote"; the
	 * reserved Other term is a placeholder for a typed location, never a place.
	 * A typed location that repeats a term (the job form adds both) counts once.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post $job    Job.
	 * @param bool     $remote The job's Remote switch.
	 * @return array{0: array<int, array<string, mixed>>, 1: bool} Places and remote.
	 */
	private static function locations( \WP_Post $job, bool $remote ): array {
		$terms   = get_the_terms( $job->ID, 'wcb_location' );
		$default = self::default_country();
		$places  = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( 'remote' === $term->slug ) {
				$remote = true;
				continue;
			}
			if ( 'other' === $term->slug ) {
				continue;
			}
			$country                                  = trim( (string) get_term_meta( $term->term_id, self::COUNTRY_META, true ) );
			$places[ self::place_key( $term->name ) ] = self::place( $term->name, '' !== $country ? $country : $default );
		}
		$custom = trim( (string) get_post_meta( $job->ID, '_wcb_location_custom', true ) );
		if ( '' !== $custom && ! isset( $places[ self::place_key( $custom ) ] ) ) {
			$places[ self::place_key( $custom ) ] = self::place( $custom, $default );
		}
		return array( array_values( $places ), $remote );
	}

	/**
	 * Whether a job has a place Google can use (a location or Remote). The Jobs
	 * list uses it to mark the jobs left out of Google for Jobs.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Post $job Job.
	 * @return bool
	 */
	public static function has_location( \WP_Post $job ): bool {
		list( $places, $remote ) = self::locations( $job, '1' === (string) get_post_meta( $job->ID, '_wcb_remote', true ) );
		return $places || $remote;
	}

	/**
	 * Same place, however it was typed: case and spacing do not matter.
	 *
	 * @param string $name Place name.
	 * @return string
	 */
	private static function place_key( string $name ): string {
		return strtolower( preg_replace( '/\s+/', ' ', self::plain( $name ) ) );
	}

	/**
	 * Text as a person reads it: a stored "AT&amp;T" or "AT&#038;T" is "AT&T".
	 * JSON-LD is data, not HTML, so entities would show up literally.
	 *
	 * @param string $text Possibly entity-encoded text.
	 * @return string
	 */
	private static function plain( string $text ): string {
		return trim( html_entity_decode( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * A Place with a PostalAddress from a free-text location.
	 *
	 * @param string $locality Location text, e.g. "Austin, TX".
	 * @param string $country  Country name or code.
	 * @return array<string, mixed>
	 */
	private static function place( string $locality, string $country ): array {
		return array(
			'@type'   => 'Place',
			'address' => self::compact(
				array(
					'@type'           => 'PostalAddress',
					'addressLocality' => self::plain( $locality ),
					'addressCountry'  => $country,
				)
			),
		);
	}

	/**
	 * End of the closing day in the site's timezone, as ISO 8601.
	 *
	 * @param string $date Y-m-d, or ''.
	 * @return string
	 */
	private static function valid_through( string $date ): string {
		if ( '' === $date ) {
			return '';
		}
		try {
			return ( new \DateTimeImmutable( $date . ' 23:59:59', wp_timezone() ) )->format( 'c' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Drop empty values.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>
	 */
	private static function compact( array $data ): array {
		return array_filter( $data, static fn ( $v ): bool => null !== $v && '' !== $v && array() !== $v );
	}

	/**
	 * Output Open Graph and Twitter tags on job and company pages.
	 *
	 * Skipped when Yoast SEO or Rank Math is active: they print their own,
	 * and two sets of og:title confuse every share preview.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function inject_og_tags(): void {
		if ( ! Settings::bool( 'social_tags_enabled', true ) || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! is_singular( array( 'wcb_job', 'wcb_company' ) ) ) {
			return;
		}
		$company_id = 'wcb_company' === $post->post_type ? $post->ID : \WCB\Core\CompanyMetaShape::for_job( $post );
		$image      = (string) get_the_post_thumbnail_url( $post->ID, 'large' );
		if ( '' === $image && $company_id ) {
			$image = (string) get_the_post_thumbnail_url( $company_id, 'large' );
		}
		$tags = array(
			'og:type'        => 'article',
			'og:title'       => get_the_title( $post ),
			'og:description' => \WCB\Core\Text::excerpt( $post->post_content, 30 ),
			'og:url'         => (string) get_permalink( $post ),
			'og:site_name'   => (string) get_bloginfo( 'name' ),
			'og:image'       => $image,
		);
		foreach ( array_filter( $tags ) as $property => $content ) {
			$value = 'og:url' === $property || 'og:image' === $property ? esc_url( $content ) : esc_attr( $content );
			echo '<meta property="' . esc_attr( $property ) . '" content="' . $value . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
		echo '<meta name="twitter:card" content="' . ( '' !== $image ? 'summary_large_image' : 'summary' ) . '" />' . "\n";
	}

	/**
	 * Register the location country term meta (REST-visible for the app and importers).
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function register_country_meta(): void {
		register_term_meta(
			'wcb_location',
			self::COUNTRY_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn (): bool => wp_is_ability_granted( 'wcb/manage-settings' ), // phpcs:ignore -- ability polyfill, see core/abilities-api-polyfill.php
			)
		);
	}

	/**
	 * Country field on Job Locations > Add.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public function country_field_add(): void {
		?>
		<div class="form-field">
			<label for="wcb-location-country"><?php esc_html_e( 'Country', 'wp-career-board' ); ?></label>
			<input type="text" id="wcb-location-country" name="wcb_location_country" value="" placeholder="<?php echo esc_attr( self::default_country() ); ?>">
			<p><?php esc_html_e( 'Country name or 2-letter code, used for Google for Jobs. Leave empty to use the default country from Settings > Jobs.', 'wp-career-board' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Country field on Job Locations > Edit.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Term $term Term being edited.
	 * @return void
	 */
	public function country_field_edit( \WP_Term $term ): void {
		?>
		<tr class="form-field">
			<th scope="row"><label for="wcb-location-country"><?php esc_html_e( 'Country', 'wp-career-board' ); ?></label></th>
			<td>
				<input type="text" id="wcb-location-country" name="wcb_location_country" value="<?php echo esc_attr( (string) get_term_meta( $term->term_id, self::COUNTRY_META, true ) ); ?>" placeholder="<?php echo esc_attr( self::default_country() ); ?>">
				<p class="description"><?php esc_html_e( 'Country name or 2-letter code, used for Google for Jobs. Leave empty to use the default country from Settings > Jobs.', 'wp-career-board' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the country from the term screens (core verified the nonce and capability).
	 *
	 * @since 1.8.0
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_country( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- edit-tags.php / term.php verified the nonce before this hook.
		if ( ! isset( $_POST['wcb_location_country'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- as above.
		$country = sanitize_text_field( wp_unslash( (string) $_POST['wcb_location_country'] ) );
		if ( '' === $country ) {
			delete_term_meta( $term_id, self::COUNTRY_META );
		} else {
			update_term_meta( $term_id, self::COUNTRY_META, $country );
		}
	}
}
