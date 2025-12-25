<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Action Items shortcode.
 *
 * Displays pending action items/tasks for the organization.
 *
 * Shortcode: [dashboard_action_items]
 */
class ActionItems extends BaseShortcode {

    protected string $tag = 'dashboard_action_items';

    /**
     * Render the action items output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-action-items">
            <div class="bbab-placeholder">
                <p><strong>Action Items</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
