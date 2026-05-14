<?php
/**
 * Shared renderer + initial-state helper for filter-driven custom fields
 * across Career Board's form blocks.
 *
 * Forms in this plugin expose a filter family for site owners / Pro Field
 * Builder to inject custom fields:
 *   - wcb_job_form_fields          (post-a-job)
 *   - wcb_application_form_fields_groups (apply form)
 *   - wcb_company_form_fields      (company profile)
 *   - wcb_candidate_form_fields    (candidate profile)
 *   - wcb_resume_form_fields       (resume builder family)
 *
 * Each filter returns an array of groups in the shape:
 *
 *   array(
 *     array(
 *       'id'     => 'partner',
 *       'label'  => 'Partner association',
 *       'fields' => array(
 *         array(
 *           'key'         => '_wcb_partner_id',
 *           'label'       => 'Partner',
 *           'type'        => 'text|textarea|select|email|tel|url|number|date',
 *           'required'    => true|false,
 *           'placeholder' => 'optional',
 *           'description' => 'optional hint',
 *           'options'     => array( 'val' => 'label' ),
 *         ),
 *       ),
 *     ),
 *   )
 *
 * This helper:
 *   - Validates the group shape.
 *   - Outputs Interactivity-API-bound inputs that two-way bind into
 *     state.customFields via the supplied `updateCustomField` action.
 *   - Provides load_values() to seed initial state from existing post meta.
 *
 * @package WP_Career_Board
 * @since   1.1.1
 */

declare( strict_types=1 );

namespace WCB\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helper used by every form block that opens a custom-field filter.
 *
 * @since 1.1.1
 */
final class FormCustomFields {

	/**
	 * Output Interactivity-API-bound markup for an array of field groups.
	 *
	 * Mirrors the rendering loop in blocks/job-single/render.php (the apply
	 * form), which is the canonical reference implementation since 1.0.0.
	 *
	 * @since 1.1.1
	 *
	 * @param array<int, array<string, mixed>> $groups    Field groups from a filter.
	 * @param string                           $update_fn JS action name to bind on input
	 *                                                    (default: updateCustomField).
	 * @param string                           $id_prefix DOM id prefix to keep ids stable
	 *                                                    when multiple forms render on
	 *                                                    one page (default: wcb-custom).
	 * @param int                              $owner_id  Post / user id to pre-fill from.
	 *                                                    Only the `radio` field type needs
	 *                                                    this — it has no single element to
	 *                                                    two-way bind, so the saved option's
	 *                                                    `checked` is set server-side. Other
	 *                                                    types pre-fill via the form's seeded
	 *                                                    state.customFields. 0 = create mode.
	 * @param string                           $reader    'post_meta' | 'user_meta' for the
	 *                                                    radio pre-fill lookup.
	 * @return void
	 */
	public static function render_groups( array $groups, string $update_fn = 'updateCustomField', string $id_prefix = 'wcb-custom', int $owner_id = 0, string $reader = 'post_meta' ): void {
		if ( empty( $groups ) ) {
			return;
		}

		// radio is the only type that needs a server-side pre-fill (see the
		// $owner_id doc above). Skip the meta read entirely when no group
		// actually contains a radio field.
		$values    = array();
		$has_radio = false;
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			foreach ( $group['fields'] as $field ) {
				if ( is_array( $field ) && 'radio' === (string) ( $field['type'] ?? $field['field_type'] ?? '' ) ) {
					$has_radio = true;
					break 2;
				}
			}
		}
		if ( $has_radio && $owner_id > 0 ) {
			$values = self::load_values( $groups, $owner_id, $reader );
		}

		echo '<div class="wcb-form-custom-fields">';
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			if ( ! empty( $group['label'] ) ) {
				echo '<h3 class="wcb-form-custom-fields__heading">' . esc_html( (string) $group['label'] ) . '</h3>';
			}

