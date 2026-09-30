<?php
/**
 * Wizard step partial: jobs (moderation, listing length, currency).
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_auto_publish = \WCB\Admin\Settings::bool( 'auto_publish_jobs', false );
$wcb_expire_days  = \WCB\Admin\Settings::int( 'jobs_expire_days', 30 );
$wcb_currency     = \WCB\Admin\Settings::string( 'salary_currency', 'USD' );
?>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-autopublish"><?php esc_html_e( 'Publish jobs without review', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<label class="wcb-toggle">
			<input type="checkbox" id="wcb-wz-autopublish" name="auto_publish_jobs" <?php checked( $wcb_auto_publish ); ?>>
			<span class="wcb-toggle-slider"></span>
		</label>
		<span class="description"><?php esc_html_e( 'Off: every new job waits for your approval under Career Board > Jobs before candidates see it.', 'wp-career-board' ); ?></span>
	</div>
</div>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-expire"><?php esc_html_e( 'Default listing length (days)', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<input type="number" id="wcb-wz-expire" name="jobs_expire_days" value="<?php echo (int) $wcb_expire_days; ?>" min="1" class="small-text">
		<span class="description"><?php esc_html_e( 'How long a job stays open when the employer sets no deadline.', 'wp-career-board' ); ?></span>
	</div>
</div>
<div class="wcb-settings-row">
	<div class="wcb-settings-row-label"><label for="wcb-wz-currency"><?php esc_html_e( 'Salary currency', 'wp-career-board' ); ?></label></div>
	<div class="wcb-settings-row-control">
		<select id="wcb-wz-currency" name="salary_currency">
			<?php foreach ( \WCB\Admin\AdminSettings::get_currency_catalog() as $wcb_code => $wcb_meta ) : ?>
				<option value="<?php echo esc_attr( $wcb_code ); ?>" <?php selected( $wcb_currency, $wcb_code ); ?>>
					<?php
					printf(
						/* translators: 1: code (USD), 2: name (US Dollar), 3: symbol ($). */
						esc_html__( '%1$s  -  %2$s (%3$s)', 'wp-career-board' ),
						esc_html( $wcb_code ),
						esc_html( $wcb_meta['name'] ),
						esc_html( $wcb_meta['symbol'] )
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="description"><?php esc_html_e( 'Pre-selected on the job form. Employers can still pick another.', 'wp-career-board' ); ?></span>
	</div>
</div>
<?php
require __DIR__ . '/_footer.php';
