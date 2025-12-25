<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Month Progress shortcode.
 *
 * Displays the current month's progress including hours used,
 * free hours remaining, and billing status.
 *
 * Shortcode: [dashboard_month_progress]
 */
class MonthProgress extends BaseShortcode {

    protected string $tag = 'dashboard_month_progress';

    /**
     * Render the month progress output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-month-progress">
            <div class="bbab-placeholder">
                <p><strong>Month Progress</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
