<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Roadmap shortcode.
 *
 * Displays feature requests and roadmap items for the organization.
 *
 * Shortcode: [dashboard_roadmap]
 */
class Roadmap extends BaseShortcode {

    protected string $tag = 'dashboard_roadmap';

    /**
     * Render the roadmap output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-roadmap">
            <div class="bbab-placeholder">
                <p><strong>Roadmap</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
