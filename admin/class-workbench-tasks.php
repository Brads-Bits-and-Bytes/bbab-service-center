<?php
/**
 * Brad's Workbench - Client Tasks Sub-Page.
 *
 * @package BBAB\Core\Admin
 * @since   1.0.0
 */

namespace BBAB\Core\Admin;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Workbench_Tasks
 *
 * Handles the Client Tasks sub-page with enhanced list table.
 *
 * @since 1.0.0
 */
class Workbench_Tasks {

    /**
     * Cache instance.
     *
     * @var Cache
     */
    private $cache;

    /**
     * The list table instance.
     *
     * @var Tasks_List_Table
     */
    private $list_table;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->cache = new Cache();
    }

    /**
     * Render the tasks page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_page() {
        // Security check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bbab-core' ) );
        }

        // Initialize the list table.
        $this->list_table = new Tasks_List_Table();
        $this->list_table->prepare_items();

        // Get summary stats.
        $stats = $this->get_summary_stats();

        // Get organizations for filter.
        $organizations = $this->get_organizations();

        // Current filters.
        $current_status = isset( $_GET['task_status'] ) ? sanitize_text_field( $_GET['task_status'] ) : '';
        $current_org    = isset( $_GET['organization'] ) ? absint( $_GET['organization'] ) : 0;

        // Load template.
        include BBAB_CORE_PATH . 'admin/partials/workbench-tasks.php';
    }

    /**
     * Get summary statistics for client tasks.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_summary_stats() {
        $cache_key = 'tasks_summary_stats';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Get all tasks.
        $all_tasks = get_posts( array(
            'post_type'      => 'client_task',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $stats = array(
            'total_pending'   => 0,
            'total_completed' => 0,
            'overdue'         => 0,
            'due_soon'        => 0,
        );

        $today = strtotime( 'today' );
        $soon  = strtotime( '+3 days' );

        foreach ( $all_tasks as $task ) {
            $status   = get_post_meta( $task->ID, 'task_status', true );
            $due_date = get_post_meta( $task->ID, 'due_date', true );

            if ( 'Pending' === $status ) {
                $stats['total_pending']++;

                if ( ! empty( $due_date ) ) {
                    $due_timestamp = strtotime( $due_date );
                    if ( $due_timestamp < $today ) {
                        $stats['overdue']++;
                    } elseif ( $due_timestamp <= $soon ) {
                        $stats['due_soon']++;
                    }
                }
            } elseif ( 'Completed' === $status ) {
                $stats['total_completed']++;
            }
        }

        $this->cache->set( $cache_key, $stats, HOUR_IN_SECONDS );

        return $stats;
    }

    /**
     * Get all organizations for filter dropdown.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_organizations() {
        $orgs = get_posts( array(
            'post_type'      => 'client_organization',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $result = array();
        foreach ( $orgs as $org ) {
            $shortcode = get_post_meta( $org->ID, 'organization_shortcode', true );
            $result[] = array(
                'id'        => $org->ID,
                'name'      => $org->post_title,
                'shortcode' => $shortcode,
            );
        }

        return $result;
    }
}

/**
 * Class Tasks_List_Table
 *
 * Custom WP_List_Table for displaying client tasks.
 *
 * @since 1.0.0
 */
class Tasks_List_Table extends \WP_List_Table {

