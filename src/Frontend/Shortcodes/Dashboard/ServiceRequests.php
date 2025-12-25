<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Service Requests shortcode.
 *
 * Displays a list of service requests for the organization.
 *
 * Shortcode: [dashboard_service_requests]
 */
class ServiceRequests extends BaseShortcode {

    protected string $tag = 'dashboard_service_requests';

    /**
     * Render the service requests output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-service-requests">
            <div class="bbab-placeholder">
                <p><strong>Service Requests</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
