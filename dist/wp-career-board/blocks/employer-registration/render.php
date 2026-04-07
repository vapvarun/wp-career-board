<?php
/**
 * Block render: wcb/employer-registration — unified sign-up form with role picker.
 *
 * Step 1: Choose role (Candidate / Employer)
 * Step 2: Fill in details (company name shown only for employers)
 * Step 3: Success — redirect to appropriate dashboard
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Already logged in — show a contextual message instead of the form.
if ( is_user_logged_in() ) {
	$wcb_settings      = (array) get_option( 'wcb_settings', array() );
	$wcb_user          = wp_get_current_user();
	$wcb_is_employer   = in_array( 'wcb_employer', (array) $wcb_user->roles, true );
	$wcb_dash_page_key = $wcb_is_employer ? 'employer_dashboard_page' : 'candidate_dashboard_page';
	$wcb_dashboard_url = ! empty( $wcb_settings[ $wcb_dash_page_key ] )
		? (string) get_permalink( (int) $wcb_settings[ $wcb_dash_page_key ] )
		: '';
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-employer-reg wcb-employer-reg--logged-in' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="wcb-reg-notice">
			<?php esc_html_e( 'You are already logged in.', 'wp-career-board' ); ?>
			<?php if ( $wcb_dashboard_url ) : ?>
				<a href="<?php echo esc_url( $wcb_dashboard_url ); ?>" class="wcb-reg-link">
					<?php esc_html_e( 'Go to your dashboard →', 'wp-career-board' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>
	<?php
	return;
}

$wcb_login_url = wp_login_url( get_permalink() ?? '' );

wp_interactivity_state(
	'wcb-employer-registration',
	array(
		'apiBase'      => rest_url( 'wcb/v1' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'role'         => '',
		'firstName'    => '',
		'lastName'     => '',
		'email'        => '',
		'companyName'    => '',
		'companyWebsite' => '',
		'companyIndustry' => '',
		'companySize'    => '',
		'companyHq'      => '',
		'password'       => '',
		'submitting'     => false,
		'submitted'    => false,
		'error'        => '',
		'dashboardUrl' => '',
		'strings'      => array(
			'errorConnection' => __( 'Connection error. Please check your network and try again.', 'wp-career-board' ),
		),
	)
);
?>
<div
	<?php echo get_block_wrapper_attributes( array( 'class' => 'wcb-employer-reg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="wcb-employer-registration"
>
	<?php /* ── Success state ── */ ?>
	<div class="wcb-reg-success wcb-hidden" data-wp-class--wcb-hidden="!state.submitted">
		<h2 class="wcb-reg-success-title"><?php esc_html_e( 'Account created!', 'wp-career-board' ); ?></h2>
		<p data-wp-class--wcb-hidden="state.isCandidate">
			<?php esc_html_e( 'You are now logged in as an employer. Set up your company profile to start posting jobs.', 'wp-career-board' ); ?>
		</p>
		<p data-wp-class--wcb-hidden="!state.isCandidate">
			<?php esc_html_e( 'You are now logged in as a candidate. Start browsing jobs and building your resume.', 'wp-career-board' ); ?>
		</p>
		<a class="wcb-btn wcb-btn--primary" data-wp-bind--href="state.dashboardUrl">
			<?php esc_html_e( 'Go to Dashboard →', 'wp-career-board' ); ?>
		</a>
	</div>

	<?php /* ── Step 1: Role picker ── */ ?>
	<div class="wcb-reg-form-wrap" data-wp-class--wcb-hidden="state.submitted">
		<div class="wcb-role-picker" data-wp-class--wcb-hidden="state.role">
			<h2 class="wcb-reg-title"><?php esc_html_e( 'Join WP Career Board', 'wp-career-board' ); ?></h2>
			<p class="wcb-reg-subtitle">
				<?php esc_html_e( 'Already have an account?', 'wp-career-board' ); ?>
				<a href="<?php echo esc_url( $wcb_login_url ); ?>" class="wcb-reg-link"><?php esc_html_e( 'Sign in', 'wp-career-board' ); ?></a>
			</p>
			<p class="wcb-role-prompt"><?php esc_html_e( 'I want to...', 'wp-career-board' ); ?></p>
			<div class="wcb-role-options">
				<button type="button" class="wcb-role-card" data-wp-on--click="actions.selectCandidate" aria-label="<?php esc_attr_e( 'Find a Job', 'wp-career-board' ); ?>">
					<span class="wcb-role-icon" aria-hidden="true"><i data-lucide="briefcase" aria-hidden="true"></i></span>
					<span class="wcb-role-label"><?php esc_html_e( 'Find a Job', 'wp-career-board' ); ?></span>
					<span class="wcb-role-desc"><?php esc_html_e( 'Browse jobs, apply, and build your resume', 'wp-career-board' ); ?></span>
				</button>
				<button type="button" class="wcb-role-card" data-wp-on--click="actions.selectEmployer" aria-label="<?php esc_attr_e( 'Hire Talent', 'wp-career-board' ); ?>">
					<span class="wcb-role-icon" aria-hidden="true"><i data-lucide="building-2" aria-hidden="true"></i></span>
					<span class="wcb-role-label"><?php esc_html_e( 'Hire Talent', 'wp-career-board' ); ?></span>
					<span class="wcb-role-desc"><?php esc_html_e( 'Post jobs, manage applications, and find candidates', 'wp-career-board' ); ?></span>
				</button>
			</div>
		</div>

		<?php /* ── Step 2: Registration form ── */ ?>
		<div class="wcb-hidden" data-wp-class--wcb-hidden="!state.role">
			<div class="wcb-reg-header-row">
				<button type="button" class="wcb-reg-back" data-wp-on--click="actions.backToRolePicker" aria-label="<?php esc_attr_e( 'Back', 'wp-career-board' ); ?>">
					&#8592;
				</button>
				<div>
					<h2 class="wcb-reg-title" data-wp-text="state.roleTitle"></h2>
					<p class="wcb-reg-subtitle">
						<?php esc_html_e( 'Already have an account?', 'wp-career-board' ); ?>
						<a href="<?php echo esc_url( $wcb_login_url ); ?>" class="wcb-reg-link"><?php esc_html_e( 'Sign in', 'wp-career-board' ); ?></a>
					</p>
				</div>
			</div>

			<form class="wcb-reg-form" data-wp-on--submit="actions.submit">
				<?php /* Honeypot */ ?>
				<input type="text" name="wcb_hp_reg" id="wcb-hp-reg" style="display:none!important" tabindex="-1" autocomplete="off" />

				<div class="wcb-reg-row">
					<div class="wcb-field-group">
						<label class="wcb-field-label" for="wcb-reg-first-name"><?php esc_html_e( 'First Name', 'wp-career-board' ); ?></label>
						<input
							id="wcb-reg-first-name"
							type="text"
							class="wcb-field-input"
							autocomplete="given-name"
							required
							aria-required="true"
							data-wp-bind--value="state.firstName"
							data-wp-on--input="actions.updateFirstName"
						/>
					</div>
					<div class="wcb-field-group">
						<label class="wcb-field-label" for="wcb-reg-last-name"><?php esc_html_e( 'Last Name', 'wp-career-board' ); ?></label>
						<input
							id="wcb-reg-last-name"
							type="text"
							class="wcb-field-input"
							autocomplete="family-name"
							required
							aria-required="true"
							data-wp-bind--value="state.lastName"
							data-wp-on--input="actions.updateLastName"
						/>
					</div>
				</div>

				<div class="wcb-field-group">
					<label class="wcb-field-label" for="wcb-reg-email" data-wp-text="state.emailLabel"></label>
					<input
						id="wcb-reg-email"
						type="email"
						class="wcb-field-input"
						autocomplete="email"
						required
						aria-required="true"
						data-wp-bind--value="state.email"
						data-wp-on--input="actions.updateEmail"
					/>
				</div>

				<div class="wcb-field-group" data-wp-class--wcb-hidden="state.isCandidate">
					<label class="wcb-field-label" for="wcb-reg-company"><?php esc_html_e( 'Company Name', 'wp-career-board' ); ?></label>
					<input
						id="wcb-reg-company"
						type="text"
						class="wcb-field-input"
						autocomplete="organization"
						data-wp-bind--required="!state.isCandidate"
						data-wp-bind--value="state.companyName"
						data-wp-on--input="actions.updateCompanyName"
					/>
				</div>
				<div class="wcb-field-group" data-wp-class--wcb-hidden="state.isCandidate">
					<label class="wcb-field-label" for="wcb-reg-website"><?php esc_html_e( 'Company Website', 'wp-career-board' ); ?></label>
					<input id="wcb-reg-website" type="url" class="wcb-field-input" placeholder="https://"
						data-wp-bind--value="state.companyWebsite" data-wp-on--input="actions.updateField" data-wcb-field="companyWebsite" />
				</div>
				<div class="wcb-field-row" data-wp-class--wcb-hidden="state.isCandidate">
					<div class="wcb-field-group wcb-field-half">
						<label class="wcb-field-label" for="wcb-reg-industry"><?php esc_html_e( 'Industry', 'wp-career-board' ); ?></label>
						<input id="wcb-reg-industry" type="text" class="wcb-field-input" placeholder="<?php esc_attr_e( 'e.g. Technology', 'wp-career-board' ); ?>"
							data-wp-bind--value="state.companyIndustry" data-wp-on--input="actions.updateField" data-wcb-field="companyIndustry" />
					</div>
					<div class="wcb-field-group wcb-field-half">
						<label class="wcb-field-label" for="wcb-reg-size"><?php esc_html_e( 'Company Size', 'wp-career-board' ); ?></label>
						<select id="wcb-reg-size" class="wcb-field-input"
							data-wp-bind--value="state.companySize" data-wp-on--change="actions.updateField" data-wcb-field="companySize">
							<option value=""><?php esc_html_e( 'Select…', 'wp-career-board' ); ?></option>
							<option value="1-10">1-10</option>
							<option value="11-50">11-50</option>
							<option value="51-200">51-200</option>
							<option value="201-500">201-500</option>
							<option value="501-1000">501-1000</option>
							<option value="1001+">1001+</option>
						</select>
					</div>
				</div>
				<div class="wcb-field-group" data-wp-class--wcb-hidden="state.isCandidate">
					<label class="wcb-field-label" for="wcb-reg-hq"><?php esc_html_e( 'Headquarters', 'wp-career-board' ); ?></label>
					<input id="wcb-reg-hq" type="text" class="wcb-field-input" placeholder="<?php esc_attr_e( 'e.g. San Francisco, CA', 'wp-career-board' ); ?>"
						data-wp-bind--value="state.companyHq" data-wp-on--input="actions.updateField" data-wcb-field="companyHq" />
				</div>

				<div class="wcb-field-group">
					<label class="wcb-field-label" for="wcb-reg-password"><?php esc_html_e( 'Password', 'wp-career-board' ); ?></label>
					<input
						id="wcb-reg-password"
						type="password"
						class="wcb-field-input"
						autocomplete="new-password"
						required
						aria-required="true"
						data-wp-bind--value="state.password"
						data-wp-on--input="actions.updatePassword"
					/>
					<span class="wcb-form-hint"><?php esc_html_e( 'Minimum 8 characters', 'wp-career-board' ); ?></span>
				</div>

				<p class="wcb-reg-error" role="alert" data-wp-class--wcb-hidden="!state.error" data-wp-text="state.error"></p>

				<button
					type="submit"
					class="wcb-btn wcb-btn--primary wcb-btn--full"
					data-wp-bind--disabled="state.submitting"
				>
					<span data-wp-class--wcb-hidden="state.submitting"><?php esc_html_e( 'Create Account', 'wp-career-board' ); ?></span>
					<span data-wp-class--wcb-hidden="!state.submitting"><?php esc_html_e( 'Creating account…', 'wp-career-board' ); ?></span>
				</button>

				<p class="wcb-reg-terms">
					<?php
					printf(
						/* translators: 1: privacy policy link open, 2: link close */
						esc_html__( 'By creating an account you agree to our %1$sPrivacy Policy%2$s.', 'wp-career-board' ),
						'<a href="' . esc_url( (string) get_privacy_policy_url() ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					);
					?>
				</p>
			</form>
		</div>
	</div>
</div>
