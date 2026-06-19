<?php
/**
 * Integrations settings tab — WP Career Board companion plugins.
 *
 * One card per Companion_Registry entry: status badge (Connected /
 * Installed, activate / Not installed) + the matching action (one-click
 * free install, activate, or store link). No data is created here — the
 * screen reflects registry status and triggers installs through
 * Companion_Installer via admin-post.
 *
 * @package WP_Career_Board
 * @since   1.4.6
 */

defined( 'ABSPATH' ) || exit;

use WCB\Integrations\Companions\CompanionRegistry;

$wcb_companions = CompanionRegistry::all();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect-back status flags, no state change.
$wcb_install_state = isset( $_GET['wcb_install'] ) ? sanitize_key( wp_unslash( $_GET['wcb_install'] ) ) : '';
$wcb_install_msg   = isset( $_GET['wcb_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wcb_msg'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>

<?php if ( 'ok' === $wcb_install_state ) : ?>
	<div class="notice notice-success wcb-notice is-dismissible">
		<p><?php esc_html_e( 'Integration installed and activated.', 'wp-career-board' ); ?></p>
	</div>
<?php elseif ( 'error' === $wcb_install_state && '' !== $wcb_install_msg ) : ?>
	<div class="notice notice-error wcb-notice is-dismissible">
		<p><?php echo esc_html( $wcb_install_msg ); ?></p>
	</div>
<?php endif; ?>

<div class="wcb-card">
	<div class="wcb-card__head">
		<p class="wcb-card__title"><?php esc_html_e( 'Companion Plugins', 'wp-career-board' ); ?></p>
		<p class="wcb-card__desc"><?php esc_html_e( 'Extend your job board with the Wbcom stack. Each plugin works on its own — installing one here does not tie it to WP Career Board.', 'wp-career-board' ); ?></p>
	</div>
	<div class="wcb-integrations-grid">
		<?php
		foreach ( $wcb_companions as $wcb_slug => $wcb_c ) :
			$wcb_status  = CompanionRegistry::status( $wcb_slug );
			$wcb_label   = (string) ( $wcb_c['label'] ?? $wcb_slug );
			$wcb_why     = (string) ( $wcb_c['why'] ?? '' );
			$wcb_unlocks = (string) ( $wcb_c['unlocks'] ?? '' );
			$wcb_store   = (string) ( $wcb_c['store_url'] ?? '' );

			// Status badge variant + label.
			if ( 'active' === $wcb_status ) {
				$wcb_badge_class = 'wcb-integration-badge wcb-integration-badge--success';
				$wcb_badge_label = __( 'Connected', 'wp-career-board' );
			} elseif ( 'installed_inactive' === $wcb_status ) {
				$wcb_badge_class = 'wcb-integration-badge wcb-integration-badge--warning';
				$wcb_badge_label = __( 'Installed, activate', 'wp-career-board' );
			} else {
				$wcb_badge_class = 'wcb-integration-badge wcb-integration-badge--muted';
				$wcb_badge_label = __( 'Not installed', 'wp-career-board' );
			}
			?>
			<div class="wcb-integration-card">
				<div class="wcb-integration-card__head">
					<h3 class="wcb-integration-card__title"><?php echo esc_html( $wcb_label ); ?></h3>
					<span class="<?php echo esc_attr( $wcb_badge_class ); ?>"><?php echo esc_html( $wcb_badge_label ); ?></span>
				</div>

				<?php if ( '' !== $wcb_why ) : ?>
					<p class="wcb-integration-card__why"><?php echo esc_html( $wcb_why ); ?></p>
				<?php endif; ?>

				<?php if ( 'active' === $wcb_status && '' !== $wcb_unlocks ) : ?>
					<p class="wcb-integration-card__unlocks">
						<i data-lucide="check-circle-2" aria-hidden="true"></i>
						<?php echo esc_html( $wcb_unlocks ); ?>
					</p>
				<?php endif; ?>

				<div class="wcb-integration-card__actions">
					<?php if ( 'active' === $wcb_status ) : ?>
						<span class="wcb-btn wcb-btn--ghost wcb-btn--disabled" aria-disabled="true">
							<i data-lucide="check" aria-hidden="true"></i>
							<?php esc_html_e( 'Connected', 'wp-career-board' ); ?>
						</span>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wcb_install_companion">
							<input type="hidden" name="companion" value="<?php echo esc_attr( $wcb_slug ); ?>">
							<input type="hidden" name="tier" value="free">
							<?php wp_nonce_field( 'wcb_install_companion_nonce_' . $wcb_slug ); ?>
							<button type="submit" class="wcb-btn wcb-btn--primary">
								<?php
								echo 'installed_inactive' === $wcb_status
									? esc_html__( 'Activate', 'wp-career-board' )
									: esc_html__( 'Install free', 'wp-career-board' );
								?>
							</button>
						</form>
					<?php endif; ?>

					<?php if ( '' !== $wcb_store ) : ?>
						<a href="<?php echo esc_url( $wcb_store ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							class="wcb-btn wcb-btn--ghost">
							<?php esc_html_e( 'Visit store', 'wp-career-board' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<p class="description" style="margin-top:1rem">
	<?php esc_html_e( 'These plugins are standalone Wbcom products. WP Career Board detects them and lights up the matching features when present.', 'wp-career-board' ); ?>
</p>
