<?php
/**
 * Image-only uploads (company logos, profile photos).
 *
 * @package WP_Career_Board
 * @since   1.8.0
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One gate for every upload that must be a picture. Core checks the real file
 * contents against this list, so a PDF renamed to .png is refused too.
 *
 * @since 1.8.0
 */
final class ImageUpload {

	/**
	 * Accepted image types.
	 *
	 * @since 1.8.0
	 * @var array<string,string>
	 */
	private const MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',
	);

	/**
	 * Store the uploaded image in `$_FILES[ $field ]` as an attachment.
	 *
	 * @since 1.8.0
	 * @param string $field     Form field name.
	 * @param int    $parent_id Post to attach to (0 for none).
	 * @return int|\WP_Error Attachment ID, or a 400 error for anything that is not an image.
	 */
	public static function handle( string $field, int $parent_id = 0 ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REST nonce is checked upstream; tmp path and name are only inspected here.
		$file  = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : array();
		$check = wp_check_filetype_and_ext( (string) ( $file['tmp_name'] ?? '' ), (string) ( $file['name'] ?? '' ), self::MIMES );
		if ( empty( $check['type'] ) ) {
			return new \WP_Error(
				'wcb_invalid_image',
				__( 'Please upload a JPEG, PNG, WebP or GIF image.', 'wp-career-board' ),
				array( 'status' => 400 )
			);
		}

		$id = media_handle_upload(
			$field,
			$parent_id,
			array(),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);
		return is_wp_error( $id ) ? $id : (int) $id;
	}
}
