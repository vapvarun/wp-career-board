<?php
/**
 * Current-password check on every core route that changes one's own login.
 *
 * The dashboards change a member's email or password through POST
 * /wcb/v1/account, which asks for the current password so a hijacked session
 * cannot swap the email and then reset the password. WordPress's own
 * /wp/v2/users/me and the wp-admin profile form change both without asking,
 * so the same rule is applied to them here: one rule, three entry points.
 *
 * The REST check runs on `rest_request_before_callbacks`, not
 * `rest_pre_insert_user`: core does not look at an error returned from that
 * filter when updating a user, it casts it to an array and answers 200 having
 * changed nothing.
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Modules\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires the current password to change one's own email or password.
 */
final class CredentialGuard {

	/**
	 * A profile save refused at the start of the request, shown when core reports errors.
	 *
	 * @var \WP_Error|null
	 */
	private ?\WP_Error $refused = null;

	/**
	 * Hook in before core's user routes run and around the profile form.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'require_current_password' ), 10, 3 );
		add_action( 'personal_options', array( $this, 'render_profile_field' ) );
		// Before core's own personal_options_update handler (priority 10), which queues an
		// email-change request and mails a confirmation link before any error is checked.
		add_action( 'personal_options_update', array( $this, 'screen_profile_save' ), 1 );
		add_action( 'user_profile_update_errors', array( $this, 'report_refusal' ) );
	}

	/**
	 * Refuse a self-service email or password change without the current password.
	 *
	 * Changing someone else's account (core's capability check gates that) and
	 * administrators are left to core.
	 *
	 * @param mixed            $response Result so far (a \WP_Error stops the request).
	 * @param array            $handler  Matched route handler.
	 * @param \WP_REST_Request $request  Request being dispatched.
	 * @return mixed
	 */
	public function require_current_password( $response, $handler, \WP_REST_Request $request ) {
		unset( $handler );

		if ( is_wp_error( $response ) || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $response;
		}
		// WordPress matches routes case-insensitively, so /wp/v2/Users/me reaches the same handler.
		if ( ! preg_match( '#^/wp/v2/users/(me|\d+)$#i', $request->get_route(), $target ) ) {
			return $response;
		}

		$user_id = 'me' === strtolower( $target[1] ) ? get_current_user_id() : (int) $target[1];
		$error   = $this->password_error( $user_id, $request->get_param( 'email' ), $request->get_param( 'password' ), (string) $request->get_param( 'current_password' ) );

		return $error ?? $response;
	}

	/**
	 * Add the "Current password" row to a member's own wp-admin profile.
	 *
	 * @param \WP_User $user Profile being edited.
	 * @return void
	 */
	public function render_profile_field( \WP_User $user ): void {
		if ( ! $this->guarded( (int) $user->ID ) ) {
			return;
		}
		?>
		<tr class="wcb-current-password">
			<th scope="row"><label for="wcb_current_password"><?php esc_html_e( 'Current password', 'wp-career-board' ); ?></label></th>
			<td>
				<input type="password" name="wcb_current_password" id="wcb_current_password" class="regular-text" autocomplete="current-password" />
				<p class="description"><?php esc_html_e( 'Needed to change your email or password.', 'wp-career-board' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Screen a member's own profile save before core acts on it.
	 *
	 * If the email or password would change without the current password, the
	 * submitted values are put back to what they are now, so nothing is queued
	 * or mailed, and the refusal is shown by report_refusal().
	 *
	 * @param int $user_id Account being saved (core passes the profile owner).
	 * @return void
	 */
	public function screen_profile_save( $user_id ): void {
		// Core verified the profile form's nonce before firing this action; the password is compared as typed.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';
		$password = isset( $_POST['pass1'] ) ? wp_unslash( (string) $_POST['pass1'] ) : '';
		$current  = isset( $_POST['wcb_current_password'] ) ? wp_unslash( (string) $_POST['wcb_current_password'] ) : '';
		// phpcs:enable

		$error = $this->password_error( (int) $user_id, $email, $password, $current );
		if ( ! $error ) {
			return;
		}

		$this->refused = $error;
		$user          = get_userdata( (int) $user_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above; these only restore the account's current values.
		$_POST['email'] = $user instanceof \WP_User ? $user->user_email : '';
		unset( $_POST['pass1'], $_POST['pass2'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Show the refusal from screen_profile_save() so core stops and displays it.
	 *
	 * @param \WP_Error $errors Errors core will show; adding one stops the save.
	 * @return void
	 */
	public function report_refusal( \WP_Error $errors ): void {
		if ( $this->refused ) {
			$errors->add( $this->refused->get_error_code(), $this->refused->get_error_message() );
			$this->refused = null;
		}
	}

	/**
	 * Whether this is a member editing their own login, who must prove themselves.
	 *
	 * Administrators, and anyone editing another account, are left to core.
	 *
	 * @param int $user_id Account being edited.
	 * @return bool
	 */
	private function guarded( int $user_id ): bool {
		return $user_id > 0 && get_current_user_id() === $user_id && ! wp_is_ability_granted( 'wcb/manage-settings' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- polyfilled in core/abilities-api-polyfill.php.
	}

	/**
	 * The one rule: an email or password change needs the correct current password.
	 *
	 * @param int    $user_id  Account being edited.
	 * @param mixed  $email    New email, or empty.
	 * @param mixed  $password New password, or empty.
	 * @param string $current  Current password as typed.
	 * @return \WP_Error|null Error to refuse with, or null to allow.
	 */
	private function password_error( int $user_id, $email, $password, string $current ): ?\WP_Error {
		if ( ! $this->guarded( $user_id ) ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		$email_changed    = is_string( $email ) && '' !== $email && strtolower( $email ) !== strtolower( $user->user_email );
		$password_changed = is_string( $password ) && '' !== $password;
		if ( ! $email_changed && ! $password_changed ) {
			return null;
		}

		if ( '' === $current || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			return new \WP_Error(
				'wcb_bad_current_password',
				__( 'Enter your current password to change your email or password.', 'wp-career-board' ),
				array( 'status' => 403 )
			);
		}

		return null;
	}
}
