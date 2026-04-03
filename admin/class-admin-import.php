<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- hyphenated name matches WP convention for multi-word classes.
/**
 * Admin Import page — one-click migration from WP Job Manager.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

use WCB\Import\WpjmImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Career Board > Import admin page.
 *
 * @since 1.0.0
 */
class AdminImport {

	/**
	 * Render the Import page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render(): void {
		$importer         = new WpjmImporter();
		$wpjm_jobs        = post_type_exists( 'job_listing' );
		$wpjm_resumes     = post_type_exists( 'resume' );
		$jobs_total       = $importer->wpjm_jobs_total();
		$jobs_migrated    = $importer->wcb_jobs_migrated();
		$resumes_total    = $importer->wpjm_resumes_total();
		$resumes_migrated = $importer->wcb_resumes_migrated();
		?>
		<div class="wrap wcb-admin wcb-admin-import">

			<div class="wcb-page-header">
				<div class="wcb-page-header__left">
					<h2 class="wcb-page-header__title">
						<i data-lucide="upload" class="wcb-icon--lg"></i>
						<?php esc_html_e( 'Import & Migration', 'wp-career-board' ); ?>
					</h2>
					<p class="wcb-page-header__desc"><?php esc_html_e( 'Move your existing job board data into WP Career Board. Your original data is never modified or deleted.', 'wp-career-board' ); ?></p>
				</div>
			</div>

			<p class="wcb-import-intro">
				<?php esc_html_e( 'Each migration is safe to run multiple times — already-imported records are automatically skipped.', 'wp-career-board' ); ?>
			</p>

			<?php /* ── WP Job Manager — Jobs ── */ ?>
			<div class="wcb-import-card" id="wcb-import-wpjm-jobs"
				data-type="wpjm-jobs"
				data-total="<?php echo (int) $jobs_total; ?>">

				<div class="wcb-import-card-head">
					<div class="wcb-import-card-title">
						<i data-lucide="briefcase"></i>
						<?php esc_html_e( 'WP Job Manager → Jobs', 'wp-career-board' ); ?>
					</div>
					<?php if ( $wpjm_jobs ) : ?>
						<span class="wcb-import-badge wcb-import-badge--active"><?php esc_html_e( 'Plugin active', 'wp-career-board' ); ?></span>
					<?php else : ?>
						<span class="wcb-import-badge wcb-import-badge--inactive"><?php esc_html_e( 'Plugin not active', 'wp-career-board' ); ?></span>
					<?php endif; ?>
				</div>

				<p class="wcb-import-desc">
					<?php esc_html_e( 'Migrates job_listing posts to wcb_job. No data is lost — all fields are preserved.', 'wp-career-board' ); ?>
				</p>

				<div class="wcb-import-fields">
					<strong><?php esc_html_e( 'Fields covered:', 'wp-career-board' ); ?></strong>
					<?php
					echo esc_html(
						implode(
							' · ',
							array(
								__( 'Title & description', 'wp-career-board' ),
								__( 'Location', 'wp-career-board' ),
								__( 'Salary (min/max)', 'wp-career-board' ),
								__( 'Currency & pay type', 'wp-career-board' ),
								__( 'Deadline / duration', 'wp-career-board' ),
								__( 'Featured flag', 'wp-career-board' ),
								__( 'Remote flag', 'wp-career-board' ),
								__( 'Application email / URL', 'wp-career-board' ),
								__( 'Company name, website, tagline, Twitter, logo, video', 'wp-career-board' ),
								__( 'Filled → closed status', 'wp-career-board' ),
								__( 'Categories & job types', 'wp-career-board' ),
							)
						)
					);
					?>
				</div>

				<?php if ( $wpjm_jobs ) : ?>
					<div class="wcb-import-stats">
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num"><?php echo (int) $jobs_total; ?></strong>
							<?php esc_html_e( 'found', 'wp-career-board' ); ?>
						</span>
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num wcb-import-stat-migrated"><?php echo (int) $jobs_migrated; ?></strong>
							<?php esc_html_e( 'already imported', 'wp-career-board' ); ?>
						</span>
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num wcb-import-stat-remaining"><?php echo (int) max( 0, $jobs_total - $jobs_migrated ); ?></strong>
							<?php esc_html_e( 'remaining', 'wp-career-board' ); ?>
						</span>
					</div>

