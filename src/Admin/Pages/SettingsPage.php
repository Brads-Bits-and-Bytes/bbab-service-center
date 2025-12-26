<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Admin\Pages;

use BBAB\ServiceCenter\Utils\Settings;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Plugin Settings Page.
 *
 * Provides a centralized settings interface under Brad's Workbench.
 * Uses the Settings utility class for storage (bbab_sc_settings option).
 *
 * Partially migrated from: WPCode Snippet #2359
 */
class SettingsPage {

    /**
     * Option group name for settings API.
     */
    private const OPTION_GROUP = 'bbab_sc_settings_group';

    /**
     * Option name (matches Settings utility class).
     */
    private const OPTION_NAME = 'bbab_sc_settings';

    /**
     * Register hooks.
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'registerMenu'], 99);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register the settings submenu under Workbench.
     */
    public function registerMenu(): void {
        add_submenu_page(
            'bbab-workbench',
            __('Settings', 'bbab-service-center'),
            __('Settings', 'bbab-service-center'),
            'manage_options',
            'bbab-settings',
            [$this, 'renderPage']
        );
    }

    /**
     * Register settings with WordPress Settings API.
     */
    public function registerSettings(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
            ]
        );
    }

    /**
     * Sanitize settings on save.
     *
     * @param array $input Raw input from form.
     * @return array Sanitized settings.
     */
    public function sanitizeSettings(array $input): array {
        // Get existing settings to preserve values we're not editing
        $existing = get_option(self::OPTION_NAME, []);

        // Sanitize Service Request settings
        if (isset($input['sr_form_id'])) {
            $existing['sr_form_id'] = absint($input['sr_form_id']);
        }

        if (isset($input['sr_notification_email'])) {
            $existing['sr_notification_email'] = sanitize_email($input['sr_notification_email']);
        }

        if (isset($input['sr_notification_subject'])) {
            $existing['sr_notification_subject'] = sanitize_text_field($input['sr_notification_subject']);
        }

        if (isset($input['sr_notification_body'])) {
            // Allow HTML in email body but sanitize it
            $existing['sr_notification_body'] = wp_kses_post($input['sr_notification_body']);
        }

        Logger::debug('SettingsPage', 'Settings saved');

        return $existing;
    }

    /**
     * Render the settings page.
     */
    public function renderPage(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'bbab_sc_messages',
                'bbab_sc_message',
                __('Settings saved.', 'bbab-service-center'),
                'updated'
            );
        }

        $settings = Settings::getAll();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Service Center Settings', 'bbab-service-center'); ?></h1>

            <?php settings_errors('bbab_sc_messages'); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table" role="presentation">

                    <!-- Service Requests Section -->
                    <tr>
                        <th colspan="2" style="padding-bottom: 0;">
                            <h2 style="margin: 0; padding: 10px 0; border-bottom: 1px solid #ccc;">
                                Service Requests
                            </h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_form_id"><?php esc_html_e('WPForms Form ID', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <input type="number"
                                   id="sr_form_id"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[sr_form_id]"
                                   value="<?php echo esc_attr($settings['sr_form_id'] ?? ''); ?>"
                                   class="small-text"
                                   min="0">
                            <p class="description">
                                <?php esc_html_e('The WPForms form ID for Service Request submissions. Find this in WPForms > All Forms.', 'bbab-service-center'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_notification_email"><?php esc_html_e('Notification Email', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   id="sr_notification_email"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[sr_notification_email]"
                                   value="<?php echo esc_attr($settings['sr_notification_email'] ?? ''); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Email address to receive new Service Request notifications.', 'bbab-service-center'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_notification_subject"><?php esc_html_e('Email Subject', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="sr_notification_subject"
                                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[sr_notification_subject]"
                                   value="<?php echo esc_attr($settings['sr_notification_subject'] ?? ''); ?>"
                                   class="large-text">
                            <p class="description">
                                <?php esc_html_e('Placeholders: {ref}, {org_name}, {user_name}, {type}, {subject}', 'bbab-service-center'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sr_notification_body"><?php esc_html_e('Email Body', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $settings['sr_notification_body'] ?? '',
                                'sr_notification_body',
                                [
                                    'textarea_name' => self::OPTION_NAME . '[sr_notification_body]',
                                    'textarea_rows' => 15,
                                    'media_buttons' => false,
                                    'teeny' => false,
                                    'quicktags' => true,
                                ]
                            );
                            ?>
                            <p class="description" style="margin-top: 10px;">
                                <?php esc_html_e('Placeholders you can use:', 'bbab-service-center'); ?><br>
                                <code>{ref}</code> - Reference number (e.g., SR-0042)<br>
                                <code>{org_name}</code> - Client organization name<br>
                                <code>{user_name}</code> - Submitter's display name<br>
                                <code>{user_email}</code> - Submitter's email<br>
                                <code>{type}</code> - Request type (Support, Feature Request, etc.)<br>
                                <code>{subject}</code> - Request subject line<br>
                                <code>{description}</code> - Full request description<br>
                                <code>{admin_link}</code> - Link to edit the SR in admin<br>
                                <code>{attachments_note}</code> - Shows "Attachments: Yes" if files attached
                            </p>
                        </td>
                    </tr>

                    <!-- General Section (Placeholder) -->
                    <tr>
                        <th colspan="2" style="padding-bottom: 0;">
                            <h2 style="margin: 20px 0 0 0; padding: 10px 0; border-bottom: 1px solid #ccc;">
                                General
                                <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
                                    (Available after Phase 7 migration)
                                </span>
                            </h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Admin Menu Name', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   value="Service Center"
                                   class="regular-text"
                                   disabled>
                            <p class="description" style="color: #999;">
                                <?php esc_html_e('This setting is currently managed by snippet #2359. It will be migrated in Phase 7.', 'bbab-service-center'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Time Tracking Section (Placeholder) -->
                    <tr>
                        <th colspan="2" style="padding-bottom: 0;">
                            <h2 style="margin: 20px 0 0 0; padding: 10px 0; border-bottom: 1px solid #ccc;">
                                Time Tracking
                                <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
                                    (Available after Phase 4.6 migration)
                                </span>
                            </h2>
                        </th>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Forgotten Timer Email', 'bbab-service-center'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   value="<?php echo esc_attr(get_option('admin_email')); ?>"
                                   class="regular-text"
                                   disabled>
                            <p class="description" style="color: #999;">
                                <?php esc_html_e('This setting is currently managed by snippet #2359. It will be migrated when the Forgotten Timer cron is built in Phase 4.6.', 'bbab-service-center'); ?>
                            </p>
                        </td>
                    </tr>

                </table>

                <?php submit_button(__('Save Settings', 'bbab-service-center')); ?>
            </form>

            <hr style="margin-top: 40px;">

            <h2><?php esc_html_e('Current Configuration', 'bbab-service-center'); ?></h2>
            <p class="description"><?php esc_html_e('Quick reference for debugging. These values are read from the database.', 'bbab-service-center'); ?></p>

            <table class="widefat" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Setting', 'bbab-service-center'); ?></th>
                        <th><?php esc_html_e('Value', 'bbab-service-center'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>sr_form_id</code></td>
                        <td><?php echo esc_html($settings['sr_form_id'] ?: '(not set)'); ?></td>
                    </tr>
                    <tr>
                        <td><code>sr_notification_email</code></td>
                        <td><?php echo esc_html($settings['sr_notification_email'] ?: '(not set)'); ?></td>
                    </tr>
                    <tr>
                        <td><code>debug_mode</code></td>
                        <td><?php echo $settings['debug_mode'] ? 'true' : 'false'; ?></td>
                    </tr>
                    <tr>
                        <td><code>simulation_enabled</code></td>
                        <td><?php echo $settings['simulation_enabled'] ? 'true' : 'false'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
