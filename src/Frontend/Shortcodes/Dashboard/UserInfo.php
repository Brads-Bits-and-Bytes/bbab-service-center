<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;
use BBAB\ServiceCenter\Utils\UserContext;

/**
 * User Info / Welcome Header shortcode.
 *
 * Displays a welcome greeting with user's name, organization name, and org logo.
 * Supports customizable greeting format via the 'format' attribute.
 *
 * Shortcode: [dashboard_welcome_header]
 * Attributes:
 *   - format: (optional) Greeting format with %s placeholder for name. Default: "Welcome, %s!"
 *
 * Migrated from: WPCode Snippet #1746
 */
class UserInfo extends BaseShortcode {

    protected string $tag = 'dashboard_welcome_header';

    /**
     * Render the welcome header output.
     */
    protected function output(array $atts, int $org_id): string {
        $atts = $this->parseAtts($atts, [
            'format' => 'Welcome, %s!',
        ]);

        $user = wp_get_current_user();
        $first_name = $user->first_name ?: $user->display_name;

        // Get organization info
        $org = $this->getOrg();
        $org_name = '';
        $logo_html = '';

        if ($org) {
            // Try organization_name field first, fall back to post_title
            $org_name = get_post_meta($org->ID, 'organization_name', true);
            if (empty($org_name)) {
                $org_name = $org->post_title;
            }

            // Get org logo
            $logo_id = get_post_meta($org->ID, 'org_logo', true);
            if (!empty($logo_id)) {
                if (is_numeric($logo_id)) {
                    $logo_url = wp_get_attachment_image_url((int) $logo_id, 'medium');
                } else {
                    // Might be a direct URL
                    $logo_url = $logo_id;
                }

                if ($logo_url) {
                    $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($org_name) . '" style="max-height: 60px; width: auto; object-fit: contain;">';
                }
            }
        }

        // Format the greeting (use sprintf to replace %s with the name)
        $greeting = sprintf(esc_html($atts['format']), esc_html($first_name));

        ob_start();
        ?>
        <div class="bbab-welcome-header" style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        ">
            <div>
                <h1 style="font-family: Poppins, sans-serif; font-size: 36px; font-weight: 600; color: #1C244B; margin: 0;">
                    <?php echo $greeting; ?>
                </h1>
                <?php if (!empty($org_name)): ?>
                    <p style="font-family: Poppins, sans-serif; font-size: 16px; color: #324A6D; margin: 5px 0 0 0;">
                        <?php echo esc_html($org_name); ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($logo_html)): ?>
                <div><?php echo $logo_html; ?></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
