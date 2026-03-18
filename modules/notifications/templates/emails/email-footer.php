<?php
/**
 * Email footer template — branded wrapper bottom.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$wcb_email_s     = (array) get_option( 'wcb_email_settings', array() );
$wcb_footer_text = ! empty( $wcb_email_s['brand']['footer_text'] )
	? $wcb_email_s['brand']['footer_text']
	/* translators: %s: site name */
	: sprintf( __( 'You are receiving this email because you have an account on %s.', 'wp-career-board' ), get_bloginfo( 'name' ) );
?>
			</div>
		</td>
		</tr>
		<!-- Footer -->
		<tr>
		<td style="background:#f9fafb;padding:20px 32px;border-top:1px solid #e5e7eb;text-align:center;">
			<p style="margin:0;font-size:12px;color:#6b7280;"><?php echo wp_kses_post( $wcb_footer_text ); ?></p>
			<p style="margin:8px 0 0;font-size:12px;color:#9ca3af;">
			<a href="<?php echo esc_url( home_url() ); ?>" style="color:#6b7280;"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
			</p>
		</td>
		</tr>
	</table>
	</td></tr>
</table>
</body>
</html>
