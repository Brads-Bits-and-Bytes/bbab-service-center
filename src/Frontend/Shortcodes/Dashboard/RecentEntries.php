<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Recent Entries shortcode.
 *
 * Displays a list of recent time entries for the organization.
 *
 * Shortcode: [dashboard_recent_entries]
 */
class RecentEntries extends BaseShortcode {

    protected string $tag = 'dashboard_recent_entries';

    /**
     * Render the recent entries output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-recent-entries">
            <div class="bbab-placeholder">
                <p><strong>Recent Time Entries</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
