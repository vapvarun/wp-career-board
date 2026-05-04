<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Emails settings admin page.
 *
 * Renders brand settings and per-email enable/subject controls.
 * Populated by wcb_registered_emails filter — Pro emails appear automatically.
 *
 * @package WP_Career_Board
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace WCB\Admin;

if (! defined('ABSPATH') ) {
    exit;
}

/**
 * Email Notifications settings page — brand config and per-email subject/enable controls.
 *
 * @since 1.0.0
 */
class EmailSettings
{

    /**
     * Register admin_init save handler + asset enqueue for the Emails tab.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        add_action('admin_init', array( $this, 'save' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_assets' ));
    }

    /**
     * Enqueue the dedicated Emails-tab CSS + JS only on the Career Board settings page
     * with tab=emails. Keeps inline asset blobs out of the PHP render path.
     *
     * @since 1.1.1
     *
     * @param  string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueue_assets( string $hook_suffix ): void
    {
        // Only fire on Career Board → Settings → Emails tab.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab discriminator.
        $current_tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
        if ('emails' !== $current_tab) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ('wcb-settings' !== $current_page) {
            return;
        }

        wp_enqueue_style(
            'wcb-admin-emails',
            WCB_URL . 'assets/css/admin/emails.css',
            array(),
            WCB_VERSION
        );

        wp_enqueue_script(
            'wcb-admin-emails',
            WCB_URL . 'assets/js/admin/emails.js',
            array(),
            WCB_VERSION,
            true
        );

        wp_localize_script(
            'wcb-admin-emails',
            'wcbAdminEmails',
            array(
                'restBase' => esc_url_raw( untrailingslashit( rest_url( 'wcb/v1' ) ) ),
                'nonce'    => wp_create_nonce('wp_rest'),
                'i18n'     => array(
                    'sending' => __('Sending…', 'wp-career-board'),
                    'sent'    => __('Sent', 'wp-career-board'),
                    'failed'  => __('Failed', 'wp-career-board'),
                    'empty'   => __('No emails logged for the current filters.', 'wp-career-board'),
                    'fail'    => __('Failed to load activity log.', 'wp-career-board'),
                    'page'    => __('Page', 'wp-career-board'),
                    'records' => __('records', 'wp-career-board'),
                ),
            )
        );
    }

    /**
     * Render just the email settings form — used when embedded as a Settings tab.
     *
     * @since 1.0.0
     */
    public function render_form(): void
    {
        $settings = wcb_get_email_settings();
        $brand    = isset($settings['brand']) ? (array) $settings['brand'] : array();
        $emails   = (array) apply_filters('wcb_registered_emails', array());
        ?>
        <div class="wcb-settings-card">
            <div class="wcb-settings-card-header">
                <h2 class="wcb-settings-card-title"><?php esc_html_e('Brand Settings', 'wp-career-board'); ?></h2>
            </div>
            <form method="post">
        <?php wp_nonce_field('wcb_email_settings_save', 'wcb_email_nonce'); ?>
                <div class="wcb-settings-row">
                    <div class="wcb-settings-row-label">
                        <label for="wcb-email-header-color"><?php esc_html_e('Header Color', 'wp-career-board'); ?></label>
                    </div>
                    <div class="wcb-settings-row-control">
                        <input type="color" id="wcb-email-header-color" name="wcb_email[brand][header_color]"
                            value="<?php echo esc_attr(isset($brand['header_color']) ? $brand['header_color'] : '#4f46e5'); ?>">
                    </div>
                </div>
                <div class="wcb-settings-row">
                    <div class="wcb-settings-row-label">
                        <label><?php esc_html_e('Logo', 'wp-career-board'); ?></label>
                    </div>
                    <div class="wcb-settings-row-control">
        <?php
         $wcb_logo_id  = (int) ( isset($brand['logo_id']) ? $brand['logo_id'] : 0 );
         $wcb_logo_url = $wcb_logo_id ? wp_get_attachment_image_url($wcb_logo_id, 'medium') : '';
        ?>
                        <input type="hidden" id="wcb-email-logo-id" name="wcb_email[brand][logo_id]" value="<?php echo (int) $wcb_logo_id; ?>">
                        <div id="wcb-logo-preview" style="margin-bottom: 8px;<?php echo $wcb_logo_url ? '' : ' display:none;'; ?>">
                            <img src="<?php echo esc_url((string) $wcb_logo_url); ?>" alt="<?php esc_attr_e('Email logo preview', 'wp-career-board'); ?>" style="max-width: 200px; max-height: 60px; border: 1px solid var(--wcb-border, #e2e8f0); border-radius: 4px; padding: 4px;">
                        </div>
                        <button type="button" class="wcb-btn wcb-btn--sm" id="wcb-logo-upload">
                            <i data-lucide="image" class="wcb-icon--sm"></i>
          <?php echo $wcb_logo_url ? esc_html__('Change Image', 'wp-career-board') : esc_html__('Choose Image', 'wp-career-board'); ?>
                        </button>
         <?php if ($wcb_logo_url ) : ?>
                        <button type="button" class="wcb-btn wcb-btn--sm wcb-btn--danger" id="wcb-logo-remove" style="margin-left: 4px;">
                <?php esc_html_e('Remove', 'wp-career-board'); ?>
                        </button>
         <?php endif; ?>
                        <span class="description"><?php esc_html_e('Displayed in the header of all WCB notification emails.', 'wp-career-board'); ?></span>
                    </div>
                </div>
                <div class="wcb-settings-row">
                    <div class="wcb-settings-row-label">
                        <label for="wcb-email-footer-text"><?php esc_html_e('Footer Text', 'wp-career-board'); ?></label>
                    </div>
                    <div class="wcb-settings-row-control">
                        <textarea id="wcb-email-footer-text" name="wcb_email[brand][footer_text]" rows="2" style="width:400px"><?php echo esc_textarea(isset($brand['footer_text']) ? $brand['footer_text'] : ''); ?></textarea>
                    </div>
                </div>
        </div>

        <div class="wcb-settings-card">
            <div class="wcb-settings-card-header">
                <h2 class="wcb-settings-card-title"><?php esc_html_e('Email Templates', 'wp-career-board'); ?></h2>
            </div>
            <div style="padding: 0 24px 16px;">
                <table class="widefat striped" style="margin-top:12px">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Email', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Recipient', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Subject', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Enabled', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Test', 'wp-career-board'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
        <?php foreach ( $emails as $email ) : ?>
            <?php
            if (! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
                continue; 
            }
            ?>
            <?php
            $id      = $email->get_id();
            $saved   = isset($settings[ $id ]) ? (array) $settings[ $id ] : array();
            $enabled = isset($saved['enabled']) ? (bool) $saved['enabled'] : true;
            $subject = isset($saved['subject']) ? $saved['subject'] : '';
            ?>
                        <tr>
                            <td><strong><?php echo esc_html($email->get_title()); ?></strong></td>
                            <td><?php echo esc_html(ucfirst($email->get_recipient())); ?></td>
                            <td>
                                <input type="text" aria-label="<?php esc_attr_e('Email subject', 'wp-career-board'); ?>" name="wcb_email[<?php echo esc_attr($id); ?>][subject]" value="<?php echo esc_attr($subject); ?>" placeholder="<?php echo esc_attr($email->get_default_subject()); ?>" style="width:100%;max-width:400px;">
                            </td>
                            <td>
                                <input type="checkbox" aria-label="<?php esc_attr_e('Enable this email notification', 'wp-career-board'); ?>" name="wcb_email[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($enabled); ?>>
                            </td>
                            <td>
                                <button type="button" class="wcb-btn wcb-btn--sm wcb-btn--ghost wcb-email-test-btn" data-email-id="<?php echo esc_attr($id); ?>" aria-label="<?php
                                /* translators: %s: email title */
                                echo esc_attr(sprintf(__('Send test of %s to me', 'wp-career-board'), $email->get_title())); ?>">
                                    <i data-lucide="send" class="wcb-icon--xs" aria-hidden="true"></i>
                                    <span><?php esc_html_e('Send test', 'wp-career-board'); ?></span>
                                </button>
                            </td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top: 12px;">
        <?php
        /* translators: %s: current admin email address */
        echo esc_html(sprintf(__('Test sends are dispatched to your admin email (%s) and recorded in the activity log below.', 'wp-career-board'), wp_get_current_user()->user_email));
        ?>
                </p>
            </div>
        </div>

        <div class="wcb-settings-footer">
        <?php submit_button(__('Save Email Settings', 'wp-career-board')); ?>
        </div>
        </form>

        <?php $this->render_activity_log(); ?>
        <?php
        wp_enqueue_media();
        ?>
        <script>
        (function(){
            var btn = document.getElementById('wcb-logo-upload');
            var rmv = document.getElementById('wcb-logo-remove');
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var frame = wp.media({ title: '<?php echo esc_js(__('Select Logo', 'wp-career-board')); ?>', multiple: false, library: { type: 'image' } });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    document.getElementById('wcb-email-logo-id').value = att.id;
                    var preview = document.getElementById('wcb-logo-preview');
                    preview.style.display = '';
                    preview.querySelector('img').src = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    btn.textContent = '<?php echo esc_js(__('Change Image', 'wp-career-board')); ?>';
                    if (!rmv) {
                        rmv = document.createElement('button');
                        rmv.type = 'button';
                        rmv.className = 'wcb-btn wcb-btn--sm wcb-btn--danger';
                        rmv.id = 'wcb-logo-remove';
                        rmv.style.marginLeft = '4px';
                        rmv.textContent = '<?php echo esc_js(__('Remove', 'wp-career-board')); ?>';
                        btn.parentNode.insertBefore(rmv, btn.nextSibling);
                        rmv.addEventListener('click', removeLogo);
                    }
                });
                frame.open();
            });
            function removeLogo(e) {
                e.preventDefault();
                document.getElementById('wcb-email-logo-id').value = '0';
                document.getElementById('wcb-logo-preview').style.display = 'none';
                btn.textContent = '<?php echo esc_js(__('Choose Image', 'wp-career-board')); ?>';
                if (rmv) { rmv.remove(); rmv = null; }
            }
            if (rmv) rmv.addEventListener('click', removeLogo);
        })();
        </script>
        <?php
    }

    /**
     * Render the activity log section beneath the settings form.
     *
     * Lists recent rows from wp_wcb_notifications_log via the REST API, filterable
     * by event type and status. Surfaces "did the email actually send?" without
     * requiring DB access.
     *
     * @since 1.1.1
     */
    public function render_activity_log(): void
    {
        $emails = (array) apply_filters('wcb_registered_emails', array());
        ?>
        <div class="wcb-settings-card wcb-email-log-card" id="wcb-email-activity-log">
            <div class="wcb-settings-card-header">
                <h2 class="wcb-settings-card-title"><?php esc_html_e('Email Activity Log', 'wp-career-board'); ?></h2>
                <p class="wcb-settings-card-desc">
        <?php esc_html_e('Recent transactional emails dispatched by Career Board. Helps verify whether emails are firing under live conditions and which recipients received them.', 'wp-career-board'); ?>
                </p>
            </div>
            <div class="wcb-email-log-body">
                <div class="wcb-email-log-toolbar">
                    <label class="wcb-email-log-filter">
                        <span class="wcb-email-log-filter__label"><?php esc_html_e('Template', 'wp-career-board'); ?></span>
                        <select id="wcb-log-filter-event" class="wcb-email-log-filter__select">
                            <option value=""><?php esc_html_e('All templates', 'wp-career-board'); ?></option>
        <?php foreach ( $emails as $email ) : ?>
            <?php
            if (! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
                continue;
            }
            ?>
                                <option value="<?php echo esc_attr($email->get_id()); ?>"><?php echo esc_html($email->get_title()); ?></option>
        <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="wcb-email-log-filter">
                        <span class="wcb-email-log-filter__label"><?php esc_html_e('Status', 'wp-career-board'); ?></span>
                        <select id="wcb-log-filter-status" class="wcb-email-log-filter__select">
                            <option value=""><?php esc_html_e('All statuses', 'wp-career-board'); ?></option>
                            <option value="sent"><?php esc_html_e('Sent', 'wp-career-board'); ?></option>
                            <option value="failed"><?php esc_html_e('Failed', 'wp-career-board'); ?></option>
                        </select>
                    </label>
                    <button type="button" class="wcb-btn wcb-btn--sm wcb-btn--ghost" id="wcb-log-refresh">
                        <i data-lucide="refresh-cw" class="wcb-icon--xs" aria-hidden="true"></i>
                        <span><?php esc_html_e('Refresh', 'wp-career-board'); ?></span>
                    </button>
                </div>
                <div class="wcb-email-log-table-wrap">
                    <table class="wcb-email-log-table" id="wcb-email-log-table">
                        <thead>
                            <tr>
                                <th class="wcb-email-log-col-when"><?php esc_html_e('When', 'wp-career-board'); ?></th>
                                <th class="wcb-email-log-col-template"><?php esc_html_e('Template', 'wp-career-board'); ?></th>
                                <th class="wcb-email-log-col-recipient"><?php esc_html_e('Recipient', 'wp-career-board'); ?></th>
                                <th class="wcb-email-log-col-subject"><?php esc_html_e('Subject', 'wp-career-board'); ?></th>
                                <th class="wcb-email-log-col-status"><?php esc_html_e('Status', 'wp-career-board'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="wcb-email-log-empty"><?php esc_html_e('Loading…', 'wp-career-board'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="wcb-email-log-pagination">
                    <button type="button" class="wcb-btn wcb-btn--sm wcb-btn--ghost" id="wcb-log-prev" disabled>
                        <i data-lucide="chevron-left" class="wcb-icon--xs" aria-hidden="true"></i>
                        <span><?php esc_html_e('Previous', 'wp-career-board'); ?></span>
                    </button>
                    <span class="wcb-email-log-pageinfo" id="wcb-log-pageinfo">—</span>
                    <button type="button" class="wcb-btn wcb-btn--sm wcb-btn--ghost" id="wcb-log-next" disabled>
                        <span><?php esc_html_e('Next', 'wp-career-board'); ?></span>
                        <i data-lucide="chevron-right" class="wcb-icon--xs" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php
        // Note: CSS + JS for this section live in assets/css/admin/emails.css and
        // assets/js/admin/emails.js — enqueued from EmailSettings::enqueue_assets().
    }

    /**
     * Render the Emails settings page.
     *
     * @since 1.0.0
     */
    public function render(): void
    {
        $settings = wcb_get_email_settings();
        $brand    = isset($settings['brand']) ? (array) $settings['brand'] : array();
        $emails   = (array) apply_filters('wcb_registered_emails', array());
        ?>
        <div class="wrap wcb-admin">
            <h1><?php esc_html_e('Email Notifications', 'wp-career-board'); ?></h1>
            <form method="post">
        <?php wp_nonce_field('wcb_email_settings_save', 'wcb_email_nonce'); ?>

                <h2><?php esc_html_e('Brand Settings', 'wp-career-board'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Header Color', 'wp-career-board'); ?></th>
                        <td>
                            <input type="color" aria-label="<?php esc_attr_e('Header color', 'wp-career-board'); ?>" name="wcb_email[brand][header_color]" value="<?php echo esc_attr(isset($brand['header_color']) ? $brand['header_color'] : '#4f46e5'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Logo', 'wp-career-board'); ?></th>
                        <td>
                            <input type="number" aria-label="<?php esc_attr_e('Logo attachment ID', 'wp-career-board'); ?>" name="wcb_email[brand][logo_id]" value="<?php echo (int) ( isset($brand['logo_id']) ? $brand['logo_id'] : 0 ); ?>" placeholder="<?php esc_attr_e('Attachment ID', 'wp-career-board'); ?>">
                            <p class="description"><?php esc_html_e('Enter the attachment ID of your logo image.', 'wp-career-board'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Footer Text', 'wp-career-board'); ?></th>
                        <td>
                            <textarea name="wcb_email[brand][footer_text]" aria-label="<?php esc_attr_e('Email footer text', 'wp-career-board'); ?>" rows="2" style="width:400px"><?php echo esc_textarea(isset($brand['footer_text']) ? $brand['footer_text'] : ''); ?></textarea>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Email Templates', 'wp-career-board'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Email', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Recipient', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Subject', 'wp-career-board'); ?></th>
                            <th><?php esc_html_e('Enabled', 'wp-career-board'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
        <?php
        foreach ( $emails as $email ) :
            if (! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
                continue; 
            }
            $id      = $email->get_id();
            $saved   = isset($settings[ $id ]) ? (array) $settings[ $id ] : array();
            $enabled = isset($saved['enabled']) ? (bool) $saved['enabled'] : true;
            $subject = isset($saved['subject']) ? $saved['subject'] : '';
            ?>
                        <tr>
                            <td><strong><?php echo esc_html($email->get_title()); ?></strong></td>
                            <td><?php echo esc_html(ucfirst($email->get_recipient())); ?></td>
                            <td>
                                <input type="text" aria-label="<?php esc_attr_e('Email subject', 'wp-career-board'); ?>" name="wcb_email[<?php echo esc_attr($id); ?>][subject]" value="<?php echo esc_attr($subject); ?>" placeholder="<?php echo esc_attr($email->get_default_subject()); ?>" style="width:100%;max-width:400px;">
                            </td>
                            <td>
                                <input type="checkbox" aria-label="<?php esc_attr_e('Enable this email notification', 'wp-career-board'); ?>" name="wcb_email[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($enabled); ?>>
                            </td>
                        </tr>
        <?php endforeach; ?>
                    </tbody>
                </table>

        <?php submit_button(__('Save Email Settings', 'wp-career-board')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Save posted email settings.
     *
     * @since 1.0.0
     */
    public function save(): void
    {
        if (! isset($_POST['wcb_email_nonce']) ) {
            return;
        }
        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wcb_email_nonce'])), 'wcb_email_settings_save') ) {
            return;
        }
        if (! current_user_can('wcb_manage_settings') ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown
            return;
        }

        $raw      = isset($_POST['wcb_email']) ? (array) wp_unslash($_POST['wcb_email']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings = array();

        // Brand settings.
        if (isset($raw['brand']) ) {
            $brand             = (array) $raw['brand'];
            $sanitized_color   = sanitize_hex_color(isset($brand['header_color']) ? $brand['header_color'] : '#4f46e5');
            $settings['brand'] = array(
            'header_color' => $sanitized_color ? $sanitized_color : '#4f46e5',
            'logo_id'      => absint(isset($brand['logo_id']) ? $brand['logo_id'] : 0),
            'footer_text'  => wp_kses_post(isset($brand['footer_text']) ? $brand['footer_text'] : ''),
            );
        }

        // Per-email settings — only save keys that match registered emails.
        $emails = (array) apply_filters('wcb_registered_emails', array());
        foreach ( $emails as $email ) {
            if (! $email instanceof \WCB\Modules\Notifications\AbstractEmail ) {
                continue;
            }
            $id              = $email->get_id();
            $settings[ $id ] = array(
            'enabled' => ! empty($raw[ $id ]['enabled']),
            'subject' => sanitize_text_field(isset($raw[ $id ]['subject']) ? $raw[ $id ]['subject'] : ''),
            );
        }

        // F-5 — write through wcb_settings so sanitization stays in one
        // path. Settings API sanitize fires too because we still pass through
        // its filter on read; the legacy row is left alone here and dropped
        // by the install migration so existing reads stay correct until that
        // upgrade pass runs.
        $aggregate           = (array) get_option('wcb_settings', array());
        $aggregate['emails'] = $settings;
        update_option('wcb_settings', $aggregate);
        add_action(
            'admin_notices',
            static function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Email settings saved.', 'wp-career-board') . '</p></div>';
            }
        );
    }
}
