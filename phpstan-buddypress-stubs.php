<?php
/**
 * PHPStan stubs for the BuddyPress API the plugins call.
 *
 * BuddyPress is optional: every call is guarded at runtime (function_exists /
 * bp_is_active). These declarations let static analysis check those calls
 * instead of ignoring every bp_* name. Only what WP Career Board and Pro use.
 * Not shipped (see .distignore).
 *
 * @package WP_Career_Board
 */

// Dev tooling, not shipped: runs from the command line or inside WordPress, never over the web.
if ( ! defined( 'ABSPATH' ) && 'cli' !== PHP_SAPI ) {
	exit;
}

// phpcs:ignoreFile -- analysis stubs, never loaded by WordPress.

/**
 * BuddyPress group.
 */
class BP_Groups_Group {}

/**
 * BuddyPress notification.
 */
class BP_Notifications_Notification {}

/**
 * BuddyPress member query.
 */
class BP_User_Query {}

/** @return mixed */
function buddypress() {}
/** @param array<string, mixed> $args @return int|false */
function bp_activity_add( $args = array() ) {}
/** @return string */
function bp_core_current_time( $gmt = true, $type = 'mysql' ) {}
/** @return string */
function bp_core_get_userlink( $user_id, $no_anchor = false, $just_link = false ) {}
/** @return void */
function bp_core_load_template( $templates ) {}
/** @param array<string, mixed> $args @return bool */
function bp_core_new_nav_item( $args, $component = 'members' ) {}
/** @param array<string, mixed> $args @return bool */
function bp_core_new_subnav_item( $args, $component = null ) {}
/** @return bool */
function bp_current_user_can( $capability, $args = array() ) {}
/** @return string */
function bp_displayed_user_domain() {}
/** @return int */
function bp_displayed_user_id() {}
/** @return string */
function bp_get_current_group_slug() {}
/** @return string */
function bp_get_group_permalink( $group = false, $path = '' ) {}
/** @return string|string[]|false */
function bp_get_member_type( $user_id, $single = true, $use_db = false ) {}
/** @return bool */
function bp_is_active( $component = '', $feature = '' ) {}
/** @return bool */
function bp_is_group() {}
/** @return bool */
function bp_is_my_profile() {}
/** @return bool */
function bp_is_user() {}
/** @return string */
function bp_loggedin_user_domain() {}
/** @param array<string, mixed> $args @return int|false */
function bp_notifications_add_notification( $args = array() ) {}
/** @param array<string, mixed> $args @return object|\WP_Error */
function bp_register_member_type( $member_type, $args = array() ) {}
/** @return false|string[] */
function bp_set_member_type( $user_id, $member_type, $append = false ) {}
/** @return bool */
function groups_delete_groupmeta( $group_id, $meta_key = false, $meta_value = false, $delete_all = false ) {}
/** @return BP_Groups_Group|false */
function groups_get_current_group() {}
/** @return BP_Groups_Group */
function groups_get_group( $group_id ) {}
/** @return mixed */
function groups_get_groupmeta( $group_id = 0, $meta_key = '', $single = true ) {}
/** @return int|bool */
function groups_is_user_admin( $user_id, $group_id ) {}
/** @return int|bool */
function groups_is_user_member( $user_id, $group_id ) {}
/** @return int|bool */
function groups_is_user_mod( $user_id, $group_id ) {}
/** @return int|bool */
function groups_update_groupmeta( $group_id, $meta_key, $meta_value, $prev_value = '' ) {}
