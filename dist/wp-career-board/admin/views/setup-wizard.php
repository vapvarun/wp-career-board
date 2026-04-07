<?php
/**
 * Admin view: setup wizard — dynamic multi-step first-run configuration.
 *
 * Steps are defined by SetupWizard::get_steps() and rendered in a loop.
 * Each step loads a template partial for its body content.
 * All interactivity is handled by assets/js/wizard.js using wp-api-fetch.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 *
 * @var array<string, array{title: string, template: string, button_text: string}> $steps Wizard steps (passed from SetupWizard::render()).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wcb-admin wcb-wizard-wrap">
	<h1 class="screen-reader-text"><?php esc_html_e( 'WP Career Board Setup', 'wp-career-board' ); ?></h1>

	<div class="wcb-page-header">
		<div class="wcb-page-header__left">
			<h2 class="wcb-page-header__title">
				<i data-lucide="briefcase" class="wcb-icon--lg"></i>
				<?php esc_html_e( 'WP Career Board', 'wp-career-board' ); ?>
			</h2>
			<p class="wcb-page-header__desc"><?php esc_html_e( 'Quick setup — takes about 2 minutes', 'wp-career-board' ); ?></p>
		</div>
	</div>

	<div class="wcb-wizard-steps" id="wcb-wizard-steps">

		<?php
		$wcb_step_num = 0;
		foreach ( $steps as $wcb_step_key => $wcb_step ) :
			++$wcb_step_num;
			?>
			<div class="wcb-wizard-step wcb-settings-card <?php echo 1 === $wcb_step_num ? 'active' : ''; ?>"
				data-step="<?php echo (int) $wcb_step_num; ?>"
				data-key="<?php echo esc_attr( $wcb_step_key ); ?>">
				<div class="wcb-settings-card-header">
					<h2 class="wcb-settings-card-title"><?php echo esc_html( $wcb_step['title'] ); ?></h2>
				</div>
			<?php
			$wcb_real_path = realpath( $wcb_step['template'] );
			if ( $wcb_real_path && str_starts_with( $wcb_real_path, WP_PLUGIN_DIR ) ) {
				include $wcb_step['template'];
			}
			?>
			</div>
			<?php
		endforeach;
		?>

	</div>
</div>
