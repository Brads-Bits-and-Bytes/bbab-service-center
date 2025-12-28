<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Admin\Columns;

use BBAB\ServiceCenter\Utils\Logger;

/**
 * Custom admin columns for Client Organizations.
 *
 * Displays:
 * - Full Name (organization_name field)
 * - Shortcode (organization_shortcode field)
 * - Looker URL status
 * - Report count
 * - User count
 *
 * Migrated from: WPCode Snippet #1109 (CO section)
 */
class OrganizationColumns {

    /**
     * Register all hooks.
     */
    public static function register(): void {
        // Column definitions
        add_filter('manage_client_organization_posts_columns', [self::class, 'defineColumns']);
        add_action('manage_client_organization_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-client_organization_sortable_columns', [self::class, 'sortableColumns']);

        // Handle sorting
        add_action('pre_get_posts', [self::class, 'handleSorting']);

        // Admin styles
        add_action('admin_head', [self::class, 'renderStyles']);

        Logger::debug('OrganizationColumns', 'Registered organization column hooks');
    }

    /**
     * Define custom columns.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function defineColumns(array $columns): array {
        $new_columns = [];

        // Keep checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }

        // Our custom columns (no title - Full Name serves as the linked column)
        $new_columns['org_name'] = 'Full Name';
        $new_columns['org_shortcode'] = 'Shortcode';
        $new_columns['has_looker'] = 'Looker URL';
        $new_columns['report_count'] = 'Reports';
        $new_columns['user_count'] = 'Users';
        $new_columns['date'] = 'Date';

        return $new_columns;
    }

    /**
     * Render column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function renderColumn(string $column, int $post_id): void {
        switch ($column) {
            case 'org_name':
                $name = get_post_meta($post_id, 'organization_name', true);
                $display = !empty($name) ? $name : get_the_title($post_id);
                $edit_link = get_edit_post_link($post_id);
                echo '<strong><a class="row-title" href="' . esc_url($edit_link) . '">' . esc_html($display) . '</a></strong>';
                break;

            case 'org_shortcode':
                $code = get_post_meta($post_id, 'organization_shortcode', true);
                echo $code ? '<code class="org-shortcode">' . esc_html($code) . '</code>' : '<span class="no-value">—</span>';
                break;

            case 'has_looker':
                $url = get_post_meta($post_id, 'looker_embed_url', true);
                if (!empty($url)) {
                    echo '<span class="status-yes">Yes</span>';
                } else {
                    echo '<span class="status-no">No</span>';
                }
                break;

            case 'report_count':
                echo esc_html((string) self::getReportCount($post_id));
                break;

            case 'user_count':
                echo esc_html((string) self::getUserCount($post_id));
                break;
        }
    }

    /**
     * Get count of monthly reports for an organization.
     *
     * @param int $org_id Organization ID.
     * @return int Report count.
     */
    private static function getReportCount(int $org_id): int {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'organization'
             AND pm.meta_value = %s
             AND p.post_type = 'monthly_report'
             AND p.post_status = 'publish'",
            $org_id
        ));

        return (int) $count;
    }

    /**
     * Get count of users assigned to an organization.
     *
     * @param int $org_id Organization ID.
     * @return int User count.
     */
    private static function getUserCount(int $org_id): int {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'organization' AND meta_value = %s",
            $org_id
        ));

        return (int) $count;
    }

    /**
     * Define sortable columns.
     *
     * @param array $columns Sortable columns.
     * @return array Modified sortable columns.
     */
    public static function sortableColumns(array $columns): array {
        $columns['org_name'] = 'org_name';
        $columns['org_shortcode'] = 'org_shortcode';
        return $columns;
    }

    /**
     * Handle custom column sorting.
     *
     * @param \WP_Query $query The query object.
     */
    public static function handleSorting(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'client_organization') {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby === 'org_name') {
            $query->set('meta_key', 'organization_name');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'org_shortcode') {
            $query->set('meta_key', 'organization_shortcode');
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Render column styles.
     */
    public static function renderStyles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'client_organization') {
            return;
        }

        echo '<style>
            /* Organization columns - balanced widths */
            .column-org_name { width: 200px; }
            .column-org_shortcode { width: 100px; }
            .column-has_looker { width: 100px; text-align: center; }
            .column-report_count { width: 80px; text-align: center; }
            .column-user_count { width: 80px; text-align: center; }

            /* Shortcode styling */
            .org-shortcode {
                background: #f0f6fc;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
            }

            /* Status indicators */
            .status-yes {
                color: #27ae60;
                font-weight: 500;
            }
            .status-no {
                color: #999;
            }
            .no-value {
                color: #999;
            }
        </style>';
    }
}
