<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Active Projects shortcode.
 *
 * Displays a list of active projects for the organization.
 *
 * Shortcode: [dashboard_active_projects]
 */
class ActiveProjects extends BaseShortcode {

    protected string $tag = 'dashboard_active_projects';

    /**
     * Render the active projects output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-active-projects">
            <div class="bbab-placeholder">
                <p><strong>Active Projects</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
