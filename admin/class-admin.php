<?php
/**
 * Admin-specific functionality orchestrator.
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
 * Class Admin
 *
 * Orchestrates all admin-specific functionality.
 *
 * @since 1.0.0
 */
class Admin {

    /**
     * The workbench instance.
     *
     * @var Workbench
     */
    private $workbench;

    /**
     * Constructor.
     *
     * Initializes admin components.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->workbench = new Workbench();
    }

    /**
     * Register all admin hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_hooks() {
        // Enqueue admin styles and scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Set transient for time entry linking (must run early, before validation snippet).
        add_action( 'load-post-new.php', array( $this, 'set_time_entry_link_transient' ) );

        // Pre-populate Pods relationship fields from URL parameters.
        add_action( 'admin_footer', array( $this, 'prepopulate_pods_fields' ) );

        // Note: Simulation hooks (filter and frontend bar) are registered in BBAB_Core
        // so they work on both admin and frontend.

        // Fallback: Set time entry relationship meta after post is inserted.
        add_action( 'save_post_time_entry', array( $this, 'maybe_set_time_entry_relationship' ), 5, 3 );

        // Filter admin list queries for our custom filter parameters.
        add_action( 'pre_get_posts', array( $this, 'filter_admin_list_queries' ) );

        // Register workbench hooks.
        $this->workbench->register_hooks();
    }

    /**
     * Filter admin list queries based on our custom parameters.
     *
     * Handles bbab_project_id and bbab_sr_id query parameters to filter
     * milestones and time entries in the admin list.
     *
     * @since 1.0.0
     * @param \WP_Query $query The query object.
     * @return void
     */
    public function filter_admin_list_queries( $query ) {
        // Only run on admin, main query.
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type = $query->get( 'post_type' );

        // Filter milestones by project.
        if ( 'milestone' === $post_type && ! empty( $_GET['bbab_project_id'] ) ) {
            $project_id = absint( $_GET['bbab_project_id'] );

            $meta_query = $query->get( 'meta_query' ) ?: array();
            $meta_query[] = array(
                'key'     => 'related_project',
                'value'   => $project_id,
                'compare' => '=',
            );
            $query->set( 'meta_query', $meta_query );
        }

        // Filter time entries by service request.
        if ( 'time_entry' === $post_type && ! empty( $_GET['bbab_sr_id'] ) ) {
            $sr_id = absint( $_GET['bbab_sr_id'] );

            $meta_query = $query->get( 'meta_query' ) ?: array();
            $meta_query[] = array(
                'key'     => 'related_service_request',
                'value'   => $sr_id,
                'compare' => '=',
            );
            $query->set( 'meta_query', $meta_query );
        }

        // Filter time entries by project (includes direct project TEs and milestone TEs).
        if ( 'time_entry' === $post_type && ! empty( $_GET['bbab_project_id'] ) ) {
            $project_id = absint( $_GET['bbab_project_id'] );

            // Get milestone IDs for this project.
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

            // Build OR query: TEs linked to project OR linked to any milestone of project.
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key'     => 'related_project',
                    'value'   => $project_id,
                    'compare' => '=',
                ),
            );

            if ( ! empty( $milestones ) ) {
                $meta_query[] = array(
                    'key'     => 'related_milestone',
                    'value'   => $milestones,
                    'compare' => 'IN',
                );
            }

