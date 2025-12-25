<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Overview shortcode.
 *
 * Displays the main dashboard overview panel with organization info,
 * quick stats, and navigation to other dashboard sections.
 *
 * Shortcode: [dashboard_overview]
 */
class Overview extends BaseShortcode {

    protected string $tag = 'dashboard_overview';

    /**
     * Render the overview output.
     */
    protected function output(array $atts, int $org_id): string {
        $org = $this->getOrg();
        $org_name = $org ? esc_html($org->post_title) : 'Unknown Organization';

        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-overview">
            <div class="bbab-placeholder">
                <p><strong>Dashboard Overview</strong></p>
                <p>Organization: ' . $org_name . ' (ID: ' . $org_id . ')</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
