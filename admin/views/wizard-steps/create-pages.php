<?php
/**
 * Wizard step partial: Create Pages.
 *
 * @package WP_Career_Board
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><?php esc_html_e( 'Your job board pages', 'wp-career-board' ); ?></div>
	<div class="wcb-settings-row-control">
		<p><?php esc_html_e( 'Pages you already have are kept. The rest are created for you:', 'wp-career-board' ); ?></p>
		<ul class="wcb-wizard-pages">
			<?php foreach ( \WCB\Admin\Pages::definitions() as $wcb_key => $wcb_def ) : ?>
				<?php $wcb_exists = \WCB\Admin\Pages::get_id( $wcb_key ) > 0; ?>
				<li>
					<?php echo esc_html( $wcb_def['title'] ); ?>
					<span class="wcb-wizard-pages__state<?php echo $wcb_exists ? ' is-ready' : ''; ?>">
						<?php $wcb_exists ? esc_html_e( 'Already set up', 'wp-career-board' ) : esc_html_e( 'Will be created', 'wp-career-board' ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<div class="wcb-settings-footer wcb-wizard-footer">
	<button type="button" class="wcb-btn wcb-btn--primary" id="wcb-create-pages" data-wcb-wizard-action="create-pages">
		<?php esc_html_e( 'Create Pages & Continue', 'wp-career-board' ); ?>
	</button>
	<span class="wcb-wizard-error" role="alert"></span>
</div>
