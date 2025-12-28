<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Overview shortcode.
 *
 * Displays the main dashboard welcome panel with organization info
 * and quick stats at a glance.
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
        $org_name = $org ? $org->post_title : 'Your Organization';

        // Get quick stats
        $stats = $this->getQuickStats($org_id);

        // Get user info
        $current_user = wp_get_current_user();
        $first_name = $current_user->first_name ?: $current_user->display_name;

        // Get org logo
        $logo_html = '';
        if ($org) {
            $logo_id = get_post_meta($org->ID, 'org_logo', true);
            if (!empty($logo_id)) {
                if (is_numeric($logo_id)) {
                    $logo_url = wp_get_attachment_image_url((int) $logo_id, 'medium');
                } else {
                    $logo_url = $logo_id;
                }

                if ($logo_url) {
                    $logo_html = '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($org_name) . '" class="bbab-org-logo">';
                }
            }
        }

        ob_start();
        ?>
        <div class="bbab-overview-section">
            <div class="bbab-overview-header">
                <div class="bbab-overview-welcome">
                    <h2>Welcome back, <?php echo esc_html($first_name); ?>!</h2>
                    <p class="bbab-org-name"><?php echo esc_html($org_name); ?></p>
                </div>
                <?php if (!empty($logo_html)): ?>
                    <div class="bbab-overview-logo">
                        <?php echo $logo_html; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bbab-stats-grid">
                <div class="bbab-stat-card">
                    <span class="bbab-stat-icon">&#128187;</span>
                    <div class="bbab-stat-content">
                        <span class="bbab-stat-number"><?php echo esc_html($stats['active_projects']); ?></span>
                        <span class="bbab-stat-label">Active Projects</span>
                    </div>
                </div>

                <div class="bbab-stat-card">
                    <span class="bbab-stat-icon">&#128172;</span>
                    <div class="bbab-stat-content">
                        <span class="bbab-stat-number"><?php echo esc_html($stats['open_requests']); ?></span>
                        <span class="bbab-stat-label">Open Requests</span>
                    </div>
                </div>

                <div class="bbab-stat-card">
                    <span class="bbab-stat-icon">&#9745;</span>
                    <div class="bbab-stat-content">
                        <span class="bbab-stat-number"><?php echo esc_html($stats['pending_tasks']); ?></span>
                        <span class="bbab-stat-label">Pending Tasks</span>
                    </div>
                </div>

                <div class="bbab-stat-card">
                    <span class="bbab-stat-icon">&#128176;</span>
                    <div class="bbab-stat-content">
                        <span class="bbab-stat-number"><?php echo esc_html($stats['unpaid_invoices']); ?></span>
                        <span class="bbab-stat-label">Unpaid Invoices</span>
                    </div>
                </div>
            </div>

            <div class="bbab-quick-actions">
                <h4>Quick Actions</h4>
                <div class="bbab-action-links">
                    <a href="/support-request-form/" class="bbab-action-btn primary">
                        <span>+</span> Submit Support Request
                    </a>
                    <a href="/feature-request/" class="bbab-action-btn secondary">
                        <span>&#128161;</span> Suggest a Feature
                    </a>
                    <a href="mailto:brad@bradsbitsandbytes.com" class="bbab-action-btn secondary">
                        <span>&#9993;</span> Email Brad
                    </a>
                </div>
            </div>
        </div>

        <style>
            .bbab-overview-section {
                background: #F3F5F8;
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .bbab-overview-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 24px;
                gap: 20px;
            }

            .bbab-overview-welcome {
                flex: 1;
            }

            .bbab-overview-welcome h2 {
                font-family: 'Poppins', sans-serif;
                font-size: 28px;
                font-weight: 600;
                color: #1C244B;
                margin: 0 0 4px 0;
            }

            .bbab-org-name {
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                color: #324A6D;
                margin: 0;
            }

            .bbab-overview-logo {
                flex-shrink: 0;
            }

            .bbab-org-logo {
                max-height: 60px;
                width: auto;
                object-fit: contain;
            }

            @media (max-width: 600px) {
                .bbab-overview-header {
                    flex-direction: column-reverse;
                    align-items: flex-start;
                }

                .bbab-overview-logo {
                    align-self: flex-end;
                    margin-bottom: 10px;
                }
            }

            .bbab-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            @media (max-width: 992px) {
                .bbab-stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }

            @media (max-width: 480px) {
                .bbab-stats-grid {
                    grid-template-columns: 1fr;
                }
            }

            .bbab-stat-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .bbab-stat-icon {
                font-size: 32px;
                line-height: 1;
            }

            .bbab-stat-content {
                display: flex;
                flex-direction: column;
            }

            .bbab-stat-number {
                font-family: 'Poppins', sans-serif;
                font-size: 28px;
                font-weight: 700;
                color: #1C244B;
                line-height: 1;
            }

            .bbab-stat-label {
                font-family: 'Poppins', sans-serif;
                font-size: 13px;
                color: #324A6D;
                margin-top: 4px;
            }

            .bbab-quick-actions h4 {
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                font-weight: 600;
                color: #1C244B;
                margin: 0 0 12px 0;
            }

            .bbab-action-links {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .bbab-action-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                border-radius: 6px;
                text-decoration: none;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s;
            }

            .bbab-action-btn.primary {
                background: #467FF7;
                color: white !important;
            }

            .bbab-action-btn.primary:hover {
                background: #3366cc;
                color: white !important;
            }

            .bbab-action-btn.secondary {
                background: white;
                color: #324A6D !important;
                border: 1px solid #e0e0e0;
            }

            .bbab-action-btn.secondary:hover {
                border-color: #467FF7;
                color: #467FF7 !important;
            }

            .bbab-action-btn span {
                font-size: 16px;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get quick stats for the organization.
     */
    private function getQuickStats(int $org_id): array {
        // Active projects count
        $active_projects = get_posts([
            'post_type' => 'project',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'organization',
                    'value' => $org_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'project_status',
                    'value' => ['Active', 'Waiting on Client'],
                    'compare' => 'IN',
                ],
            ],
            'fields' => 'ids',
        ]);

        // Open service requests count
        $open_requests = get_posts([
            'post_type' => 'service_request',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'organization',
                    'value' => $org_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'request_status',
                    'value' => ['New', 'Acknowledged', 'In Progress', 'Waiting on Client'],
                    'compare' => 'IN',
                ],
            ],
            'fields' => 'ids',
        ]);

        // Pending tasks count
        global $wpdb;
        $user_id = get_current_user_id();
        $pending_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->prefix}podsrel r ON p.ID = r.item_id
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'task_status' AND pm.meta_value = 'Pending'
             LEFT JOIN {$wpdb->postmeta} au ON p.ID = au.post_id AND au.meta_key = 'assigned_user'
             WHERE p.post_type = 'client_task'
             AND p.post_status = 'publish'
             AND r.related_item_id = %d
             AND (au.meta_value IS NULL OR au.meta_value = '' OR au.meta_value = %d)",
            $org_id,
            $user_id
        ));

        // Unpaid invoices count
        $unpaid_invoices = get_posts([
            'post_type' => 'invoice',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'organization',
                    'value' => $org_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'invoice_status',
                    'value' => ['Pending', 'Partial'],
                    'compare' => 'IN',
                ],
            ],
            'fields' => 'ids',
        ]);

        return [
            'active_projects' => count($active_projects),
            'open_requests' => count($open_requests),
            'pending_tasks' => (int) $pending_tasks,
            'unpaid_invoices' => count($unpaid_invoices),
        ];
    }
}
