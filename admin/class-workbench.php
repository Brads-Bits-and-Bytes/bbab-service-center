<?php
/**
 * Brad's Workbench - Main Dashboard Page.
 *
 * @package BBAB\Core\Admin
 * @since   1.0.0
 */

namespace BBAB\Core\Admin;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class Workbench
 *
 * Handles the main Workbench dashboard page.
 *
 * @since 1.0.0
 */
class Workbench {

    /**
     * Cache instance.
     *
     * @var Cache
     */
    private $cache;

    /**
     * Projects sub-page instance.
     *
     * @var Workbench_Projects
     */
    private $projects_page;

    /**
     * Requests sub-page instance.
     *
     * @var Workbench_Requests
     */
    private $requests_page;

    /**
     * Invoices sub-page instance.
     *
     * @var Workbench_Invoices
     */
    private $invoices_page;

    /**
     * Tasks sub-page instance.
     *
     * @var Workbench_Tasks
     */
    private $tasks_page;

    /**
     * Roadmap sub-page instance.
     *
     * @var Workbench_Roadmap
     */
    private $roadmap_page;

    /**
     * Status sort order for Service Requests.
     * Lower number = higher priority in display.
     *
     * @var array
     */
    private $sr_status_order = array(
        'New'               => 1,
        'Acknowledged'      => 2,
        'In Progress'       => 3,
        'Waiting on Client' => 4,
        'On Hold'           => 5,
    );

    /**
     * Status sort order for Projects.
     *
     * @var array
     */
    private $project_status_order = array(
        'Active'            => 1,
        'Waiting on Client' => 2,
        'On Hold'           => 3,
    );

