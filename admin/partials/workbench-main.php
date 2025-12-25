<?php
/**
 * Main Workbench Dashboard Template
 *
 * Variables available:
 * - $service_requests      Array of open service request posts
 * - $projects              Array of active project posts
 * - $invoices              Array of pending invoice posts
 * - $sr_total_count        Total count of open service requests
 * - $project_total_count   Total count of active projects
 * - $invoice_total_count   Total count of pending invoices
 * - $client_tasks          Array of pending client task posts
 * - $roadmap_items         Array of active roadmap item posts
 * - $task_total_count      Total count of pending client tasks
 * - $roadmap_total_count   Total count of active roadmap items
 * - $organizations         Array of client organizations for simulation
 * - $simulating_org_id     Currently simulated org ID (0 if not simulating)
 * - $simulating_org_name   Name of currently simulated org
 *
 * @package BBAB\Core\Admin
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap bbab-workbench-wrap">
    <div class="bbab-workbench-header">
        <div class="bbab-header-left">
            <h1>
                <span class="dashicons dashicons-desktop"></span>
                <?php esc_html_e( "Brad's Workbench", 'bbab-core' ); ?>
            </h1>
            <p class="bbab-text-muted">
                <?php esc_html_e( 'Your command center for the BBAB Service Center.', 'bbab-core' ); ?>
            </p>
        </div>
        <div class="bbab-header-right">
            <div class="bbab-simulation-control">
                <?php if ( $simulating_org_id ) : ?>
                    <div class="bbab-simulation-active">
                        <span class="dashicons dashicons-visibility"></span>
                        <span class="bbab-simulation-label">
                            <?php
                            printf(
                                /* translators: %s: organization name */
                                esc_html__( 'Viewing as: %s', 'bbab-core' ),
                                '<strong>' . esc_html( $simulating_org_name ) . '</strong>'
                            );
                            ?>
                        </span>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bbab-workbench&bbab_stop_simulation=1' ), 'bbab_simulation' ) ); ?>" class="button button-small bbab-stop-simulation">
                            <?php esc_html_e( 'Exit', 'bbab-core' ); ?>
                        </a>
                        <a href="<?php echo esc_url( home_url( '/client-dashboard/' ) ); ?>" class="button button-primary button-small" target="_blank">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e( 'View Portal', 'bbab-core' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div class="bbab-simulation-form">
                        <label for="bbab-simulate-org" class="screen-reader-text">
                            <?php esc_html_e( 'Simulate client view', 'bbab-core' ); ?>
                        </label>
                        <select id="bbab-simulate-org" class="bbab-simulate-select">
                            <option value=""><?php esc_html_e( '👁️ Simulate Client View...', 'bbab-core' ); ?></option>
                            <?php foreach ( $organizations as $org ) : ?>
                                <option value="<?php echo esc_attr( $org['id'] ); ?>">
                                    <?php echo esc_html( $org['shortcode'] ? $org['shortcode'] . ' - ' . $org['name'] : $org['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="bbab-simulate-go" class="button button-primary button-small">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e( 'Go', 'bbab-core' ); ?>
                        </button>
                    </div>
                    <script>
                    document.getElementById('bbab-simulate-go').addEventListener('click', function() {
                        var orgId = document.getElementById('bbab-simulate-org').value;
                        if (orgId) {
                            window.location.href = <?php echo wp_json_encode( wp_nonce_url( admin_url( 'admin.php?page=bbab-workbench&bbab_simulate_org=' ), 'bbab_simulation' ) ); ?> + orgId;
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bbab-workbench-grid">

        <!-- Service Requests Box -->
        <div class="bbab-box" data-box-type="service-requests">
            <div class="bbab-box-header">
                <h2 class="bbab-box-title">
                    <span class="dashicons dashicons-sos"></span>
                    <?php esc_html_e( 'Open Service Requests', 'bbab-core' ); ?>
                </h2>
                <span class="bbab-box-count <?php echo $sr_total_count === 0 ? 'count-zero' : ''; ?>">
                    <?php echo esc_html( $sr_total_count ); ?>
                </span>
            </div>
            <div class="bbab-box-content">
                <?php if ( empty( $service_requests ) ) : ?>
                    <div class="bbab-empty-state">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p><?php esc_html_e( 'No open service requests. Nice work!', 'bbab-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="bbab-item-list">
                        <?php foreach ( $service_requests as $sr ) :
                            $ref_number = get_post_meta( $sr->ID, 'reference_number', true );
                            $status     = get_post_meta( $sr->ID, 'request_status', true );
                            $subject    = get_post_meta( $sr->ID, 'subject', true );
                            $org_code   = $this->get_org_shortcode( $sr->ID );
                            $te_count   = $this->get_sr_time_entry_count( $sr->ID );
                            $edit_link  = $this->get_edit_link( $sr->ID );

                            // Truncate subject if too long.
                            $subject_display = mb_strlen( $subject ) > 40 ? mb_substr( $subject, 0, 40 ) . '...' : $subject;
                        ?>
                            <li class="bbab-item">
                                <span class="bbab-item-ref"><?php echo esc_html( $ref_number ); ?></span>
                                <span class="bbab-item-title">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" title="<?php echo esc_attr( $subject ); ?>">
                                        <?php echo esc_html( $subject_display ); ?>
                                    </a>
                                </span>
                                <?php if ( $org_code ) : ?>
                                    <span class="bbab-item-org"><?php echo esc_html( $org_code ); ?></span>
                                <?php endif; ?>
                                <span class="bbab-item-meta">
                                    <?php if ( $te_count > 0 ) : ?>
                                        <a href="<?php echo esc_url( $this->get_time_entries_by_sr_url( $sr->ID ) ); ?>" class="bbab-te-count" title="<?php esc_attr_e( 'View Time Entries', 'bbab-core' ); ?>">
                                            <span class="dashicons dashicons-clock"></span><?php echo esc_html( $te_count ); ?>
                                        </a>
                                    <?php endif; ?>
                                </span>
                                <?php echo $this->render_status_badge( $status, 'sr' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer bbab-box-footer-3">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-requests' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Requests Hub', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=service_request' ) ); ?>" class="button">
                    <?php esc_html_e( 'WP List', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=service_request' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </a>
            </div>
        </div>

        <!-- Projects Box -->
        <div class="bbab-box" data-box-type="projects">
            <div class="bbab-box-header">
                <h2 class="bbab-box-title">
                    <span class="dashicons dashicons-portfolio"></span>
                    <?php esc_html_e( 'Active Projects', 'bbab-core' ); ?>
                </h2>
                <span class="bbab-box-count <?php echo $project_total_count === 0 ? 'count-zero' : ''; ?>">
                    <?php echo esc_html( $project_total_count ); ?>
                </span>
            </div>
            <div class="bbab-box-content">
                <?php if ( empty( $projects ) ) : ?>
                    <div class="bbab-empty-state">
                        <span class="dashicons dashicons-portfolio"></span>
                        <p><?php esc_html_e( 'No active projects at the moment.', 'bbab-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="bbab-item-list">
                        <?php foreach ( $projects as $project ) :
                            $ref_number      = get_post_meta( $project->ID, 'reference_number', true );
                            $status          = get_post_meta( $project->ID, 'project_status', true );
                            $project_name    = get_post_meta( $project->ID, 'project_name', true );
                            $org_code        = $this->get_org_shortcode( $project->ID );
                            $milestone_count = $this->get_project_milestone_count( $project->ID );
                            $te_count        = $this->get_project_time_entry_count( $project->ID );
                            $edit_link       = $this->get_edit_link( $project->ID );

                            // Use project_name if set, otherwise post title.
                            $display_name = ! empty( $project_name ) ? $project_name : $project->post_title;
                            $name_display = mb_strlen( $display_name ) > 35 ? mb_substr( $display_name, 0, 35 ) . '...' : $display_name;
                        ?>
                            <li class="bbab-item">
                                <span class="bbab-item-ref"><?php echo esc_html( $ref_number ); ?></span>
                                <span class="bbab-item-title">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" title="<?php echo esc_attr( $display_name ); ?>">
                                        <?php echo esc_html( $name_display ); ?>
                                    </a>
                                </span>
                                <?php if ( $org_code ) : ?>
                                    <span class="bbab-item-org"><?php echo esc_html( $org_code ); ?></span>
                                <?php endif; ?>
                                <span class="bbab-item-meta">
                                    <?php if ( $milestone_count > 0 ) : ?>
                                        <a href="<?php echo esc_url( $this->get_milestones_by_project_url( $project->ID ) ); ?>" class="bbab-milestone-count" title="<?php esc_attr_e( 'View Milestones', 'bbab-core' ); ?>">
                                            <span class="dashicons dashicons-flag"></span><?php echo esc_html( $milestone_count ); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( $te_count > 0 ) : ?>
                                        <a href="<?php echo esc_url( $this->get_time_entries_by_project_url( $project->ID ) ); ?>" class="bbab-te-count" title="<?php esc_attr_e( 'View Time Entries', 'bbab-core' ); ?>">
                                            <span class="dashicons dashicons-clock"></span><?php echo esc_html( $te_count ); ?>
                                        </a>
                                    <?php endif; ?>
                                </span>
                                <?php echo $this->render_status_badge( $status, 'project' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer bbab-box-footer-3">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-projects' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Projects Hub', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=project' ) ); ?>" class="button">
                    <?php esc_html_e( 'WP List', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=project' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </a>
            </div>
        </div>

        <!-- Invoices Box -->
        <div class="bbab-box" data-box-type="invoices">
            <div class="bbab-box-header">
                <h2 class="bbab-box-title">
                    <span class="dashicons dashicons-media-text"></span>
                    <?php esc_html_e( 'Pending Invoices', 'bbab-core' ); ?>
                </h2>
                <span class="bbab-box-count <?php echo $invoice_total_count === 0 ? 'count-zero' : ''; ?>">
                    <?php echo esc_html( $invoice_total_count ); ?>
                </span>
            </div>
            <div class="bbab-box-content">
                <?php if ( empty( $invoices ) ) : ?>
                    <div class="bbab-empty-state">
                        <span class="dashicons dashicons-money-alt"></span>
                        <p><?php esc_html_e( 'All invoices are paid. Cash flow looking good!', 'bbab-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="bbab-item-list">
                        <?php foreach ( $invoices as $invoice ) :
                            $inv_number = get_post_meta( $invoice->ID, 'invoice_number', true );
                            $status     = get_post_meta( $invoice->ID, 'invoice_status', true );
                            $amount     = get_post_meta( $invoice->ID, 'amount', true );
                            $due_date   = get_post_meta( $invoice->ID, 'due_date', true );
                            $org_code   = $this->get_org_shortcode( $invoice->ID );
                            $edit_link  = $this->get_edit_link( $invoice->ID );

                            // Format due date.
                            $due_display = '';
                            if ( $due_date ) {
                                $due_timestamp = strtotime( $due_date );
                                $due_display = date_i18n( 'M j', $due_timestamp );

                                // Check if overdue.
                                if ( $due_timestamp < time() && $status !== 'Paid' ) {
                                    $days_overdue = floor( ( time() - $due_timestamp ) / DAY_IN_SECONDS );
                                    $due_display .= ' (' . $days_overdue . 'd overdue)';
                                }
                            }
                        ?>
                            <li class="bbab-item">
                                <span class="bbab-item-ref">
                                    <a href="<?php echo esc_url( $edit_link ); ?>">
                                        <?php echo esc_html( $inv_number ); ?>
                                    </a>
                                </span>
                                <span class="bbab-item-title bbab-item-amount">
                                    <?php echo esc_html( $this->format_currency( $amount ) ); ?>
                                </span>
                                <?php if ( $org_code ) : ?>
                                    <span class="bbab-item-org"><?php echo esc_html( $org_code ); ?></span>
                                <?php endif; ?>
                                <span class="bbab-item-meta">
                                    <?php if ( $due_display ) : ?>
                                        <span class="bbab-due-date" title="<?php esc_attr_e( 'Due Date', 'bbab-core' ); ?>">
                                            <?php echo esc_html( $due_display ); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <?php echo $this->render_status_badge( $status, 'invoice' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer bbab-box-footer-3">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-invoices' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Invoices Hub', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=invoice' ) ); ?>" class="button">
                    <?php esc_html_e( 'WP List', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=invoice' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </a>
            </div>
        </div>

        <!-- Section Divider -->
        <div class="bbab-section-divider">
            <span class="bbab-section-divider-label"><?php esc_html_e( 'Planning & Client', 'bbab-core' ); ?></span>
        </div>

        <!-- Roadmap Items Box -->
        <div class="bbab-box" data-box-type="roadmap-items">
            <div class="bbab-box-header">
                <h2 class="bbab-box-title">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e( 'Active Roadmap Items', 'bbab-core' ); ?>
                </h2>
                <span class="bbab-box-count <?php echo $roadmap_total_count === 0 ? 'count-zero' : ''; ?>">
                    <?php echo esc_html( $roadmap_total_count ); ?>
                </span>
            </div>
            <div class="bbab-box-content">
                <?php if ( empty( $roadmap_items ) ) : ?>
                    <div class="bbab-empty-state">
                        <span class="dashicons dashicons-chart-line"></span>
                        <p><?php esc_html_e( 'No active roadmap items at the moment.', 'bbab-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="bbab-item-list">
                        <?php foreach ( $roadmap_items as $item ) :
                            $status      = get_post_meta( $item->ID, 'roadmap_status', true );
                            $priority    = get_post_meta( $item->ID, 'priority', true );
                            $description = get_post_meta( $item->ID, 'description', true );
                            $org_code    = $this->get_org_shortcode( $item->ID );
                            $edit_link   = $this->get_edit_link( $item->ID );

                            // Use post title as display name.
                            $display_name = $item->post_title;
                            $name_display = mb_strlen( $display_name ) > 35 ? mb_substr( $display_name, 0, 35 ) . '...' : $display_name;
                        ?>
                            <li class="bbab-item">
                                <span class="bbab-item-title">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" title="<?php echo esc_attr( $display_name ); ?>">
                                        <?php echo esc_html( $name_display ); ?>
                                    </a>
                                </span>
                                <?php if ( $org_code ) : ?>
                                    <span class="bbab-item-org"><?php echo esc_html( $org_code ); ?></span>
                                <?php endif; ?>
                                <?php if ( $priority ) : ?>
                                    <span class="bbab-priority-badge priority-<?php echo esc_attr( strtolower( $priority ) ); ?>">
                                        <?php echo esc_html( $priority ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo $this->render_status_badge( $status, 'roadmap' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer bbab-box-footer-3">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-roadmap' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Roadmap Hub', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=roadmap_item' ) ); ?>" class="button">
                    <?php esc_html_e( 'WP List', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=roadmap_item' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </a>
            </div>
        </div>

        <!-- Client Tasks Box -->
        <div class="bbab-box" data-box-type="client-tasks">
            <div class="bbab-box-header">
                <h2 class="bbab-box-title">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Pending Client Tasks', 'bbab-core' ); ?>
                </h2>
                <span class="bbab-box-count <?php echo $task_total_count === 0 ? 'count-zero' : ''; ?>">
                    <?php echo esc_html( $task_total_count ); ?>
                </span>
            </div>
            <div class="bbab-box-content">
                <?php if ( empty( $client_tasks ) ) : ?>
                    <div class="bbab-empty-state">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p><?php esc_html_e( 'No pending tasks for clients.', 'bbab-core' ); ?></p>
                    </div>
                <?php else : ?>
                    <ul class="bbab-item-list">
                        <?php foreach ( $client_tasks as $task ) :
                            $description = get_post_meta( $task->ID, 'task_description', true );
                            $due_date    = get_post_meta( $task->ID, 'due_date', true );
                            $status      = get_post_meta( $task->ID, 'task_status', true );
                            $org_code    = $this->get_task_org_shortcode( $task->ID );
                            $edit_link   = $this->get_edit_link( $task->ID );

                            // Use task_description or post title.
                            $display_name = ! empty( $description ) ? $description : $task->post_title;
                            $name_display = mb_strlen( $display_name ) > 40 ? mb_substr( $display_name, 0, 40 ) . '...' : $display_name;

                            // Format due date and check urgency.
                            $due_display = '';
                            $due_class   = '';
                            if ( $due_date ) {
                                $due_timestamp = strtotime( $due_date );
                                $today         = strtotime( 'today' );
                                $days_until    = floor( ( $due_timestamp - $today ) / DAY_IN_SECONDS );

                                $due_display = date_i18n( 'M j', $due_timestamp );

                                if ( $days_until < 0 ) {
                                    $due_class = 'bbab-overdue';
                                } elseif ( $days_until <= 3 ) {
                                    $due_class = 'bbab-due-soon';
                                }
                            }
                        ?>
                            <li class="bbab-item">
                                <span class="bbab-item-title">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" title="<?php echo esc_attr( $display_name ); ?>">
                                        <?php echo esc_html( $name_display ); ?>
                                    </a>
                                </span>
                                <?php if ( $org_code ) : ?>
                                    <span class="bbab-item-org"><?php echo esc_html( $org_code ); ?></span>
                                <?php endif; ?>
                                <?php if ( $due_display ) : ?>
                                    <span class="bbab-due-date <?php echo esc_attr( $due_class ); ?>">
                                        <?php echo esc_html( $due_display ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo $this->render_status_badge( $status, 'task' ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer bbab-box-footer-3">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-tasks' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Tasks Hub', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=client_task' ) ); ?>" class="button">
                    <?php esc_html_e( 'WP List', 'bbab-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=client_task' ) ); ?>" class="button">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </a>
            </div>
        </div>

    </div><!-- .bbab-workbench-grid -->

</div><!-- .bbab-workbench-wrap -->