            $query->set( 'meta_query', $meta_query );
        }

        // Filter invoices by project (includes direct project invoices and milestone invoices).
        if ( 'invoice' === $post_type && ! empty( $_GET['bbab_project_id'] ) ) {
            $project_id = absint( $_GET['bbab_project_id'] );

            // Get milestone IDs for this project.
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

            // Build OR query: Invoices linked to project OR linked to any milestone of project.
            $meta_query = array(
                'relation' => 'OR',
                array(
                    'key'     => 'related_project',
                    'value'   => $project_id,
                    'compare' => '=',
                ),
            );

            if ( ! empty( $milestones ) ) {
                $meta_query[] = array(
                    'key'     => 'related_milestone',
                    'value'   => $milestones,
                    'compare' => 'IN',
                );
            }

            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Enqueue admin styles.
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_styles( $hook_suffix ) {
        // Only load on our plugin pages.
        if ( ! $this->is_plugin_page( $hook_suffix ) ) {
            return;
        }

        wp_enqueue_style(
            'bbab-workbench',
            BBAB_CORE_URL . 'admin/css/workbench.css',
            array(),
            BBAB_CORE_VERSION
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_scripts( $hook_suffix ) {
        // Only load on our plugin pages.
        if ( ! $this->is_plugin_page( $hook_suffix ) ) {
            return;
        }

        wp_enqueue_script(
            'bbab-workbench',
            BBAB_CORE_URL . 'admin/js/workbench.js',
            array( 'jquery' ),
            BBAB_CORE_VERSION,
            true
        );

        // Localize script with data we'll need.
        wp_localize_script(
            'bbab-workbench',
            'bbabWorkbench',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'bbab_workbench_nonce' ),
            )
        );
    }

    /**
     * Check if current page is a plugin page.
     *
     * @since 1.0.0
     * @param string $hook_suffix The current admin page hook suffix.
     * @return bool
     */
    private function is_plugin_page( $hook_suffix ) {
        $plugin_pages = array(
            'toplevel_page_bbab-workbench',
            'brads-workbench_page_bbab-projects',
            'brads-workbench_page_bbab-requests',
            'brads-workbench_page_bbab-invoices',
            'brads-workbench_page_bbab-tasks',
            'brads-workbench_page_bbab-roadmap',
        );

        // Also check for alternate hook suffix format.
        $alt_pages = array(
            'brad-s-workbench_page_bbab-projects',
            'brad-s-workbench_page_bbab-requests',
            'brad-s-workbench_page_bbab-invoices',
            'brad-s-workbench_page_bbab-tasks',
            'brad-s-workbench_page_bbab-roadmap',
        );

        return in_array( $hook_suffix, $plugin_pages, true ) || in_array( $hook_suffix, $alt_pages, true );
    }

    /**
     * Filter callback to override org ID when simulation is active.
     *
     * This hooks into the bbab_simulated_org_id filter that's called
     * by bbab_get_user_org_id() in the helper snippet.
     *
     * @since 1.0.0
     * @param int|false $org_id  Current org ID (false = no override).
     * @param int       $user_id The user ID being looked up.
     * @return int|false Organization ID to simulate, or false for no override.
     */
    public function maybe_override_org_id( $org_id, $user_id ) {
        // Only admins can simulate.
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Check for simulation transient.
        $simulated_org = get_transient( 'bbab_simulating_org_' . get_current_user_id() );

        if ( $simulated_org ) {
            return absint( $simulated_org );
        }

        return false;
    }

    /**
     * Render the simulation indicator bar on the frontend.
     *
     * Shows a floating bar when an admin is viewing the site as a client.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_simulation_indicator() {
        // Only for admins.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if simulating.
        $simulated_org = get_transient( 'bbab_simulating_org_' . get_current_user_id() );

        if ( ! $simulated_org ) {
            return;
        }

        $org_name = get_the_title( $simulated_org );
        $exit_url = wp_nonce_url(
            admin_url( 'admin.php?page=bbab-workbench&bbab_stop_simulation=1' ),
            'bbab_simulation'
        );
        ?>
        <div id="bbab-simulation-bar" style="
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 12px 20px;
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
        ">
            <span style="display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-visibility" style="font-size: 18px;"></span>
                <strong><?php esc_html_e( 'Simulation Mode', 'bbab-core' ); ?></strong>
                <?php
                printf(
                    /* translators: %s: organization name */
                    esc_html__( 'Viewing as: %s', 'bbab-core' ),
                    '<strong>' . esc_html( $org_name ) . '</strong>'
                );
                ?>
            </span>
            <a href="<?php echo esc_url( $exit_url ); ?>" style="
                background: rgba(255,255,255,0.2);
                color: #fff;
                padding: 6px 16px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
                transition: background 0.2s;
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                <?php esc_html_e( 'Exit Simulation', 'bbab-core' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-workbench' ) ); ?>" style="
                background: #fff;
                color: #667eea;
                padding: 6px 16px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
                transition: opacity 0.2s;
            " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                <?php esc_html_e( 'Back to Workbench', 'bbab-core' ); ?>
            </a>
        </div>
        <style>
            /* Add padding to body so content isn't hidden behind the bar */
            body { padding-bottom: 60px !important; }
        </style>
        <?php
    }

    /**
     * Set transient for time entry linking when using our row action URLs.
     *
     * This allows the validation snippet (1863) to recognize that we're
     * creating a time entry from a valid source (SR, Project, or Milestone).
     *
     * @since 1.0.0
     * @return void
     */
    public function set_time_entry_link_transient() {
        global $typenow;

        // Only for time_entry post type.
        if ( 'time_entry' !== $typenow ) {
            return;
        }

        $user_id = get_current_user_id();

        // Check for SR link.
        if ( ! empty( $_GET['related_service_request'] ) ) {
            $sr_id = absint( $_GET['related_service_request'] );
            if ( $sr_id > 0 ) {
                // Set transient that the validation snippet looks for.
                set_transient( 'bbab_pending_sr_link_' . $user_id, $sr_id, HOUR_IN_SECONDS );
            }
        }

        // Check for Project link.
        if ( ! empty( $_GET['related_project'] ) ) {
            $project_id = absint( $_GET['related_project'] );
            if ( $project_id > 0 ) {
                // Set transient that the validation snippet looks for.
                set_transient( 'bbab_pending_project_link_' . $user_id, $project_id, HOUR_IN_SECONDS );
            }
        }

        // Check for Milestone link.
        if ( ! empty( $_GET['related_milestone'] ) ) {
            $milestone_id = absint( $_GET['related_milestone'] );
            if ( $milestone_id > 0 ) {
                // Set transient that the validation snippet looks for.
                set_transient( 'bbab_pending_milestone_link_' . $user_id, $milestone_id, HOUR_IN_SECONDS );
            }
        }
    }

    /**
     * Set time entry relationship from our hidden field or transient.
     *
     * This ensures the relationship is saved even if Pods Select2 pre-population
     * didn't work. Runs early (priority 5) to set the meta before validation.
     *
     * @since 1.0.0
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     * @return void
     */
    public function maybe_set_time_entry_relationship( $post_id, $post, $update ) {
        // Only on new posts.
        if ( $update ) {
            return;
        }

        $user_id = get_current_user_id();

        // Try to get values from our hidden fields first.
        $sr_id      = 0;
        $project_id = 0;
        $milestone_id = 0;

        // Check hidden fields (with nonce verification).
        if ( isset( $_POST['bbab_prepopulate_nonce'] ) &&
             wp_verify_nonce( $_POST['bbab_prepopulate_nonce'], 'bbab_prepopulate_te' ) ) {
            $sr_id      = isset( $_POST['bbab_prepopulate_sr'] ) ? absint( $_POST['bbab_prepopulate_sr'] ) : 0;
            $project_id = isset( $_POST['bbab_prepopulate_project'] ) ? absint( $_POST['bbab_prepopulate_project'] ) : 0;
        }

        // Fall back to transients if hidden fields weren't set.
        if ( $sr_id === 0 ) {
            $sr_id = get_transient( 'bbab_pending_sr_link_' . $user_id );
            $sr_id = $sr_id ? absint( $sr_id ) : 0;
        }
        if ( $project_id === 0 ) {
            $project_id = get_transient( 'bbab_pending_project_link_' . $user_id );
            $project_id = $project_id ? absint( $project_id ) : 0;
        }
        if ( $milestone_id === 0 ) {
            $milestone_id = get_transient( 'bbab_pending_milestone_link_' . $user_id );
            $milestone_id = $milestone_id ? absint( $milestone_id ) : 0;
        }

        // Set SR relationship if provided and not already set.
        if ( $sr_id > 0 ) {
            $existing = get_post_meta( $post_id, 'related_service_request', true );
            if ( empty( $existing ) ) {
                update_post_meta( $post_id, 'related_service_request', $sr_id );
            }
        }

        // Set Project relationship if provided and not already set.
        if ( $project_id > 0 ) {
            $existing = get_post_meta( $post_id, 'related_project', true );
            if ( empty( $existing ) ) {
                update_post_meta( $post_id, 'related_project', $project_id );
            }
        }

        // Set Milestone relationship if provided and not already set.
        if ( $milestone_id > 0 ) {
            $existing = get_post_meta( $post_id, 'related_milestone', true );
            if ( empty( $existing ) ) {
                update_post_meta( $post_id, 'related_milestone', $milestone_id );
            }
        }

        // Clean up transients after use.
        delete_transient( 'bbab_pending_sr_link_' . $user_id );
        delete_transient( 'bbab_pending_project_link_' . $user_id );
        delete_transient( 'bbab_pending_milestone_link_' . $user_id );
    }

    /**
     * Pre-populate Pods relationship fields from URL parameters.
     *
     * Handles cases where we link to new Time Entry with a pre-selected
     * Service Request or Project.
     *
     * Uses MutationObserver to reliably catch when Pods initializes Select2
     * fields, then sets the value using the proper Select2 API.
     *
     * @since 1.0.0
     * @return void
     */
    public function prepopulate_pods_fields() {
        global $pagenow, $typenow;

        // Only run on post-new.php for time_entry.
        if ( 'post-new.php' !== $pagenow || 'time_entry' !== $typenow ) {
            return;
        }

        // Check for SR or Project ID in URL.
        $sr_id      = isset( $_GET['related_service_request'] ) ? absint( $_GET['related_service_request'] ) : 0;
        $project_id = isset( $_GET['related_project'] ) ? absint( $_GET['related_project'] ) : 0;

        if ( ! $sr_id && ! $project_id ) {
            return;
        }

        // Get post info for the related item.
        if ( $sr_id ) {
            $post = get_post( $sr_id );
            if ( ! $post || 'service_request' !== $post->post_type ) {
                return;
            }
            $field_name = 'related_service_request';
            $post_id    = $sr_id;
            $ref_number = get_post_meta( $sr_id, 'reference_number', true );
            $subject    = get_post_meta( $sr_id, 'subject', true ) ?: $post->post_title;
            $label      = $ref_number . ' - ' . $subject;
        } else {
            $post = get_post( $project_id );
            if ( ! $post || 'project' !== $post->post_type ) {
                return;
            }
            $field_name  = 'related_project';
            $post_id     = $project_id;
            $ref_number  = get_post_meta( $project_id, 'reference_number', true );
            $project_name = get_post_meta( $project_id, 'project_name', true ) ?: $post->post_title;
            $label       = $ref_number . ' - ' . $project_name;
        }
        // Create nonce for the hidden field.
        $nonce = wp_create_nonce( 'bbab_prepopulate_te' );
        ?>
        <script type="text/javascript">
        (function($) {
            var fieldName = <?php echo wp_json_encode( $field_name ); ?>;
            var postId = <?php echo wp_json_encode( (string) $post_id ); ?>;
            var label = <?php echo wp_json_encode( $label ); ?>;
            var isSR = <?php echo wp_json_encode( $sr_id > 0 ); ?>;
            var nonce = <?php echo wp_json_encode( $nonce ); ?>;
            var populated = false;
            var hiddenFieldsAdded = false;

            /**
             * Add hidden fields to the form as a fallback.
             * These will be read by PHP if Select2 pre-population doesn't work.
             */
            function addHiddenFields() {
                if (hiddenFieldsAdded) return;

                var $form = $('form#post');
                if (!$form.length) return;

                // Add our hidden fields.
                if (isSR) {
                    $form.append('<input type="hidden" name="bbab_prepopulate_sr" value="' + postId + '" />');
                } else {
                    $form.append('<input type="hidden" name="bbab_prepopulate_project" value="' + postId + '" />');
                }
                $form.append('<input type="hidden" name="bbab_prepopulate_nonce" value="' + nonce + '" />');

                hiddenFieldsAdded = true;
                console.log('BBAB: Added hidden fallback fields for ' + fieldName);
            }

            /**
             * Try to set the Select2 field value.
             */
            function setSelect2Value($select) {
                if (populated) return true;

                // Make sure it's Select2 initialized.
                if (!$select.hasClass('select2-hidden-accessible')) {
                    return false;
                }

                // Check if already has our value.
                var currentVal = $select.val();
                if (currentVal === postId || (Array.isArray(currentVal) && currentVal.indexOf(postId) !== -1)) {
                    populated = true;
                    return true;
                }

                // Create and append the option.
                var newOption = new Option(label, postId, true, true);
                $select.append(newOption);
                $select.val(postId).trigger('change');

                populated = true;
                console.log('BBAB: Pre-populated Select2 ' + fieldName + ' with ' + postId);
                return true;
            }

            /**
             * Find the target select field.
             */
            function findSelectField() {
                // Try various Pods naming conventions.
                var selectors = [
                    'select[name="pods_meta_' + fieldName + '"]',
                    'select[name="' + fieldName + '"]',
                    'select[data-name="' + fieldName + '"]',
                    'select#pods-form-ui-' + fieldName.replace(/_/g, '-'),
                    '.pods-field-' + fieldName.replace(/_/g, '-') + ' select'
                ];

                for (var i = 0; i < selectors.length; i++) {
                    var $field = $(selectors[i]);
                    if ($field.length) {
                        return $field;
                    }
                }
                return null;
            }

            /**
             * Main attempt function.
             */
            function attemptPrepopulation() {
                if (populated) return true;

                var $select = findSelectField();
                if ($select && $select.length) {
                    return setSelect2Value($select);
                }
                return false;
            }

            // Use MutationObserver to watch for when Pods adds the Select2 classes.
            function setupObserver() {
                var observer = new MutationObserver(function(mutations) {
                    if (populated) {
                        observer.disconnect();
                        return;
                    }

                    // Check if our field now exists and is initialized.
                    if (attemptPrepopulation()) {
                        observer.disconnect();
                    }
                });

                // Observe the entire document for changes.
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });

                // Disconnect after 10 seconds regardless.
                setTimeout(function() {
                    observer.disconnect();
                }, 10000);
            }

            // Run when DOM is ready.
            $(document).ready(function() {
                // Always add hidden fields as fallback.
                addHiddenFields();

                // Try to pre-populate Select2 immediately.
                if (!attemptPrepopulation()) {
                    // Set up observer for async initialization.
                    setupObserver();

                    // Also try on intervals as backup.
                    var attempts = 0;
                    var interval = setInterval(function() {
                        attempts++;
                        if (attemptPrepopulation() || attempts > 20) {
                            clearInterval(interval);
                        }
                    }, 250);
                }
            });

            // Also try when window fully loads.
            $(window).on('load', function() {
                if (!populated) {
                    attemptPrepopulation();
                }
            });

        })(jQuery);
        </script>
        <?php
    }
}
