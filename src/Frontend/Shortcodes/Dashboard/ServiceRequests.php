<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Service Requests shortcode.
 *
 * Shows recent service requests with status badges and hours.
 *
 * Shortcode: [dashboard_service_requests]
 * Migrated from: WPCode Snippet #1805
 */
class ServiceRequests extends BaseShortcode {

    protected string $tag = 'dashboard_service_requests';

    /**
     * Render the service requests output.
     */
    protected function output(array $atts, int $org_id): string {
        // Get recent service requests for this org
        $requests = pods('service_request', [
            'where' => "organization.ID = {$org_id}",
            'orderby' => 'post_date DESC',
            'limit' => 5,
        ]);

        ob_start();
        ?>
        <div class="service-requests-section">
            <div class="section-header">
                <h3>Service Requests</h3>
                <a href="/support-request-form/" class="new-request-btn">+ New Request</a>
            </div>

            <?php if ($requests->total() > 0): ?>
                <div class="requests-list">
                    <?php while ($requests->fetch()): ?>
                        <?php
                        $sr_id = $requests->id();
                        $ref = $requests->field('reference_number');
                        $subject = $requests->field('subject');
                        $status = $requests->field('request_status');

                        // Handle submitted_date - might be array or string
                        $submitted_raw = $requests->field('submitted_date');
                        if (is_array($submitted_raw)) {
                            $submitted_raw = reset($submitted_raw); // Get first element
                        }
                        $date = !empty($submitted_raw) ? date('M j, Y', strtotime($submitted_raw)) : date('M j, Y', strtotime($requests->field('post_date')));

                        $hours = $this->getSrTotalHours($sr_id);
                        $status_class = strtolower(str_replace(' ', '-', $status));
                        $sr_url = get_permalink($sr_id);
                        ?>
                        <a href="<?php echo esc_url($sr_url); ?>" class="request-card">
                            <div class="request-header">
                                <span class="request-ref"><?php echo esc_html($ref); ?></span>
                                <span class="request-subject"><?php echo esc_html($subject); ?></span>
                            </div>
                            <div class="request-meta">
                                <span class="request-date">Submitted <?php echo esc_html($date); ?></span>
                                <span class="request-status status-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status); ?></span>
                                <?php if ($hours > 0): ?>
                                    <span class="request-hours"><?php echo esc_html(number_format($hours, 2)); ?> hrs</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
                <a href="/service-requests/" class="view-all-link">View All Requests &rarr;</a>
            <?php else: ?>
                <p class="no-requests">No service requests yet. Need help? Submit a request and we'll get right on it.</p>
                <a href="/support-request-form/" class="submit-first-btn">Submit Your First Request</a>
            <?php endif; ?>
        </div>

        <style>
            .service-requests-section {
                background: #F3F5F8;
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
            }
            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }
            .section-header h3 {
                font-family: 'Poppins', sans-serif;
                font-size: 22px;
                font-weight: 600;
                color: #1C244B;
                margin: 0;
            }
            .new-request-btn {
                background: #467FF7;
                color: white !important;
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 500;
                transition: background 0.2s;
            }
            .new-request-btn:hover {
                background: #3366cc;
                color: white !important;
            }
            .requests-list {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .request-card {
                background: white;
                border-radius: 8px;
                padding: 16px;
                text-decoration: none;
                display: block;
                transition: all 0.2s;
                border: 2px solid transparent;
            }
            .request-card:hover {
                border-color: #467FF7;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(70, 127, 247, 0.15);
            }
            .request-header {
                margin-bottom: 8px;
            }
            .request-ref {
                font-family: 'Poppins', sans-serif;
                font-weight: 600;
                color: #467FF7;
                margin-right: 8px;
                font-size: 14px;
            }
            .request-subject {
                font-family: 'Poppins', sans-serif;
                color: #1C244B;
                font-weight: 500;
                font-size: 16px;
            }
            .request-meta {
                font-family: 'Poppins', sans-serif;
                font-size: 13px;
                color: #324A6D;
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }
            .request-status {
                padding: 3px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
            }
            .status-new { background: #e3f2fd; color: #1976d2; }
            .status-acknowledged { background: #fff3e0; color: #f57c00; }
            .status-in-progress { background: #e8f5e9; color: #388e3c; }
            .status-waiting-on-client { background: #fef9e7; color: #b7950b; }
            .status-on-hold { background: #e8eaf6; color: #5c6bc0; }
            .status-completed { background: #f5f5f5; color: #616161; }
            .status-cancelled { background: #ffebee; color: #c62828; }
            .request-hours {
                font-weight: 600;
                color: #467FF7;
            }
            .view-all-link {
                display: block;
                text-align: center;
                color: #467FF7;
                text-decoration: none;
                margin-top: 16px;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 500;
            }
            .view-all-link:hover {
                text-decoration: underline;
            }
            .no-requests {
                color: #324A6D;
                text-align: center;
                padding: 32px 24px 16px;
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
            }
            .submit-first-btn {
                display: block;
                background: #467FF7;
                color: white !important;
                padding: 12px 24px;
                border-radius: 6px;
                text-decoration: none;
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                font-weight: 500;
                text-align: center;
                max-width: 300px;
                margin: 0 auto;
                transition: background 0.2s;
            }
            .submit-first-btn:hover {
                background: #3366cc;
                color: white !important;
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .section-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 12px;
                }
                .new-request-btn {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Get total hours for a service request.
     *
     * This replaces the global bbab_get_sr_total_hours() function.
     */
    private function getSrTotalHours(int $sr_id): float {
        global $wpdb;

        // Sum the 'hours' field (rounded billable hours)
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2)))
            FROM {$wpdb->postmeta}
            WHERE post_id IN (
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = 'related_service_request'
                AND meta_value = %d
            )
            AND meta_key = 'hours'",
            $sr_id
        ));

        return floatval($total);
    }
}