    /**
     * Cache instance.
     *
     * @var Cache
     */
    private $cache;

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => 'task',
            'plural'   => 'tasks',
            'ajax'     => false,
        ) );

        $this->cache = new Cache();
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'task_description' => __( 'Task', 'bbab-core' ),
            'organization'     => __( 'Client', 'bbab-core' ),
            'due_date'         => __( 'Due Date', 'bbab-core' ),
            'status'           => __( 'Status', 'bbab-core' ),
            'created_date'     => __( 'Created', 'bbab-core' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'task_description' => array( 'task_description', false ),
            'organization'     => array( 'organization', false ),
            'due_date'         => array( 'due_date', true ),
            'status'           => array( 'task_status', false ),
            'created_date'     => array( 'created_date', false ),
        );
    }

    /**
     * Prepare items for display.
     *
     * @return void
     */
    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        // Get filter values.
        $status_filter = isset( $_GET['task_status'] ) ? sanitize_text_field( $_GET['task_status'] ) : '';
        $org_filter    = isset( $_GET['organization'] ) ? absint( $_GET['organization'] ) : 0;
        $search        = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Build query args.
        $args = array(
            'post_type'      => 'client_task',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        // Status filter.
        if ( ! empty( $status_filter ) ) {
            $args['meta_query'][] = array(
                'key'     => 'task_status',
                'value'   => $status_filter,
                'compare' => '=',
            );
        } else {
            // Default: show pending tasks only.
            $args['meta_query'][] = array(
                'key'     => 'task_status',
                'value'   => 'Pending',
                'compare' => '=',
            );
        }

        // Ensure meta_query has relation if multiple conditions.
        if ( isset( $args['meta_query'] ) && count( $args['meta_query'] ) > 1 ) {
            $args['meta_query']['relation'] = 'AND';
        }

        $tasks = get_posts( $args );

        // Filter by organization (uses Advanced Relationship - must query wp_podsrel).
        if ( ! empty( $org_filter ) ) {
            global $wpdb;

            // Get task IDs linked to this organization.
            $org_task_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT item_id FROM {$wpdb->prefix}podsrel
                 WHERE related_item_id = %d AND field_id = 1320",
                $org_filter
            ) );

            $tasks = array_filter( $tasks, function( $task ) use ( $org_task_ids ) {
                return in_array( $task->ID, $org_task_ids, true );
            } );
            $tasks = array_values( $tasks );
        }

        // Custom search - filter results by meta fields if search term provided.
        if ( ! empty( $search ) ) {
            $search_lower = strtolower( $search );
            $tasks = array_filter( $tasks, function( $task ) use ( $search_lower ) {
                // Search in task_description.
                $desc = strtolower( get_post_meta( $task->ID, 'task_description', true ) );
                if ( strpos( $desc, $search_lower ) !== false ) {
                    return true;
                }

                // Search in post title.
                if ( strpos( strtolower( $task->post_title ), $search_lower ) !== false ) {
                    return true;
                }

                // Search in organization name/shortcode.
                $org_id = $this->get_task_org_id( $task->ID );
                if ( ! empty( $org_id ) ) {
                    $org_name = strtolower( get_the_title( $org_id ) );
                    $org_shortcode = strtolower( get_post_meta( $org_id, 'organization_shortcode', true ) );
                    if ( strpos( $org_name, $search_lower ) !== false || strpos( $org_shortcode, $search_lower ) !== false ) {
                        return true;
                    }
                }

                return false;
            } );
            $tasks = array_values( $tasks ); // Re-index array.
        }

        // Check for user-requested sorting via column headers.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';
        $order   = isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'desc' ? 'desc' : 'asc';

        $today = strtotime( 'today' );

        if ( ! empty( $orderby ) ) {
            usort( $tasks, function( $a, $b ) use ( $orderby, $order, $today ) {
                $result = 0;

                switch ( $orderby ) {
                    case 'task_description':
                        $desc_a = get_post_meta( $a->ID, 'task_description', true ) ?: $a->post_title;
                        $desc_b = get_post_meta( $b->ID, 'task_description', true ) ?: $b->post_title;
                        $result = strcasecmp( $desc_a, $desc_b );
                        break;

                    case 'organization':
                        $org_a = $this->get_task_org_id( $a->ID );
                        $org_b = $this->get_task_org_id( $b->ID );
                        $short_a = $org_a ? get_post_meta( $org_a, 'organization_shortcode', true ) : '';
                        $short_b = $org_b ? get_post_meta( $org_b, 'organization_shortcode', true ) : '';
                        $result = strcasecmp( $short_a, $short_b );
                        break;

                    case 'due_date':
                        $due_a = get_post_meta( $a->ID, 'due_date', true );
                        $due_b = get_post_meta( $b->ID, 'due_date', true );
                        $time_a = ! empty( $due_a ) ? strtotime( $due_a ) : PHP_INT_MAX;
                        $time_b = ! empty( $due_b ) ? strtotime( $due_b ) : PHP_INT_MAX;
                        $result = $time_a - $time_b;
                        break;

                    case 'task_status':
                        $stat_a = get_post_meta( $a->ID, 'task_status', true );
                        $stat_b = get_post_meta( $b->ID, 'task_status', true );
                        $result = strcasecmp( $stat_a, $stat_b );
                        break;

                    case 'created_date':
                        $created_a = get_post_meta( $a->ID, 'created_date', true );
                        $created_b = get_post_meta( $b->ID, 'created_date', true );
                        $time_a = ! empty( $created_a ) ? strtotime( $created_a ) : strtotime( $a->post_date );
                        $time_b = ! empty( $created_b ) ? strtotime( $created_b ) : strtotime( $b->post_date );
                        $result = $time_a - $time_b;
                        break;
                }

                return $order === 'desc' ? -$result : $result;
            } );
        } else {
            // Default sort: by due date (soonest/overdue first), then by created date.
            usort( $tasks, function( $a, $b ) use ( $today ) {
                $due_a = get_post_meta( $a->ID, 'due_date', true );
                $due_b = get_post_meta( $b->ID, 'due_date', true );

                // Items with due date come before items without.
                if ( empty( $due_a ) && ! empty( $due_b ) ) {
                    return 1;
                }
                if ( ! empty( $due_a ) && empty( $due_b ) ) {
                    return -1;
                }
                if ( ! empty( $due_a ) && ! empty( $due_b ) ) {
                    // Sort by due date (earliest first).
                    return strtotime( $due_a ) - strtotime( $due_b );
                }

                // Neither has due date, sort by created date (newest first).
                return strtotime( $b->post_date ) - strtotime( $a->post_date );
            } );
        }

        // Pagination.
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $total_items  = count( $tasks );

        $this->items = array_slice( $tasks, ( $current_page - 1 ) * $per_page, $per_page );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    /**
     * Get organization ID for a task.
     *
     * Client tasks use Advanced Relationship which stores data in wp_podsrel.
     *
     * @param int $task_id Task ID.
     * @return int|null Organization ID or null.
     */
    private function get_task_org_id( $task_id ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT related_item_id FROM {$wpdb->prefix}podsrel
             WHERE item_id = %d AND field_id = 1320",
            $task_id
        ) );
    }

    /**
     * Render the task description column.
     *
     * @param \WP_Post $item The task post.
     * @return string
     */
    public function column_task_description( $item ) {
        $description = get_post_meta( $item->ID, 'task_description', true );
        $display     = ! empty( $description ) ? $description : $item->post_title;
        $edit_link   = get_edit_post_link( $item->ID, 'raw' );

        // Truncate if too long.
        $truncated = mb_strlen( $display ) > 60 ? mb_substr( $display, 0, 60 ) . '...' : $display;

        // Row actions.
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_link ),
                __( 'Edit', 'bbab-core' )
            ),
        );

        // Add "Mark Complete" action for pending tasks.
        $status = get_post_meta( $item->ID, 'task_status', true );
        if ( 'Pending' === $status ) {
            $complete_url = add_query_arg(
                array(
                    'action'     => 'bbab_complete_task',
                    'task_id'    => $item->ID,
                    '_wpnonce'   => wp_create_nonce( 'bbab_complete_task_' . $item->ID ),
                ),
                admin_url( 'admin-post.php' )
            );
            $actions['complete'] = sprintf(
                '<a href="%s" style="color: #00a32a;">%s</a>',
                esc_url( $complete_url ),
                __( 'Mark Complete', 'bbab-core' )
            );
        }

        return sprintf(
            '<a href="%s"><strong title="%s">%s</strong></a>%s',
            esc_url( $edit_link ),
            esc_attr( $display ),
            esc_html( $truncated ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Render the organization column.
     *
     * @param \WP_Post $item The task post.
     * @return string
     */
    public function column_organization( $item ) {
        $org_id = $this->get_task_org_id( $item->ID );

        if ( empty( $org_id ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        $shortcode = get_post_meta( $org_id, 'organization_shortcode', true );
        $name      = get_the_title( $org_id );

        return sprintf(
            '<span class="bbab-org-badge" title="%s">%s</span>',
            esc_attr( $name ),
            esc_html( $shortcode ?: $name )
        );
    }

    /**
     * Render the due date column.
     *
     * @param \WP_Post $item The task post.
     * @return string
     */
    public function column_due_date( $item ) {
        $due_date = get_post_meta( $item->ID, 'due_date', true );
        $status   = get_post_meta( $item->ID, 'task_status', true );

        if ( empty( $due_date ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        $due_timestamp = strtotime( $due_date );
        $display       = date_i18n( 'M j, Y', $due_timestamp );
        $today         = strtotime( 'today' );
        $soon          = strtotime( '+3 days' );

        // Add urgency indicators for pending tasks.
        if ( 'Pending' === $status ) {
            if ( $due_timestamp < $today ) {
                $days_overdue = floor( ( $today - $due_timestamp ) / DAY_IN_SECONDS );
                return sprintf(
                    '<span class="bbab-overdue">%s <small>(%dd overdue)</small></span>',
                    esc_html( $display ),
                    $days_overdue
                );
            } elseif ( $due_timestamp <= $soon ) {
                return sprintf(
                    '<span class="bbab-due-soon">%s</span>',
                    esc_html( $display )
                );
            }
        }

        return esc_html( $display );
    }

    /**
     * Render the status column.
     *
     * @param \WP_Post $item The task post.
     * @return string
     */
    public function column_status( $item ) {
        $status = get_post_meta( $item->ID, 'task_status', true );

        $css_class = 'status-' . sanitize_title( $status );

        return sprintf(
            '<span class="bbab-status-badge %s">%s</span>',
            esc_attr( $css_class ),
            esc_html( $status )
        );
    }

    /**
     * Render the created date column.
     *
     * @param \WP_Post $item The task post.
     * @return string
     */
    public function column_created_date( $item ) {
        $created_date = get_post_meta( $item->ID, 'created_date', true );

        if ( empty( $created_date ) ) {
            // Fallback to post date.
            return date_i18n( 'M j, Y', strtotime( $item->post_date ) );
        }

        return date_i18n( 'M j, Y', strtotime( $created_date ) );
    }

    /**
     * Default column renderer.
     *
     * @param \WP_Post $item        The task post.
     * @param string   $column_name Column name.
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return '';
    }

    /**
     * Message when no items found.
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No client tasks found.', 'bbab-core' );
    }
}
