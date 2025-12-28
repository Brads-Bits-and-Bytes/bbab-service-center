<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Admin\Metaboxes;

use BBAB\ServiceCenter\Modules\Billing\MonthlyReportService;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Monthly Report editor metaboxes.
 *
 * Displays on monthly_report edit screens:
 * - Time Entries for this report period (sidebar)
 *
 * Migrated from: Phase 7.6 (new functionality)
 */
class MonthlyReportMetabox {

    /**
     * Register hooks.
     */
    public static function register(): void {
        add_action('add_meta_boxes', [self::class, 'registerMetaboxes']);
        add_action('admin_head', [self::class, 'renderStyles']);

        Logger::debug('MonthlyReportMetabox', 'Registered monthly report metabox hooks');
    }

    /**
     * Register the metaboxes.
     */
    public static function registerMetaboxes(): void {
        // Time Entries (sidebar)
        add_meta_box(
            'bbab_monthly_report_time_entries',
            'Time Entries',
            [self::class, 'renderTimeEntriesMetabox'],
            'monthly_report',
            'side',
            'default'
        );
    }

    /**
     * Render time entries metabox.
     *
     * @param \WP_Post $post The post object.
     */
    public static function renderTimeEntriesMetabox(\WP_Post $post): void {
        $report_id = $post->ID;

        // Get time entries using MonthlyReportService (queries by date range)
        $time_entries = MonthlyReportService::getTimeEntries($report_id);

        // Sort by entry_date descending (most recent first)
        usort($time_entries, function($a, $b) {
            $date_a = get_post_meta($a->ID, 'entry_date', true);
            $date_b = get_post_meta($b->ID, 'entry_date', true);
            return strtotime($date_b) - strtotime($date_a);
        });

        // Calculate totals
        $total_hours = 0.0;
        $billable_hours = 0.0;
        foreach ($time_entries as $te) {
            $hours = (float) get_post_meta($te->ID, 'hours', true);
            $total_hours += $hours;
            $billable = get_post_meta($te->ID, 'billable', true);
            if ($billable !== '0' && $billable !== 0 && $billable !== false) {
                $billable_hours += $hours;
            }
        }

        $count = count($time_entries);

        // Summary bar
        echo '<div class="bbab-summary-bar">';
        echo '<strong>' . $count . ' entr' . ($count === 1 ? 'y' : 'ies') . '</strong> &bull; ' . number_format($total_hours, 2) . ' hrs total';
        if ($billable_hours !== $total_hours) {
            echo '<br><span style="color: #059669; font-weight: 600;">' . number_format($billable_hours, 2) . ' billable hrs</span>';
            echo ' <span style="color: #666;">(' . number_format($total_hours - $billable_hours, 2) . ' no charge)</span>';
        }
        echo '</div>';

        // Time entries group
        echo '<div class="bbab-te-group">';
        echo '<div class="bbab-group-header-green">';
        echo '<span>Time Entries</span>';
        echo '<span>' . $count . '</span>';
        echo '</div>';

        if ($count > 0) {
            echo '<div class="bbab-scroll-container" style="max-height: 350px;">';
            foreach ($time_entries as $te) {
                self::renderTeRow($te);
            }
            echo '</div>';
        } else {
            echo '<div class="bbab-empty-state">No time entries for this report period.</div>';
        }

        echo '</div>';
    }

    /**
     * Render a single time entry row.
     *
     * @param \WP_Post $te Time entry post.
     */
    private static function renderTeRow(\WP_Post $te): void {
        $te_id = $te->ID;
        $date = get_post_meta($te_id, 'entry_date', true);
        $title = get_the_title($te_id);
        $te_ref = get_post_meta($te_id, 'reference_number', true);
        $hours = (float) get_post_meta($te_id, 'hours', true);
        $billable = get_post_meta($te_id, 'billable', true);
        $edit_link = get_edit_post_link($te_id);

        // Get related SR
        $sr_id = get_post_meta($te_id, 'related_service_request', true);
        $sr_ref = '';
        if ($sr_id) {
            $sr_ref = get_post_meta($sr_id, 'reference_number', true);
        }

        $formatted_date = $date ? date('M j', strtotime($date)) : '-';
        $billable_badge = ($billable === '0' || $billable === 0 || $billable === false)
            ? ' <span style="background: #d5f5e3; color: #1e8449; padding: 1px 5px; border-radius: 3px; font-size: 10px;">NC</span>'
            : '';

        // Display: TE-XXXX - Title (or just TE-XXXX if no title)
        $display_text = $te_ref ?: 'TE';
        if (!empty($title) && $title !== 'Auto Draft' && $title !== $te_ref) {
            $display_text = $te_ref . ' - ' . wp_trim_words($title, 6, '...');
        }

        echo '<div class="bbab-te-row">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
        echo '<span style="color: #666;">' . esc_html($formatted_date) . '</span>';
        echo '<span>' . number_format($hours, 2) . ' hrs' . $billable_badge . '</span>';
        echo '</div>';
        echo '<div style="margin-top: 3px;">';
        echo '<a href="' . esc_url($edit_link) . '">' . esc_html($display_text) . '</a>';
        if ($sr_ref) {
            echo ' <span style="color: #888; font-size: 10px;">(' . esc_html($sr_ref) . ')</span>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render admin styles for metaboxes.
     */
    public static function renderStyles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'monthly_report') {
            return;
        }

        echo '<style>
            /* Sidebar Metaboxes */
            #bbab_monthly_report_time_entries .inside { margin: 0; padding: 0; }

            /* Group Headers */
            .bbab-group-header-green {
                background: #059669; color: white; padding: 8px 10px;
                font-weight: 600; font-size: 12px;
                display: flex; justify-content: space-between; align-items: center;
            }

            /* Rows */
            .bbab-te-row {
                padding: 8px 10px; border-bottom: 1px solid #eee;
                font-size: 12px; background: white;
            }
            .bbab-te-row a { text-decoration: none; color: #1e40af; }
            .bbab-te-row a:hover { text-decoration: underline; }

            /* Empty State */
            .bbab-empty-state {
                padding: 10px; color: #666; font-style: italic;
                font-size: 12px; background: #fafafa;
            }

            /* Summary */
            .bbab-summary-bar {
                background: #f0f6fc; border-left: 4px solid #2271b1;
                padding: 10px 12px; margin: 12px; font-size: 13px;
            }

            /* Scroll Container */
            .bbab-scroll-container { max-height: 400px; overflow-y: auto; }
        </style>';
    }
}
