<?php
/**
 * Email header template — branded wrapper top.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_email_s      = (array) get_option( 'wcb_email_settings', array() );
$wcb_header_color = ! empty( $wcb_email_s['brand']['header_color'] ) ? $wcb_email_s['brand']['header_color'] : '#4f46e5';
$wcb_logo_id      = ! empty( $wcb_email_s['brand']['logo_id'] ) ? (int) $wcb_email_s['brand']['logo_id'] : 0;
$wcb_logo_url     = $wcb_logo_id ? (string) wp_get_attachment_image_url( $wcb_logo_id, 'medium' ) : '';
$wcb_site_name    = (string) get_bloginfo( 'name' );
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $wcb_site_name ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 16px;">
	<tr><td align="center">
	<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
		<!-- Header bar -->
		<tr>
		<td style="background:<?php echo esc_attr( $wcb_header_color ); ?>;padding:24px 32px;text-align:center;">
			<?php if ( $wcb_logo_url ) : ?>
			<img src="<?php echo esc_url( $wcb_logo_url ); ?>" alt="<?php echo esc_attr( $wcb_site_name ); ?>" style="max-height:48px;display:inline-block;">
			<?php else : ?>
			<span style="color:#ffffff;font-size:20px;font-weight:700;"><?php echo esc_html( $wcb_site_name ); ?></span>
			<?php endif; ?>
		</td>
		</tr>
		<!-- Body -->
		<tr>
		<td style="padding:32px;">
			<div style="font-size:15px;line-height:1.6;color:#374151;">
