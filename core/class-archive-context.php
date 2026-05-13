<?php
/**
 * Archive context DTO - bundle of metadata each archive partial needs.
 *
 * Without this, every partial would receive 5-9 standalone args and each
 * archive render.php would have to remember the exact order and key names.
 * The DTO captures the canonical shape (singular / plural / nouns / state
 * namespace / etc.) so partials read from a single typed object.
 *
 * Created once at the top of each archive render.php and threaded into the
 * partials via `$wcb_ctx`. Pro consumes the same class.
 *
 * @package WP_Career_Board
 * @since   1.2.7
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable archive metadata bundle.
 *
 * @since 1.2.7
 */
final class ArchiveContext {

	/**
	 * Slug identifier for the archive kind (jobs, companies, resumes).
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $kind;

	/**
	 * Singular noun for UI labels (e.g. "job", "company", "resume").
	 *
	 * Translated by the caller before passing in - this DTO is not the
	 * place to call __(); use it where the strings need to be picked up
	 * by gettext extraction.
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $singular;

	/**
	 * Plural noun for UI labels (e.g. "jobs", "companies", "resumes").
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $plural;

	/**
	 * Interactivity API store namespace (e.g. "wcb-job-listings").
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $namespace;

	/**
	 * Post type slug (e.g. "wcb_job").
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $post_type;

	/**
	 * REST base path under /wcb/v1/ (e.g. "jobs", "companies", "resumes").
	 *
	 * @since 1.2.7
	 * @var string
	 */
	public string $rest_base;

	/**
	 * @param array{
	 *   kind: string,
	 *   singular: string,
	 *   plural: string,
	 *   namespace: string,
	 *   post_type: string,
	 *   rest_base: string,
	 * } $args
	 */
	public function __construct( array $args ) {
		$this->kind      = (string) ( $args['kind'] ?? '' );
		$this->singular  = (string) ( $args['singular'] ?? '' );
		$this->plural    = (string) ( $args['plural'] ?? '' );
		$this->namespace = (string) ( $args['namespace'] ?? '' );
		$this->post_type = (string) ( $args['post_type'] ?? '' );
		$this->rest_base = (string) ( $args['rest_base'] ?? '' );
	}

	/**
	 * Convenience factory for Find Jobs.
	 *
	 * @since 1.2.7
	 * @return self
	 */
	public static function jobs(): self {
		return new self(
			array(
				'kind'      => 'jobs',
				'singular'  => __( 'job', 'wp-career-board' ),
				'plural'    => __( 'jobs', 'wp-career-board' ),
				'namespace' => 'wcb-job-listings',
				'post_type' => 'wcb_job',
				'rest_base' => 'jobs',
			)
		);
	}

	/**
	 * Convenience factory for Companies.
	 *
	 * @since 1.2.7
	 * @return self
	 */
	public static function companies(): self {
		return new self(
			array(
				'kind'      => 'companies',
				'singular'  => __( 'company', 'wp-career-board' ),
				'plural'    => __( 'companies', 'wp-career-board' ),
				'namespace' => 'wcb-company-archive',
				'post_type' => 'wcb_company',
				'rest_base' => 'companies',
			)
		);
	}

	/**
	 * Convenience factory for Find Candidates (Pro).
	 *
	 * Pro consumes this class directly via the upscale model (Pro always
	 * extends Free; the dependency guard ensures Free is loaded). Text
	 * domain on the labels passes through caller-supplied strings so the
	 * Pro-side label gets the 'wp-career-board-pro' domain extracted to
	 * the Pro .pot file - call from Pro with custom singular/plural args
	 * instead of relying on this factory.
	 *
	 * @since 1.2.7
	 *
	 * @param string $singular Caller-translated singular noun.
	 * @param string $plural   Caller-translated plural noun.
	 * @return self
	 */
	public static function resumes( string $singular = '', string $plural = '' ): self {
		return new self(
			array(
				'kind'      => 'resumes',
				'singular'  => '' !== $singular ? $singular : 'resume',
				'plural'    => '' !== $plural ? $plural : 'resumes',
				'namespace' => 'wcb-resume-archive',
				'post_type' => 'wcb_resume',
				'rest_base' => 'resumes',
			)
		);
	}
}
