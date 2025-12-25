<?php
/**
 * Brad's Workbench - Roadmap Items Sub-Page.
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
 * Class Workbench_Roadmap
 *
 * Handles the Roadmap Items sub-page with enhanced list table.
 *
 * @since 1.0.0
 */
class Workbench_Roadmap {

    /**
     * Cache instance.
     *
     * @var Cache
     */
    private $cache;

    /**
     * The list table instance.
     *
     * @var Roadmap_List_Table
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
     * Render the roadmap page.
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
        $this->list_table = new Roadmap_List_Table();
        $this->list_table->prepare_items();

        // Get summary stats.
        $stats = $this->get_summary_stats();

        // Get organizations for filter.
        $organizations = $this->get_organizations();

        // Current filters.
        $current_status = isset( $_GET['roadmap_status'] ) ? sanitize_text_field( $_GET['roadmap_status'] ) : '';
        $current_org    = isset( $_GET['organization'] ) ? absint( $_GET['organization'] ) : 0;

        // Load template.
        include BBAB_CORE_PATH . 'admin/partials/workbench-roadmap.php';
    }

    /**
     * Get summary statistics for roadmap items.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_summary_stats() {
        $cache_key = 'roadmap_summary_stats';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Get all roadmap items.
        $all_items = get_posts( array(
            'post_type'      => 'roadmap_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $stats = array(
            'total_idea'     => 0,
            'total_adr'      => 0,
            'total_proposed' => 0,
            'total_approved' => 0,
            'total_declined' => 0,
        );

        foreach ( $all_items as $item ) {
            $status = get_post_meta( $item->ID, 'roadmap_status', true );

            switch ( $status ) {
                case 'Idea':
                    $stats['total_idea']++;
                    break;
                case 'ADR In Progress':
                    $stats['total_adr']++;
                    break;
                case 'Proposed':
                    $stats['total_proposed']++;
                    break;
                case 'Approved':
                    $stats['total_approved']++;
                    break;
                case 'Declined':
                    $stats['total_declined']++;
                    break;
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
 * Class Roadmap_List_Table
 *
 * Custom WP_List_Table for displaying roadmap items.
 *
 * @since 1.0.0
 */
class Roadmap_List_Table extends \WP_List_Table {

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
            'singular' => 'roadmap_item',
            'plural'   => 'roadmap_items',
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
            'title'        => __( 'Feature', 'bbab-core' ),
            'organization' => __( 'Client', 'bbab-core' ),
            'status'       => __( 'Status', 'bbab-core' ),
            'priority'     => __( 'Priority', 'bbab-core' ),
            'category'     => __( 'Category', 'bbab-core' ),
            'submitted_by' => __( 'Submitted By', 'bbab-core' ),
            'project'      => __( 'Project', 'bbab-core' ),
        );
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'title'        => array( 'title', false ),
            'organization' => array( 'organization', false ),
            'status'       => array( 'roadmap_status', false ),
            'priority'     => array( 'priority', false ),
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
        $status_filter = isset( $_GET['roadmap_status'] ) ? sanitize_text_field( $_GET['roadmap_status'] ) : '';
        $org_filter    = isset( $_GET['organization'] ) ? absint( $_GET['organization'] ) : 0;
        $search        = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Build query args.
        $args = array(
            'post_type'      => 'roadmap_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        // Status filter.
        if ( ! empty( $status_filter ) ) {
            $args['meta_query'][] = array(
                'key'     => 'roadmap_status',
                'value'   => $status_filter,
                'compare' => '=',
            );
        } else {
            // Default: show active items only (not Approved/Declined).
            $args['meta_query'][] = array(
                'key'     => 'roadmap_status',
                'value'   => array( 'Idea', 'ADR In Progress', 'Proposed' ),
                'compare' => 'IN',
            );
        }

        // Org filter.
        if ( ! empty( $org_filter ) ) {
            $args['meta_query'][] = array(
                'key'     => 'organization',
                'value'   => $org_filter,
                'compare' => '=',
            );
        }

        // Ensure meta_query has relation if multiple conditions.
        if ( isset( $args['meta_query'] ) && count( $args['meta_query'] ) > 1 ) {
            $args['meta_query']['relation'] = 'AND';
        }

        $items = get_posts( $args );

        // Custom search - filter results if search term provided.
        if ( ! empty( $search ) ) {
            $search_lower = strtolower( $search );
            $items = array_filter( $items, function( $item ) use ( $search_lower ) {
                // Search in title.
                if ( strpos( strtolower( $item->post_title ), $search_lower ) !== false ) {
                    return true;
                }

                // Search in description.
                $desc = strtolower( get_post_meta( $item->ID, 'description', true ) );
                if ( strpos( $desc, $search_lower ) !== false ) {
                    return true;
                }

                // Search in organization name/shortcode.
                $org_id = get_post_meta( $item->ID, 'organization', true );
                if ( ! empty( $org_id ) ) {
                    if ( is_array( $org_id ) ) {
                        $org_id = reset( $org_id );
                    }
                    $org_name = strtolower( get_the_title( $org_id ) );
                    $org_shortcode = strtolower( get_post_meta( $org_id, 'organization_shortcode', true ) );
                    if ( strpos( $org_name, $search_lower ) !== false || strpos( $org_shortcode, $search_lower ) !== false ) {
                        return true;
                    }
                }

                return false;
            } );
            $items = array_values( $items );
        }

        // Check for user-requested sorting via column headers.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : '';
        $order   = isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'desc' ? 'desc' : 'asc';

        // Priority order for sorting.
        $priority_order = array(
            'High'   => 1,
            'Medium' => 2,
            'Low'    => 3,
        );

        // Status order for sorting.
        $status_order = array(
            'Proposed'        => 1,
            'ADR In Progress' => 2,
            'Idea'            => 3,
            'Approved'        => 4,
            'Declined'        => 5,
        );

        if ( ! empty( $orderby ) ) {
            usort( $items, function( $a, $b ) use ( $orderby, $order, $priority_order, $status_order ) {
                $result = 0;

                switch ( $orderby ) {
                    case 'title':
                        $result = strcasecmp( $a->post_title, $b->post_title );
                        break;

                    case 'organization':
                        $org_a = get_post_meta( $a->ID, 'organization', true );
                        $org_b = get_post_meta( $b->ID, 'organization', true );
                        $org_a = is_array( $org_a ) ? reset( $org_a ) : $org_a;
                        $org_b = is_array( $org_b ) ? reset( $org_b ) : $org_b;
                        $short_a = $org_a ? get_post_meta( $org_a, 'organization_shortcode', true ) : '';
                        $short_b = $org_b ? get_post_meta( $org_b, 'organization_shortcode', true ) : '';
                        $result = strcasecmp( $short_a, $short_b );
                        break;

                    case 'roadmap_status':
                        $stat_a = get_post_meta( $a->ID, 'roadmap_status', true );
                        $stat_b = get_post_meta( $b->ID, 'roadmap_status', true );
                        $val_a = isset( $status_order[ $stat_a ] ) ? $status_order[ $stat_a ] : 99;
                        $val_b = isset( $status_order[ $stat_b ] ) ? $status_order[ $stat_b ] : 99;
                        $result = $val_a - $val_b;
                        break;

                    case 'priority':
                        $prio_a = get_post_meta( $a->ID, 'priority', true );
                        $prio_b = get_post_meta( $b->ID, 'priority', true );
                        $val_a = isset( $priority_order[ $prio_a ] ) ? $priority_order[ $prio_a ] : 99;
                        $val_b = isset( $priority_order[ $prio_b ] ) ? $priority_order[ $prio_b ] : 99;
                        $result = $val_a - $val_b;
                        break;
                }

                return $order === 'desc' ? -$result : $result;
            } );
        } else {
            // Default sort: by status priority, then by priority level, then by date.
            usort( $items, function( $a, $b ) use ( $status_order, $priority_order ) {
                $status_a = get_post_meta( $a->ID, 'roadmap_status', true );
                $status_b = get_post_meta( $b->ID, 'roadmap_status', true );

                $s_order_a = isset( $status_order[ $status_a ] ) ? $status_order[ $status_a ] : 99;
                $s_order_b = isset( $status_order[ $status_b ] ) ? $status_order[ $status_b ] : 99;

                if ( $s_order_a !== $s_order_b ) {
                    return $s_order_a - $s_order_b;
                }

                // Same status, sort by priority.
                $prio_a = get_post_meta( $a->ID, 'priority', true );
                $prio_b = get_post_meta( $b->ID, 'priority', true );

                $p_order_a = isset( $priority_order[ $prio_a ] ) ? $priority_order[ $prio_a ] : 99;
                $p_order_b = isset( $priority_order[ $prio_b ] ) ? $priority_order[ $prio_b ] : 99;

                if ( $p_order_a !== $p_order_b ) {
                    return $p_order_a - $p_order_b;
                }

                // Same priority, sort by date (newest first).
                return strtotime( $b->post_date ) - strtotime( $a->post_date );
            } );
        }

        // Pagination.
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $total_items  = count( $items );

        $this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

    /**
     * Render the title column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_title( $item ) {
        $edit_link = get_edit_post_link( $item->ID, 'raw' );
        $title     = $item->post_title;

        // Truncate if too long.
        $truncated = mb_strlen( $title ) > 50 ? mb_substr( $title, 0, 50 ) . '...' : $title;

        // Row actions.
        $actions = array(
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url( $edit_link ),
                __( 'Edit', 'bbab-core' )
            ),
        );

        // Add quick actions based on status.
        $status = get_post_meta( $item->ID, 'roadmap_status', true );

        if ( 'Idea' === $status ) {
            $start_adr_url = add_query_arg(
                array(
                    'action'   => 'bbab_start_adr',
                    'item_id'  => $item->ID,
                    '_wpnonce' => wp_create_nonce( 'bbab_start_adr_' . $item->ID ),
                ),
                admin_url( 'admin-post.php' )
            );
            $actions['start_adr'] = sprintf(
                '<a href="%s" style="color: #dba617;">%s</a>',
                esc_url( $start_adr_url ),
                __( 'Start ADR', 'bbab-core' )
            );
        }

        if ( 'Approved' === $status ) {
            $project_id = get_post_meta( $item->ID, 'related_project', true );
            if ( empty( $project_id ) ) {
                $create_project_url = add_query_arg(
                    array(
                        'action'   => 'bbab_create_project',
                        'item_id'  => $item->ID,
                        '_wpnonce' => wp_create_nonce( 'bbab_create_project_' . $item->ID ),
                    ),
                    admin_url( 'admin-post.php' )
                );
                $actions['create_project'] = sprintf(
                    '<a href="%s" style="color: #00a32a;">%s</a>',
                    esc_url( $create_project_url ),
                    __( 'Create Project', 'bbab-core' )
                );
            }
        }

        return sprintf(
            '<a href="%s"><strong title="%s">%s</strong></a>%s',
            esc_url( $edit_link ),
            esc_attr( $title ),
            esc_html( $truncated ),
            $this->row_actions( $actions )
        );
    }

    /**
     * Render the organization column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_organization( $item ) {
        $org_id = get_post_meta( $item->ID, 'organization', true );

        if ( empty( $org_id ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        if ( is_array( $org_id ) ) {
            $org_id = reset( $org_id );
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
     * Render the status column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_status( $item ) {
        $status = get_post_meta( $item->ID, 'roadmap_status', true );

        $css_class = 'status-' . sanitize_title( $status );

        return sprintf(
            '<span class="bbab-status-badge %s">%s</span>',
            esc_attr( $css_class ),
            esc_html( $status )
        );
    }

    /**
     * Render the priority column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_priority( $item ) {
        $priority = get_post_meta( $item->ID, 'priority', true );

        if ( empty( $priority ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        $css_class = 'priority-' . strtolower( $priority );

        return sprintf(
            '<span class="bbab-priority-badge %s">%s</span>',
            esc_attr( $css_class ),
            esc_html( $priority )
        );
    }

    /**
     * Render the category column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_category( $item ) {
        $category = get_post_meta( $item->ID, 'roadmap_category', true );

        if ( empty( $category ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        return esc_html( $category );
    }

    /**
     * Render the submitted by column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_submitted_by( $item ) {
        $submitted_by = get_post_meta( $item->ID, 'submitted_by', true );

        if ( empty( $submitted_by ) ) {
            // Check post author.
            $author = get_user_by( 'id', $item->post_author );
            if ( $author && user_can( $author->ID, 'administrator' ) ) {
                return '<span class="bbab-text-muted">' . esc_html__( 'Brad', 'bbab-core' ) . '</span>';
            }
            return '<span class="bbab-text-muted">—</span>';
        }

        if ( is_array( $submitted_by ) ) {
            $submitted_by = reset( $submitted_by );
        }

        $user = get_user_by( 'id', $submitted_by );
        if ( ! $user ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        // Check if admin (Brad) or client.
        if ( user_can( $user->ID, 'administrator' ) ) {
            return '<span class="bbab-text-muted">' . esc_html__( 'Brad', 'bbab-core' ) . '</span>';
        }

        return esc_html( $user->display_name );
    }

    /**
     * Render the project column.
     *
     * @param \WP_Post $item The roadmap item post.
     * @return string
     */
    public function column_project( $item ) {
        $project_id = get_post_meta( $item->ID, 'related_project', true );

        if ( empty( $project_id ) ) {
            return '<span class="bbab-text-muted">—</span>';
        }

        if ( is_array( $project_id ) ) {
            $project_id = reset( $project_id );
        }

        $ref = get_post_meta( $project_id, 'reference_number', true );

        return sprintf(
            '<a href="%s" class="bbab-ref-link">%s</a>',
            esc_url( get_edit_post_link( $project_id, 'raw' ) ),
            esc_html( $ref )
        );
    }

    /**
     * Default column renderer.
     *
     * @param \WP_Post $item        The roadmap item post.
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
        esc_html_e( 'No roadmap items found.', 'bbab-core' );
    }
}