					<div class="wcb-import-actions">
						<?php if ( $jobs_total > 0 ) : ?>
							<button type="button" class="wcb-btn wcb-btn--primary wcb-import-start"
								data-type="wpjm-jobs">
								<?php esc_html_e( 'Import All Jobs', 'wp-career-board' ); ?>
							</button>
						<?php else : ?>
							<span class="wcb-import-empty"><?php esc_html_e( 'No jobs found in WP Job Manager.', 'wp-career-board' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="wcb-import-progress-wrap" style="display:none">
						<div class="wcb-import-progress-bar-track">
							<div class="wcb-import-progress-bar-fill" style="width:0%"></div>
						</div>
						<p class="wcb-import-progress-label"></p>
					</div>

					<div class="wcb-import-log" style="display:none"></div>

				<?php else : ?>
					<p class="wcb-import-notice">
						<?php
						printf(
							/* translators: %s: plugin name */
							esc_html__( '%s is not installed or not active.', 'wp-career-board' ),
							'<strong>WP Job Manager</strong>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php /* ── WP Job Manager Resumes ── */ ?>
			<div class="wcb-import-card" id="wcb-import-wpjm-resumes"
				data-type="wpjm-resumes"
				data-total="<?php echo (int) $resumes_total; ?>">

				<div class="wcb-import-card-head">
					<div class="wcb-import-card-title">
						<i data-lucide="file-user"></i>
						<?php esc_html_e( 'WP Job Manager Resumes → Resumes', 'wp-career-board' ); ?>
					</div>
					<?php if ( $wpjm_resumes ) : ?>
						<span class="wcb-import-badge wcb-import-badge--active"><?php esc_html_e( 'Plugin active', 'wp-career-board' ); ?></span>
					<?php else : ?>
						<span class="wcb-import-badge wcb-import-badge--inactive"><?php esc_html_e( 'Plugin not active', 'wp-career-board' ); ?></span>
					<?php endif; ?>
				</div>

				<p class="wcb-import-desc">
					<?php esc_html_e( 'Migrates resume posts to wcb_resume. All candidate data is preserved — nothing is lost.', 'wp-career-board' ); ?>
				</p>

				<div class="wcb-import-fields">
					<strong><?php esc_html_e( 'Fields covered:', 'wp-career-board' ); ?></strong>
					<?php
					echo esc_html(
						implode(
							' · ',
							array(
								__( 'Candidate name & bio', 'wp-career-board' ),
								__( 'Professional title', 'wp-career-board' ),
								__( 'Contact email', 'wp-career-board' ),
								__( 'Location', 'wp-career-board' ),
								__( 'Photo', 'wp-career-board' ),
								__( 'Video URL', 'wp-career-board' ),
								__( 'Resume file', 'wp-career-board' ),
								__( 'Featured flag', 'wp-career-board' ),
								__( 'Expiry date', 'wp-career-board' ),
								__( 'Education history', 'wp-career-board' ),
								__( 'Work experience', 'wp-career-board' ),
								__( 'Social / website links', 'wp-career-board' ),
								__( 'Resume categories', 'wp-career-board' ),
							)
						)
					);
					?>
				</div>

				<?php if ( $wpjm_resumes ) : ?>
					<div class="wcb-import-stats">
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num"><?php echo (int) $resumes_total; ?></strong>
							<?php esc_html_e( 'found', 'wp-career-board' ); ?>
						</span>
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num wcb-import-stat-migrated"><?php echo (int) $resumes_migrated; ?></strong>
							<?php esc_html_e( 'already imported', 'wp-career-board' ); ?>
						</span>
						<span class="wcb-import-stat">
							<strong class="wcb-import-stat-num wcb-import-stat-remaining"><?php echo (int) max( 0, $resumes_total - $resumes_migrated ); ?></strong>
							<?php esc_html_e( 'remaining', 'wp-career-board' ); ?>
						</span>
					</div>

					<div class="wcb-import-actions">
						<?php if ( $resumes_total > 0 ) : ?>
							<button type="button" class="wcb-btn wcb-btn--primary wcb-import-start"
								data-type="wpjm-resumes">
								<?php esc_html_e( 'Import All Resumes', 'wp-career-board' ); ?>
							</button>
						<?php else : ?>
							<span class="wcb-import-empty"><?php esc_html_e( 'No resumes found in WP Job Manager Resumes.', 'wp-career-board' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="wcb-import-progress-wrap" style="display:none">
						<div class="wcb-import-progress-bar-track">
							<div class="wcb-import-progress-bar-fill" style="width:0%"></div>
						</div>
						<p class="wcb-import-progress-label"></p>
					</div>

					<div class="wcb-import-log" style="display:none"></div>

				<?php else : ?>
					<p class="wcb-import-notice">
						<?php
						printf(
							/* translators: %s: plugin name */
							esc_html__( '%s is not installed or not active.', 'wp-career-board' ),
							'<strong>WP Job Manager Resumes</strong>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires after the last built-in import card on the Import page.
			 *
			 * Pro uses this to inject the CSV importer card without adding a separate menu page.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wcb_import_extra_cards' );
			?>

		</div><!-- .wrap -->
		<?php
	}
}
