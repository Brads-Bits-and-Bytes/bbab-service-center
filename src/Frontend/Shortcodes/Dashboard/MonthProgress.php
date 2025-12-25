<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Month Progress shortcode.
 *
 * Displays a progress bar showing the current month's hours usage
 * against the free hours limit.
 *
 * Shortcode: [dashboard_hours_progress]
 * Migrated from: WPCode Snippet #1120
 */
class MonthProgress extends BaseShortcode {

    protected string $tag = 'dashboard_hours_progress';

    /**
     * Render the month progress output.
     */
    protected function output(array $atts, int $org_id): string {
        global $wpdb;

        // Find the most recent monthly report for this organization
        $report_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'monthly_report'
             AND p.post_status = 'publish'
             AND pm.meta_key = 'organization'
             AND pm.meta_value = %s
             ORDER BY p.post_date DESC
             LIMIT 1",
            $org_id
        ));

        if (!$report_id) {
            return '<p>No reports found for your organization yet.</p>';
        }

        // Get report data
        $report_month = get_post_meta($report_id, 'report_month', true);
        $total_hours = $this->getReportTotalHours((int) $report_id);
        $limit = get_post_meta($report_id, 'free_hours_limit', true);
        $limit = !empty($limit) ? floatval($limit) : 2;

        $percentage = ($limit > 0) ? min(($total_hours / $limit) * 100, 100) : 0;

        // Determine colors (base and gradient highlight)
        if ($percentage >= 100) {
            $color = '#e74c3c';
            $color_light = '#ec7063';
        } elseif ($percentage >= 81) {
            $color = '#e67e22';
            $color_light = '#f39c12';
        } elseif ($percentage >= 51) {
            $color = '#f39c12';
            $color_light = '#f7dc6f';
        } else {
            $color = '#3498db';
            $color_light = '#5dade2';
        }

        // Calculate overage
        $overage_html = '';
        if ($total_hours > $limit) {
            $overage_hours = round($total_hours - $limit, 2);
            $overage_cost = $overage_hours * 30;
            $overage_html = '<div style="margin-top: 8px; font-family: Poppins, sans-serif; font-size: 14px; color: #e74c3c; font-weight: 600;">Overage: ' . esc_html($overage_hours) . ' hours @ $30/hr = $' . esc_html(number_format($overage_cost, 2)) . '</div>';
        }

        // Unique ID for animation
        $unique_id = 'bbab-dash-progress-' . uniqid();

        // Build output
        $output = '<div class="bbab-dashboard-progress" style="margin-bottom: 20px;">';
        $output .= '<div style="font-family: Poppins, sans-serif; font-size: 14px; color: #324A6D; margin-bottom: 5px;">' . esc_html($report_month) . '</div>';
        $output .= '<div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">';
        $output .= '<span style="font-family: Poppins, sans-serif; font-size: 36px; font-weight: 600; text-transform: uppercase; line-height: 1.1; color: #1C244B;">Monthly Hours</span>';
        $output .= '<span style="font-family: Poppins, sans-serif; font-size: 24px; font-weight: 600; color: #1C244B;">' . esc_html(round($percentage)) . '%</span>';
        $output .= '</div>';
        $output .= '<div style="background-color: #e0e0e0; border-radius: 12px; height: 24px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">';
        $output .= '<div id="' . esc_attr($unique_id) . '" style="
            background: linear-gradient(180deg, ' . esc_attr($color_light) . ' 0%, ' . esc_attr($color) . ' 100%);
            height: 100%;
            width: 0%;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: width 1s ease-out;
        "></div>';
        $output .= '</div>';
        $output .= '<div style="margin-top: 10px; font-family: Poppins, sans-serif; font-size: 16px; color: #1C244B;">' . esc_html($total_hours) . ' / ' . esc_html($limit) . ' Free Hours Used</div>';
        $output .= $overage_html;
        $output .= '</div>';

        // Animation script
        $output .= '<script>
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(function() {
                    var el = document.getElementById("' . esc_js($unique_id) . '");
                    if (el) { el.style.width = "' . esc_js($percentage) . '%"; }
                }, 100);
            });
        </script>';

        return $output;
    }

    /**
     * Get total hours for a monthly report.
     *
     * This replaces the global bbab_get_report_total_hours() function.
     */
    private function getReportTotalHours(int $report_id): float {
        // Try to get cached value first
        $cached = get_post_meta($report_id, 'total_hours', true);
        if ($cached !== '' && $cached !== false) {
            return floatval($cached);
        }

        // Calculate from time entries if not cached
        $time_entries = get_posts([
            'post_type' => 'time_entry',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'related_monthly_report',
                    'value' => $report_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $total = 0.0;
        foreach ($time_entries as $entry) {
            $hours = get_post_meta($entry->ID, 'hours', true);
            $total += floatval($hours);
        }

        return round($total, 2);
    }
}
