<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;
use BBAB\ServiceCenter\Modules\Billing\MonthlyReportService;

/**
 * Hours Progress Bar shortcode for single report pages.
 *
 * Displays a colorful progress bar showing hours used vs free hours limit.
 * Color changes based on percentage: blue < 51%, yellow < 81%, orange < 100%, red >= 100%.
 *
 * Shortcode: [hours_progress_bar]
 * Attributes:
 *   - report_id: (optional) The monthly report ID. Defaults to current post ID.
 *
 * Migrated from: WPCode Snippet #1076
 */
class HoursProgressBar extends BaseShortcode {

    protected string $tag = 'hours_progress_bar';

    /**
     * This shortcode doesn't require org context - it works with a report_id.
     */
    protected bool $requires_org = false;

    /**
     * Render the progress bar output.
     */
    protected function output(array $atts, int $org_id): string {
        $atts = $this->parseAtts($atts, [
            'report_id' => get_the_ID(),
        ]);

        $report_id = absint($atts['report_id']);

        if (!$report_id) {
            return '';
        }

        // Verify this is a monthly_report post
        $post = get_post($report_id);
        if (!$post || $post->post_type !== 'monthly_report') {
            return '';
        }

        // Get report data using MonthlyReportService (queries by date range, not meta)
        $total_hours = MonthlyReportService::getTotalHours($report_id);
        $limit = MonthlyReportService::getFreeHoursLimit($report_id);

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

        // Unique ID for this instance (for animation)
        $unique_id = 'bbab-progress-' . uniqid();

        // Build output
        ob_start();
        ?>
        <div class="bbab-progress-container" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                <span style="font-family: Poppins, sans-serif; font-size: 36px; font-weight: 600; text-transform: uppercase; line-height: 1.1; color: #1C244B;">Monthly Hours</span>
                <span style="font-family: Poppins, sans-serif; font-size: 24px; font-weight: 600; color: #1C244B;"><?php echo esc_html(round($percentage)); ?>%</span>
            </div>
            <div style="background-color: #e0e0e0; border-radius: 12px; height: 24px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                <div id="<?php echo esc_attr($unique_id); ?>" style="
                    background: linear-gradient(180deg, <?php echo esc_attr($color_light); ?> 0%, <?php echo esc_attr($color); ?> 100%);
                    height: 100%;
                    width: 0%;
                    border-radius: 12px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
                    transition: width 1s ease-out;
                "></div>
            </div>
            <div style="margin-top: 10px; font-family: Poppins, sans-serif; font-size: 16px; color: #1C244B;">
                <?php echo esc_html($total_hours); ?> / <?php echo esc_html($limit); ?> Free Hours Used
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(function() {
                    var el = document.getElementById("<?php echo esc_js($unique_id); ?>");
                    if (el) { el.style.width = "<?php echo esc_js($percentage); ?>%"; }
                }, 100);
            });
        </script>
        <?php
        return ob_get_clean();
    }
}
