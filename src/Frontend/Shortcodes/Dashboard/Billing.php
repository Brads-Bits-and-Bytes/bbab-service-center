<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Billing shortcode.
 *
 * Displays billing information including invoices and payment status.
 *
 * Shortcode: [dashboard_billing]
 */
class Billing extends BaseShortcode {

    protected string $tag = 'dashboard_billing';

    /**
     * Render the billing output.
     */
    protected function output(array $atts, int $org_id): string {
        // TODO: Migrate actual functionality from WPCode snippet
        return '<div class="bbab-dashboard-billing">
            <div class="bbab-placeholder">
                <p><strong>Billing</strong></p>
                <p>Organization ID: ' . $org_id . '</p>
                <p><em>Placeholder - functionality will be migrated from WPCode snippet.</em></p>
            </div>
        </div>';
    }
}