    /**
     * Status sort order for Invoices.
     *
     * @var array
     */
    private $invoice_status_order = array(
        'Draft'    => 1,
        'Pending'  => 2,
        'Partial'  => 3,
        'Overdue'  => 4,
    );

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->cache         = new Cache();
        $this->projects_page = new Workbench_Projects();
        $this->requests_page = new Workbench_Requests();
        $this->invoices_page = new Workbench_Invoices();
        $this->tasks_page    = new Workbench_Tasks();
        $this->roadmap_page  = new Workbench_Roadmap();
    }

    /**
     * Register hooks for the workbench.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
    }

    /**
     * Register the admin menu pages.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_menu_pages() {
        // Main menu page.
        add_menu_page(
            __( "Brad's Workbench", 'bbab-core' ),
            __( "Brad's Workbench", 'bbab-core' ),
            'manage_options',
            'bbab-workbench',
            array( $this, 'render_main_page' ),
            'dashicons-desktop',
            2 // Position: right below Dashboard.
        );

        // Rename the auto-created submenu item.
        add_submenu_page(
            'bbab-workbench',
            __( "Brad's Workbench", 'bbab-core' ),
            __( 'Dashboard', 'bbab-core' ),
            'manage_options',
            'bbab-workbench',
            array( $this, 'render_main_page' )
        );

        // Projects sub-page.
        add_submenu_page(
            'bbab-workbench',
            __( 'Projects', 'bbab-core' ),
            __( 'Projects', 'bbab-core' ),
            'manage_options',
            'bbab-projects',
            array( $this->projects_page, 'render_page' )
        );

        // Service Requests sub-page.
        add_submenu_page(
            'bbab-workbench',
            __( 'Service Requests', 'bbab-core' ),
            __( 'Service Requests', 'bbab-core' ),
            'manage_options',
            'bbab-requests',
            array( $this->requests_page, 'render_page' )
        );

        // Invoices sub-page.
        add_submenu_page(
            'bbab-workbench',
            __( 'Invoices', 'bbab-core' ),
            __( 'Invoices', 'bbab-core' ),
            'manage_options',
            'bbab-invoices',
            array( $this->invoices_page, 'render_page' )
        );

        // Client Tasks sub-page.
        add_submenu_page(
            'bbab-workbench',
            __( 'Client Tasks', 'bbab-core' ),
            __( 'Client Tasks', 'bbab-core' ),
            'manage_options',
            'bbab-tasks',
            array( $this->tasks_page, 'render_page' )
        );

        // Roadmap Items sub-page.
        add_submenu_page(
            'bbab-workbench',
            __( 'Roadmap Items', 'bbab-core' ),
            __( 'Roadmap Items', 'bbab-core' ),
            'manage_options',
            'bbab-roadmap',
            array( $this->roadmap_page, 'render_page' )
        );
    }

    /**
     * Render the main workbench page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_main_page() {
        // Security check.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bbab-core' ) );
        }

        // Handle simulation start/stop.
        $this->handle_simulation_actions();

        // Get data for the boxes.
        $service_requests = $this->get_open_service_requests( 10 );
        $projects         = $this->get_active_projects( 10 );
        $invoices         = $this->get_pending_invoices( 10 );
        $client_tasks     = $this->get_pending_client_tasks( 10 );
        $roadmap_items    = $this->get_active_roadmap_items( 10 );

        // Get total counts (for "View All" badges).
        $sr_total_count      = $this->get_open_service_request_count();
        $project_total_count = $this->get_active_project_count();
        $invoice_total_count = $this->get_pending_invoice_count();
        $task_total_count    = $this->get_pending_client_task_count();
        $roadmap_total_count = $this->get_active_roadmap_item_count();

        // Get organizations for simulation dropdown.
        $organizations = $this->get_all_organizations();

        // Get current simulation state.
        $simulating_org_id   = $this->get_simulating_org_id();
        $simulating_org_name = '';
        if ( $simulating_org_id ) {
            $simulating_org_name = get_the_title( $simulating_org_id );
        }

        // Load the template.
        include BBAB_CORE_PATH . 'admin/partials/workbench-main.php';
    }

    /**
     * Handle simulation start/stop actions from URL parameters.
     *
     * @since 1.0.0
     * @return void
     */
    private function handle_simulation_actions() {
        $user_id = get_current_user_id();

        // Stop simulation.
        if ( isset( $_GET['bbab_stop_simulation'] ) && $_GET['bbab_stop_simulation'] ) {
            // Verify nonce.
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bbab_simulation' ) ) {
                return;
            }

            delete_transient( 'bbab_simulating_org_' . $user_id );

            // Redirect to remove the query args.
            wp_safe_redirect( admin_url( 'admin.php?page=bbab-workbench' ) );
            exit;
        }

        // Start simulation.
        if ( isset( $_GET['bbab_simulate_org'] ) && ! empty( $_GET['bbab_simulate_org'] ) ) {
            // Verify nonce (wp_nonce_url uses _wpnonce).
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'bbab_simulation' ) ) {
                return;
            }

            $org_id = absint( $_GET['bbab_simulate_org'] );

            // Verify the org exists.
            $org_post = get_post( $org_id );
            if ( $org_post && 'client_organization' === $org_post->post_type ) {
                // Set transient for 8 hours.
                set_transient( 'bbab_simulating_org_' . $user_id, $org_id, 8 * HOUR_IN_SECONDS );
            }

            // Redirect to remove the query args.
            wp_safe_redirect( admin_url( 'admin.php?page=bbab-workbench' ) );
            exit;
        }
    }

    /**
     * Get the currently simulated organization ID for the current user.
     *
     * @since 1.0.0
     * @return int Organization ID or 0 if not simulating.
     */
    public function get_simulating_org_id() {
        $user_id = get_current_user_id();
        $org_id  = get_transient( 'bbab_simulating_org_' . $user_id );

        return $org_id ? absint( $org_id ) : 0;
    }

    /**
     * Get all client organizations for the simulation dropdown.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_all_organizations() {
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
            $result[]  = array(
                'id'        => $org->ID,
                'name'      => $org->post_title,
                'shortcode' => $shortcode,
            );
        }

        return $result;
    }

    /**
     * Get open service requests for display.
     *
     * @since 1.0.0
     * @param int $limit Number of items to return.
     * @return array
     */
    public function get_open_service_requests( $limit = 10 ) {
        $cache_key = 'open_srs_' . $limit;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'service_request',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Get all, then sort and limit.
            'meta_query'     => array(
                array(
                    'key'     => 'request_status',
                    'value'   => array( 'Completed', 'Cancelled' ),
                    'compare' => 'NOT IN',
                ),
            ),
        ) );

        // Sort by status priority, then by date.
        usort( $results, function( $a, $b ) {
            $status_a = get_post_meta( $a->ID, 'request_status', true );
            $status_b = get_post_meta( $b->ID, 'request_status', true );

            $order_a = isset( $this->sr_status_order[ $status_a ] ) ? $this->sr_status_order[ $status_a ] : 99;
            $order_b = isset( $this->sr_status_order[ $status_b ] ) ? $this->sr_status_order[ $status_b ] : 99;

            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }

            // Same status, sort by date (newest first).
            return strtotime( $b->post_date ) - strtotime( $a->post_date );
        } );

        // Limit results.
        $results = array_slice( $results, 0, $limit );

        $this->cache->set( $cache_key, $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Get total count of open service requests.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_open_service_request_count() {
        $cache_key = 'open_srs_count';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'service_request',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'request_status',
                    'value'   => array( 'Completed', 'Cancelled' ),
                    'compare' => 'NOT IN',
                ),
            ),
        ) );

        $count = count( $results );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get active projects for display.
     *
     * @since 1.0.0
     * @param int $limit Number of items to return.
     * @return array
     */
    public function get_active_projects( $limit = 10 ) {
        $cache_key = 'active_projects_' . $limit;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'project_status',
                    'value'   => array( 'Active', 'Waiting on Client', 'On Hold' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        // Sort by status priority, then by date.
        usort( $results, function( $a, $b ) {
            $status_a = get_post_meta( $a->ID, 'project_status', true );
            $status_b = get_post_meta( $b->ID, 'project_status', true );

            $order_a = isset( $this->project_status_order[ $status_a ] ) ? $this->project_status_order[ $status_a ] : 99;
            $order_b = isset( $this->project_status_order[ $status_b ] ) ? $this->project_status_order[ $status_b ] : 99;

            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }

            return strtotime( $b->post_date ) - strtotime( $a->post_date );
        } );

        $results = array_slice( $results, 0, $limit );

        $this->cache->set( $cache_key, $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Get total count of active projects.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_active_project_count() {
        $cache_key = 'active_projects_count';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'project_status',
                    'value'   => array( 'Active', 'Waiting on Client', 'On Hold' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        $count = count( $results );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get pending invoices for display.
     *
     * @since 1.0.0
     * @param int $limit Number of items to return.
     * @return array
     */
    public function get_pending_invoices( $limit = 10 ) {
        $cache_key = 'pending_invoices_' . $limit;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'invoice',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'invoice_status',
                    'value'   => array( 'Paid', 'Cancelled' ),
                    'compare' => 'NOT IN',
                ),
            ),
        ) );

        // Sort by status priority (overdue first), then by due date.
        usort( $results, function( $a, $b ) {
            $status_a = get_post_meta( $a->ID, 'invoice_status', true );
            $status_b = get_post_meta( $b->ID, 'invoice_status', true );

            $order_a = isset( $this->invoice_status_order[ $status_a ] ) ? $this->invoice_status_order[ $status_a ] : 99;
            $order_b = isset( $this->invoice_status_order[ $status_b ] ) ? $this->invoice_status_order[ $status_b ] : 99;

            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }

            // Same status, sort by due date (earliest first).
            $due_a = get_post_meta( $a->ID, 'due_date', true );
            $due_b = get_post_meta( $b->ID, 'due_date', true );

            return strtotime( $due_a ) - strtotime( $due_b );
        } );

        $results = array_slice( $results, 0, $limit );

        $this->cache->set( $cache_key, $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Get total count of pending invoices.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_pending_invoice_count() {
        $cache_key = 'pending_invoices_count';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'invoice',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'invoice_status',
                    'value'   => array( 'Paid', 'Cancelled' ),
                    'compare' => 'NOT IN',
                ),
            ),
        ) );

        $count = count( $results );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get organization shortcode for a post.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return string Organization shortcode or empty string.
     */
    public function get_org_shortcode( $post_id ) {
        $org_id = get_post_meta( $post_id, 'organization', true );

        if ( empty( $org_id ) ) {
            return '';
        }

        // Handle if it's an array (Pods relationship field).
        if ( is_array( $org_id ) ) {
            $org_id = reset( $org_id );
        }

        $shortcode = get_post_meta( $org_id, 'organization_shortcode', true );

        return $shortcode ? $shortcode : '';
    }

    /**
     * Get time entry count for a service request.
     *
     * @since 1.0.0
     * @param int $sr_id Service Request ID.
     * @return int
     */
    public function get_sr_time_entry_count( $sr_id ) {
        $cache_key = 'sr_te_count_' . $sr_id;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $entries = get_posts( array(
            'post_type'      => 'time_entry',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'related_service_request',
                    'value'   => $sr_id,
                    'compare' => '=',
                ),
            ),
        ) );

        $count = count( $entries );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get time entry count for a project (including all milestones).
     *
     * @since 1.0.0
     * @param int $project_id Project ID.
     * @return int
     */
    public function get_project_time_entry_count( $project_id ) {
        $cache_key = 'project_te_count_' . $project_id;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Get TEs directly linked to project.
        $direct_entries = get_posts( array(
            'post_type'      => 'time_entry',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'related_project',
                    'value'   => $project_id,
                    'compare' => '=',
                ),
            ),
        ) );

        // Get TEs linked to milestones of this project.
        $milestones = $this->get_project_milestones( $project_id );
        $milestone_entries = array();

        foreach ( $milestones as $milestone_id ) {
            $ms_entries = get_posts( array(
                'post_type'      => 'time_entry',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => 'related_milestone',
                        'value'   => $milestone_id,
                        'compare' => '=',
                    ),
                ),
            ) );
            $milestone_entries = array_merge( $milestone_entries, $ms_entries );
        }

        // Combine and dedupe.
        $all_entries = array_unique( array_merge( $direct_entries, $milestone_entries ) );
        $count = count( $all_entries );

        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get milestone count for a project.
     *
     * @since 1.0.0
     * @param int $project_id Project ID.
     * @return int
     */
    public function get_project_milestone_count( $project_id ) {
        $milestones = $this->get_project_milestones( $project_id );
        return count( $milestones );
    }

    /**
     * Get milestone IDs for a project.
     *
     * @since 1.0.0
     * @param int $project_id Project ID.
     * @return array Array of milestone IDs.
     */
    public function get_project_milestones( $project_id ) {
        $cache_key = 'project_milestones_' . $project_id;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $milestones = get_posts( array(
            'post_type'      => 'milestone',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'related_project',
                    'value'   => $project_id,
                    'compare' => '=',
                ),
            ),
        ) );

        $this->cache->set( $cache_key, $milestones, HOUR_IN_SECONDS );

        return $milestones;
    }

    /**
     * Render a status badge.
     *
     * @since 1.0.0
     * @param string $status The status value.
     * @param string $type   The CPT type (sr, project, invoice).
     * @return string HTML for the status badge.
     */
    public function render_status_badge( $status, $type = '' ) {
        if ( empty( $status ) ) {
            return '';
        }

        // Convert status to CSS class.
        $css_class = 'status-' . sanitize_title( $status );

        return sprintf(
            '<span class="bbab-status-badge %s">%s</span>',
            esc_attr( $css_class ),
            esc_html( $status )
        );
    }

    /**
     * Format currency amount.
     *
     * @since 1.0.0
     * @param float $amount The amount to format.
     * @return string Formatted currency string.
     */
    public function format_currency( $amount ) {
        return '$' . number_format( (float) $amount, 2 );
    }

    /**
     * Get edit link for a post.
     *
     * @since 1.0.0
     * @param int $post_id Post ID.
     * @return string Edit URL.
     */
    public function get_edit_link( $post_id ) {
        return get_edit_post_link( $post_id, 'raw' );
    }

    /**
     * Get filtered admin list URL for milestones by project.
     *
     * @since 1.0.0
     * @param int $project_id Project ID.
     * @return string Admin list URL with filter.
     */
    public function get_milestones_by_project_url( $project_id ) {
        return add_query_arg(
            array(
                'post_type'       => 'milestone',
                'bbab_project_id' => absint( $project_id ),
            ),
            admin_url( 'edit.php' )
        );
    }

    /**
     * Get filtered admin list URL for time entries by service request.
     *
     * @since 1.0.0
     * @param int $sr_id Service Request ID.
     * @return string Admin list URL with filter.
     */
    public function get_time_entries_by_sr_url( $sr_id ) {
        return add_query_arg(
            array(
                'post_type'   => 'time_entry',
                'bbab_sr_id'  => absint( $sr_id ),
            ),
            admin_url( 'edit.php' )
        );
    }

    /**
     * Get filtered admin list URL for time entries by project.
     *
     * @since 1.0.0
     * @param int $project_id Project ID.
     * @return string Admin list URL with filter.
     */
    public function get_time_entries_by_project_url( $project_id ) {
        return add_query_arg(
            array(
                'post_type'       => 'time_entry',
                'bbab_project_id' => absint( $project_id ),
            ),
            admin_url( 'edit.php' )
        );
    }

    /**
     * Get pending client tasks for display.
     *
     * @since 1.0.0
     * @param int $limit Number of items to return.
     * @return array
     */
    public function get_pending_client_tasks( $limit = 10 ) {
        $cache_key = 'pending_tasks_' . $limit;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'client_task',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'task_status',
                    'value'   => 'Pending',
                    'compare' => '=',
                ),
            ),
        ) );

        // Sort by due date (soonest first, then overdue, then no date).
        usort( $results, function( $a, $b ) {
            $due_a = get_post_meta( $a->ID, 'due_date', true );
            $due_b = get_post_meta( $b->ID, 'due_date', true );

            // If neither has a due date, sort by post date.
            if ( empty( $due_a ) && empty( $due_b ) ) {
                return strtotime( $b->post_date ) - strtotime( $a->post_date );
            }

            // Items with due date come before items without.
            if ( empty( $due_a ) ) {
                return 1;
            }
            if ( empty( $due_b ) ) {
                return -1;
            }

            // Sort by due date (earliest first).
            return strtotime( $due_a ) - strtotime( $due_b );
        } );

        $results = array_slice( $results, 0, $limit );

        $this->cache->set( $cache_key, $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Get total count of pending client tasks.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_pending_client_task_count() {
        $cache_key = 'pending_tasks_count';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'client_task',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'task_status',
                    'value'   => 'Pending',
                    'compare' => '=',
                ),
            ),
        ) );

        $count = count( $results );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get organization shortcode for a client task.
     *
     * Client tasks use Advanced Relationship which stores data in wp_podsrel
     * table, not postmeta. Need to query directly.
     *
     * @since 1.0.0
     * @param int $task_id Task ID.
     * @return string Organization shortcode or empty string.
     */
    public function get_task_org_shortcode( $task_id ) {
        global $wpdb;

        // Field ID 1320 is the 'organization' field for client_task.
        // Query wp_podsrel table directly since Advanced Relationship.
        $org_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT related_item_id FROM {$wpdb->prefix}podsrel
             WHERE item_id = %d AND field_id = 1320",
            $task_id
        ) );

        if ( empty( $org_id ) ) {
            return '';
        }

        $shortcode = get_post_meta( $org_id, 'organization_shortcode', true );

        return $shortcode ? $shortcode : '';
    }

    /**
     * Get active roadmap items for display.
     *
     * @since 1.0.0
     * @param int $limit Number of items to return.
     * @return array
     */
    public function get_active_roadmap_items( $limit = 10 ) {
        $cache_key = 'active_roadmap_' . $limit;
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        // Active statuses: Idea, ADR In Progress, Proposed.
        $results = get_posts( array(
            'post_type'      => 'roadmap_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => 'roadmap_status',
                    'value'   => array( 'Idea', 'ADR In Progress', 'Proposed' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        // Priority order for sorting.
        $priority_order = array(
            'High'   => 1,
            'Medium' => 2,
            'Low'    => 3,
        );

        // Status order for sorting.
        $status_order = array(
            'Proposed'        => 1, // Ready for review first.
            'ADR In Progress' => 2,
            'Idea'            => 3,
        );

        // Sort by status priority, then by priority level, then by date.
        usort( $results, function( $a, $b ) use ( $status_order, $priority_order ) {
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

        $results = array_slice( $results, 0, $limit );

        $this->cache->set( $cache_key, $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Get total count of active roadmap items.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_active_roadmap_item_count() {
        $cache_key = 'active_roadmap_count';
        $cached    = $this->cache->get( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $results = get_posts( array(
            'post_type'      => 'roadmap_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'roadmap_status',
                    'value'   => array( 'Idea', 'ADR In Progress', 'Proposed' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        $count = count( $results );
        $this->cache->set( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }
}