			foreach ( $group['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$normalised = self::normalise_field( $field );
				if ( '' === $normalised['key'] || '' === $normalised['type'] ) {
					continue;
				}
				self::render_field( $normalised, $update_fn, $id_prefix, $values[ $normalised['key'] ] ?? '' );
			}
		}
		echo '</div>';
	}

	/**
	 * Coerce a field definition to the canonical {key,type,label,...}
	 * shape regardless of source. Accepts both the apply-form's
	 * documented contract (`key`/`type`) and Pro Field Builder's
	 * DB-row shape (`field_key`/`field_type`) so the renderer is
	 * defensive against any future hook.
	 *
	 * @since 1.1.1
	 *
	 * @param array<string, mixed> $field Raw field row.
	 * @return array{key:string,type:string,label:string,required:bool,placeholder:string,description:string,options:array<string,string>}
	 */
	private static function normalise_field( array $field ): array {
		$key  = (string) ( $field['key'] ?? $field['field_key'] ?? '' );
		$type = (string) ( $field['type'] ?? $field['field_type'] ?? '' );

		// Options can come as JSON string (DB row) or array (filter contract).
		$raw_options = $field['options'] ?? array();
		if ( is_string( $raw_options ) && '' !== trim( $raw_options ) ) {
			$decoded     = json_decode( $raw_options, true );
			$raw_options = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw_options ) ) {
			$raw_options = array();
		}

		return array(
			'key'         => sanitize_key( $key ),
			'type'        => $type,
			'label'       => (string) ( $field['label'] ?? '' ),
			'required'    => ! empty( $field['required'] ),
			'placeholder' => (string) ( $field['placeholder'] ?? '' ),
			'description' => (string) ( $field['description'] ?? '' ),
			'options'     => array_map( 'strval', $raw_options ),
		);
	}

	/**
	 * Render a single field definition with type-aware controls.
	 *
	 * @since 1.1.1
	 *
	 * @param array<string, mixed> $field         Field definition.
	 * @param string               $update_fn     JS action name.
	 * @param string               $id_prefix     DOM id prefix.
	 * @param string               $current_value Saved value, used only by the
	 *                                            `radio` type to set the
	 *                                            matching option's `checked`
	 *                                            server-side. Empty in create
	 *                                            mode and for every other type.
	 * @return void
	 */
	private static function render_field( array $field, string $update_fn, string $id_prefix, string $current_value = '' ): void {
		$key    = (string) $field['key'];
		$type   = (string) $field['type'];
		$dom_id = $id_prefix . '-' . $key;

		$required_attr    = ! empty( $field['required'] ) ? ' required aria-required="true"' : '';
		$placeholder_attr = '' !== (string) $field['placeholder']
			? ' placeholder="' . esc_attr( (string) $field['placeholder'] ) . '"'
			: '';

		echo '<div class="wcb-form-field wcb-form-field--custom wcb-form-field--' . esc_attr( $type ) . '">';

		// Checkbox renders its label inline (after the box) — a lone label
		// above an empty checkbox reads as broken. Radio renders a group
		// label as a <span> (a <label for> can only target one input, not a
		// radio group). Every other type keeps the standard <label for>.
		if ( ! empty( $field['label'] ) && 'checkbox' !== $type ) {
			if ( 'radio' === $type ) {
				echo '<span class="wcb-form-label">';
			} else {
				echo '<label class="wcb-form-label" for="' . esc_attr( $dom_id ) . '">';
			}
			echo esc_html( (string) $field['label'] );
			if ( ! empty( $field['required'] ) ) {
				echo ' <span class="wcb-required" aria-hidden="true">*</span>';
			}
			echo 'radio' === $type ? '</span>' : '</label>';
		}

		$value_bind = 'data-wp-bind--value="state.customFields.' . $key . '"';

		/*
		 * EscapeOutput is disabled for the field-render branches below. Every
		 * interpolated value is provably safe: $dom_id / $update_fn / $key go
		 * through esc_attr (and $key is already sanitize_key'd in
		 * normalise_field); $value_bind embeds that same sanitized $key;
		 * $required_attr and $placeholder_attr are literal-ternary strings
		 * ($placeholder_attr's only variable is wrapped in esc_attr at
		 * assignment). The sniff can't trace those across the concatenation,
		 * and a single-line phpcs:ignore can't cover these multi-line echoes.
		 */
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( 'checkbox' === $type ) {
			// Boolean field. data-wp-bind--checked drives the box from
			// state.customFields[ key ]; save_values() persists '1' / '' so
			// the bound value stays JS-falsy when off (the string '0' is
			// truthy in JS, '' is not). Reuses the .wcb-checkbox-label
			// pattern the job forms already use — styled in the shared
			// frontend-components.css so it renders consistently wherever
			// this renderer is embedded (jobs, company, resume, application).
			echo '<label class="wcb-checkbox-label" for="' . esc_attr( $dom_id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $dom_id ) . '" class="wcb-field" value="1"'
				. ' data-wp-on--change="actions.' . esc_attr( $update_fn ) . '"'
				. ' data-wcb-field="' . esc_attr( $key ) . '"'
				. ' data-wp-bind--checked="state.customFields.' . esc_attr( $key ) . '"'
				. $required_attr . ' />';
			echo '<span>' . esc_html( (string) $field['label'] );
			if ( ! empty( $field['required'] ) ) {
				echo ' <span class="wcb-required" aria-hidden="true">*</span>';
			}
			echo '</span></label>';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea id="' . esc_attr( $dom_id ) . '" class="wcb-field" rows="4"'
				. ' data-wp-on--input="actions.' . esc_attr( $update_fn ) . '"'
				. ' data-wcb-field="' . esc_attr( $key ) . '"'
				. ' ' . $value_bind
				. $placeholder_attr . $required_attr . '></textarea>';
		} elseif ( 'select' === $type && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			echo '<select id="' . esc_attr( $dom_id ) . '" class="wcb-field"'
				. ' data-wp-on--change="actions.' . esc_attr( $update_fn ) . '"'
				. ' data-wcb-field="' . esc_attr( $key ) . '"'
				. ' ' . $value_bind
				. $required_attr . '>';
			echo '<option value="">' . esc_html__( 'Select…', 'wp-career-board' ) . '</option>';
			foreach ( $field['options'] as $val => $label ) {
				echo '<option value="' . esc_attr( (string) $val ) . '">' . esc_html( (string) $label ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'radio' === $type && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			// A radio group has no single element to two-way bind, so it
			// doesn't use data-wp-bind. The browser keeps the group mutually
			// exclusive (shared `name`), data-wp-on--change pushes the picked
			// value into state.customFields via updateCustomField (radio hits
			// the same target.value path as text/select), and the saved
			// option's `checked` is set server-side from $current_value.
			echo '<div class="wcb-radio-group" role="radiogroup">';
			$radio_index = 0;
			foreach ( $field['options'] as $val => $label ) {
				$radio_id      = $dom_id . '-' . $radio_index;
				$radio_checked = ( (string) $val === $current_value ) ? ' checked' : '';
				echo '<label class="wcb-radio-label" for="' . esc_attr( $radio_id ) . '">';
				echo '<input type="radio" id="' . esc_attr( $radio_id ) . '" class="wcb-field"'
					. ' name="' . esc_attr( $dom_id ) . '"'
					. ' value="' . esc_attr( (string) $val ) . '"'
					. ' data-wp-on--change="actions.' . esc_attr( $update_fn ) . '"'
					. ' data-wcb-field="' . esc_attr( $key ) . '"'
					. $radio_checked . $required_attr . ' />';
				echo '<span>' . esc_html( (string) $label ) . '</span>';
				echo '</label>';
				++$radio_index;
			}
			echo '</div>';
		} else {
			$allowed    = array( 'text', 'email', 'tel', 'url', 'number', 'date' );
			$input_type = in_array( $type, $allowed, true ) ? $type : 'text';
			echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $dom_id ) . '" class="wcb-field"'
				. ' data-wp-on--input="actions.' . esc_attr( $update_fn ) . '"'
				. ' data-wcb-field="' . esc_attr( $key ) . '"'
				. ' ' . $value_bind
				. $placeholder_attr . $required_attr . ' />';
		}
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $field['description'] ) ) {
			echo '<span class="wcb-field-hint">' . esc_html( (string) $field['description'] ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Persist submitted custom-field values to the owning post / user meta.
	 *
	 * The accepted shape is what every form's view.js sends in its submit
	 * body: an associative array keyed by sanitised field_key, with scalar
	 * values. Boolean checkbox values are coerced to '0' / '1'.
	 *
	 * Each value is also pushed through the `wcb_save_custom_field` filter
	 * so add-ons can validate / transform / reject specific keys without
	 * having to fork the renderer.
	 *
	 * @since 1.1.1
	 *
	 * @param array<int, array<string, mixed>> $groups   Field-group definitions.
	 * @param int                              $owner_id Post / user / row id to save into.
	 * @param array<string, mixed>             $values   Submitted values (untrusted).
	 * @param string                           $writer   'post_meta' | 'user_meta'.
	 * @return int Number of values persisted.
	 */
	public static function save_values( array $groups, int $owner_id, array $values, string $writer = 'post_meta' ): int {
		if ( $owner_id <= 0 || empty( $groups ) ) {
			return 0;
		}

		$allowed_keys = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['fields'] ) ) {
				continue;
			}
			foreach ( $group['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$normalised = self::normalise_field( $field );
				if ( '' !== $normalised['key'] ) {
					$allowed_keys[ $normalised['key'] ] = $normalised['type'];
				}
			}
		}

		$count = 0;
		foreach ( $values as $key => $raw_value ) {
			$key = sanitize_key( (string) $key );
			if ( ! isset( $allowed_keys[ $key ] ) ) {
				continue;
			}

			$type      = $allowed_keys[ $key ];
			$sanitised = self::sanitise_value( $type, $raw_value );

			/**
			 * Filter a single custom-field value before it's written to meta.
			 *
			 * Return null to skip persistence (e.g. add-on validation rejecting
			 * the value); return any scalar to override.
			 *
			 * @since 1.1.1
			 *
			 * @param string|null $sanitised Sanitised value, or null to skip.
			 * @param string      $key       Field key.
			 * @param int         $owner_id  Owning entity id.
			 */
			$sanitised = apply_filters( 'wcb_save_custom_field', $sanitised, $key, $owner_id );
			if ( null === $sanitised ) {
				continue;
			}

			if ( 'user_meta' === $writer ) {
				update_user_meta( $owner_id, $key, $sanitised );
			} else {
				update_post_meta( $owner_id, $key, $sanitised );
			}
			++$count;
		}

		return $count;
	}

	/**
	 * Sanitise a raw value based on the declared field type.
	 *
	 * @since 1.1.1
	 *
	 * @param string $type      Field type.
	 * @param mixed  $raw_value Raw value from request.
	 * @return string
	 */
	private static function sanitise_value( string $type, mixed $raw_value ): string {
		// Checkbox first — it accepts a bool (JS sends target.checked) or any
		// truthy string shape, and must store '' (not '0') when off so the
		// data-wp-bind--checked binding stays falsy in JS, where '0' is truthy.
		if ( 'checkbox' === $type ) {
			$is_on = is_bool( $raw_value )
				? $raw_value
				: in_array( strtolower( (string) $raw_value ), array( '1', 'on', 'true', 'yes' ), true );
			return $is_on ? '1' : '';
		}

		if ( is_bool( $raw_value ) ) {
			return $raw_value ? '1' : '0';
		}
		$str = is_scalar( $raw_value ) ? (string) $raw_value : '';

		return match ( $type ) {
			'textarea' => sanitize_textarea_field( $str ),
			'email'    => sanitize_email( $str ),
			'url'      => esc_url_raw( $str ),
			'number'   => is_numeric( $str ) ? (string) (float) $str : '',
			'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $str ) ? $str : '',
			default    => sanitize_text_field( $str ),
		};
	}

	/**
	 * Load saved values for the configured fields from the owning post's meta.
	 *
	 * Used by render.php files to seed the customFields entry in the
	 * Interactivity API initial-state. Reads each `key` from the group
	 * definitions and looks up the post meta with the same key.
	 *
	 * @since 1.1.1
	 *
	 * @param array<int, array<string, mixed>> $groups   Same shape as render_groups().
	 * @param int                              $owner_id Post / user / row id whose meta to read.
	 * @param string                           $reader   'post_meta' | 'user_meta'. Default post_meta.
	 * @return array<string, string> key => string-cast value.
	 */
	public static function load_values( array $groups, int $owner_id, string $reader = 'post_meta' ): array {
		$values = array();
		if ( $owner_id <= 0 ) {
			return $values;
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || empty( $group['fields'] ) ) {
				continue;
			}
			foreach ( $group['fields'] as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$normalised = self::normalise_field( $field );
				$key        = $normalised['key'];
				if ( '' === $key ) {
					continue;
				}

				$value = 'user_meta' === $reader
					? get_user_meta( $owner_id, $key, true )
					: get_post_meta( $owner_id, $key, true );

				$values[ $key ] = is_scalar( $value ) ? (string) $value : '';
			}
		}

		return $values;
	}
}
