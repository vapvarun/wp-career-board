<?php
/**
 * Enrich the auto-generated /jobs/feed/ RSS output with job-specific fields.
 *
 * WordPress core auto-publishes /jobs/feed/ for any public CPT with
 * has_archive => true, but the default RSS template only carries title /
 * link / date / content. This module adds a `wcb:` XML namespace and a
 * small set of well-known job fields (salary, location, company, deadline,
 * remote flag, employment type) so syndication readers, IFTTT/Zapier
 * triggers, and aggregators have everything they need without parsing
 * post_content. Pro's /wcb-jobs.xml stays the canonical Indeed feed; this
 * is the Free counterpart for general RSS consumers.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks rss2_ns + rss2_item to inject WCB-namespaced job fields.
 *
 * @since 1.1.0
 */
final class RssFeedEnrichment {

	/**
	 * RSS namespace URI for our wcb: prefix.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const NS_URI = 'https://wbcomdesigns.com/xmlns/wcb/1.0/';

	/**
	 * Boot hooks.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function boot(): void {
		add_action( 'rss2_ns', array( $this, 'declare_namespace' ) );
		add_action( 'rss2_item', array( $this, 'render_item_fields' ) );
		add_filter( 'the_excerpt_rss', array( $this, 'enrich_excerpt' ) );
	}

	/**
	 * Declare the wcb: namespace on the <rss> root element.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function declare_namespace(): void {
		echo "\n\t" . 'xmlns:wcb="' . esc_url( self::NS_URI ) . '"';
	}

	/**
	 * Append wcb:* elements inside each <item> for wcb_job posts.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function render_item_fields(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || 'wcb_job' !== $post->post_type ) {
			return;
		}

		$company     = (string) get_post_meta( $post->ID, '_wcb_company_name', true );
		$apply_url   = (string) get_post_meta( $post->ID, '_wcb_apply_url', true );
		$apply_email = (string) get_post_meta( $post->ID, '_wcb_apply_email', true );
		$min         = (string) get_post_meta( $post->ID, '_wcb_salary_min', true );
		$max         = (string) get_post_meta( $post->ID, '_wcb_salary_max', true );
		$currency    = (string) get_post_meta( $post->ID, '_wcb_salary_currency', true );
		$type        = (string) get_post_meta( $post->ID, '_wcb_salary_type', true );
		$remote      = '1' === (string) get_post_meta( $post->ID, '_wcb_remote', true );
		$deadline    = (string) get_post_meta( $post->ID, '_wcb_deadline', true );
		$loc_terms   = wp_get_object_terms( $post->ID, 'wcb_location', array( 'fields' => 'names' ) );
		$type_terms  = wp_get_object_terms( $post->ID, 'wcb_job_type', array( 'fields' => 'names' ) );
		$cat_terms   = wp_get_object_terms( $post->ID, 'wcb_category', array( 'fields' => 'names' ) );
		$tag_terms   = wp_get_object_terms( $post->ID, 'wcb_tag', array( 'fields' => 'names' ) );
		$exp_terms   = wp_get_object_terms( $post->ID, 'wcb_experience', array( 'fields' => 'names' ) );

		// Stable per-job reference for dedup on the aggregator side.
		echo "\t<wcb:reference>" . esc_html( (string) $post->ID ) . "</wcb:reference>\n";
		echo "\t<wcb:posted>" . esc_html( mysql_to_rfc3339( $post->post_date_gmt ) ) . "</wcb:posted>\n";

		if ( '' !== $company ) {
			echo "\t<wcb:company>" . esc_html( $company ) . "</wcb:company>\n";
		}
		if ( '' !== $apply_url ) {
			echo "\t<wcb:apply_url>" . esc_url( $apply_url ) . "</wcb:apply_url>\n";
		}
		if ( '' !== $apply_email && is_email( $apply_email ) ) {
			echo "\t<wcb:apply_email>" . esc_html( $apply_email ) . "</wcb:apply_email>\n";
		}
		if ( $min || $max ) {
			$attrs = '';
			if ( '' !== $currency ) {
				$attrs .= ' currency="' . esc_attr( $currency ) . '"';
			}
			if ( '' !== $type ) {
				$attrs .= ' period="' . esc_attr( $type ) . '"';
			}
			echo "\t<wcb:salary{$attrs}>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs already escaped.
			if ( $min ) {
				echo '<wcb:min>' . esc_html( $min ) . '</wcb:min>';
			}
			if ( $max ) {
				echo '<wcb:max>' . esc_html( $max ) . '</wcb:max>';
			}
			echo "</wcb:salary>\n";
		}
		if ( ! is_wp_error( $loc_terms ) && ! empty( $loc_terms ) ) {
			foreach ( $loc_terms as $loc ) {
				echo "\t<wcb:location>" . esc_html( (string) $loc ) . "</wcb:location>\n";
			}
		}
		if ( ! is_wp_error( $type_terms ) && ! empty( $type_terms ) ) {
			foreach ( $type_terms as $job_type ) {
				echo "\t<wcb:type>" . esc_html( (string) $job_type ) . "</wcb:type>\n";
			}
		}
		if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) {
			foreach ( $cat_terms as $cat ) {
				echo "\t<wcb:category>" . esc_html( (string) $cat ) . "</wcb:category>\n";
			}
		}
		if ( ! is_wp_error( $tag_terms ) && ! empty( $tag_terms ) ) {
			foreach ( $tag_terms as $tag ) {
				echo "\t<wcb:tag>" . esc_html( (string) $tag ) . "</wcb:tag>\n";
			}
		}
		if ( ! is_wp_error( $exp_terms ) && ! empty( $exp_terms ) ) {
			foreach ( $exp_terms as $exp ) {
				echo "\t<wcb:experience>" . esc_html( (string) $exp ) . "</wcb:experience>\n";
			}
		}
		echo "\t<wcb:remote>" . ( $remote ? 'true' : 'false' ) . "</wcb:remote>\n";
		if ( '' !== $deadline ) {
			echo "\t<wcb:deadline>" . esc_html( $deadline ) . "</wcb:deadline>\n";
		}
	}

	/**
	 * Prepend a one-line human-readable summary (company • location • salary)
	 * to the RSS description so basic readers that ignore custom namespaces
	 * still see useful job context above the post excerpt.
	 *
	 * @since 1.1.0
	 *
	 * @param string $excerpt Default RSS excerpt.
	 * @return string
	 */
	public function enrich_excerpt( string $excerpt ): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || 'wcb_job' !== $post->post_type ) {
			return $excerpt;
		}

		$bits      = array();
		$company   = (string) get_post_meta( $post->ID, '_wcb_company_name', true );
		$min       = (int) get_post_meta( $post->ID, '_wcb_salary_min', true );
		$max       = (int) get_post_meta( $post->ID, '_wcb_salary_max', true );
		$currency  = (string) get_post_meta( $post->ID, '_wcb_salary_currency', true );
		$loc_terms = wp_get_object_terms( $post->ID, 'wcb_location', array( 'fields' => 'names' ) );
		$remote    = '1' === (string) get_post_meta( $post->ID, '_wcb_remote', true );

		if ( '' !== $company ) {
			$bits[] = $company;
		}
		if ( $remote ) {
			$bits[] = __( 'Remote', 'wp-career-board' );
		} elseif ( ! is_wp_error( $loc_terms ) && ! empty( $loc_terms ) ) {
			$bits[] = (string) $loc_terms[0];
		}
		if ( $min || $max ) {
			$range  = $min && $max ? $min . '–' . $max : ( $min ? $min . '+' : '≤' . $max );
			$bits[] = trim( $currency . ' ' . $range );
		}

		if ( empty( $bits ) ) {
			return $excerpt;
		}

		$summary = implode( ' • ', $bits );
		return $summary . "\n\n" . $excerpt;
	}
}
