<?php
/**
 * Brad's Workbench - Main Dashboard Template.
 *
 * Ported from admin/partials/workbench-main.php
 *
 * @package BBAB\ServiceCenter\Admin\Workbench
 * @since   2.0.0
 *
 * Variables available:
 * @var array  $service_requests
 * @var array  $projects
 * @var array  $invoices
 * @var array  $client_tasks
 * @var array  $roadmap_items
 * @var int    $sr_total_count
 * @var int    $project_total_count
 * @var int    $invoice_total_count
 * @var int    $task_total_count
 * @var int    $roadmap_total_count
 * @var array  $organizations
 * @var int    $simulating_org_id
 * @var string $simulating_org_name
 * @var WorkbenchPage $this
 */

use BBAB\ServiceCenter\Core\SimulationBootstrap;

// Don't allow direct access.
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap bbab-workbench-wrap">
    <h1><?php esc_html_e("Brad's Workbench", 'bbab-service-center'); ?></h1>

    <!-- Simulation Controls -->
    <div class="bbab-simulation-controls">
        <?php if ($simulating_org_id):
            $dashboard_page_id = \BBAB\ServiceCenter\Utils\Settings::get('dashboard_page_id');
            $dashboard_url = $dashboard_page_id ? get_permalink($dashboard_page_id) : home_url('/client-dashboard/');
        ?>
            <div class="bbab-simulation-active">
                <span class="bbab-sim-indicator"></span>
                <strong><?php esc_html_e('Simulating:', 'bbab-service-center'); ?></strong>
                <?php echo esc_html($simulating_org_name); ?>
                <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Open Dashboard', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(
                    add_query_arg('bbab_sc_exit_simulation', '1', admin_url('admin.php?page=bbab-workbench')),
                    'bbab_sc_simulation'
                )); ?>" class="button bbab-btn-stop">
                    <?php esc_html_e('Exit Simulation', 'bbab-service-center'); ?>
                </a>
            </div>
        <?php else: ?>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="bbab-simulation-form">
                <input type="hidden" name="page" value="bbab-workbench">
                <?php wp_nonce_field('bbab_sc_simulation', '_wpnonce', false); ?>
                <label for="bbab_sc_simulate_org"><?php esc_html_e('Simulate Client:', 'bbab-service-center'); ?></label>
                <select name="bbab_sc_simulate_org" id="bbab_sc_simulate_org">
                    <option value=""><?php esc_html_e('-- Select Organization --', 'bbab-service-center'); ?></option>
                    <?php foreach ($organizations as $org): ?>
                        <option value="<?php echo esc_attr($org['id']); ?>">
                            <?php echo esc_html($org['shortcode'] ? $org['shortcode'] . ' - ' . $org['name'] : $org['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-primary"><?php esc_html_e('Start Simulation', 'bbab-service-center'); ?></button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Dashboard Boxes Grid -->
    <div class="bbab-workbench-grid">

        <!-- Service Requests Box -->
        <div class="bbab-workbench-box">
            <div class="bbab-box-header">
                <h2><?php esc_html_e('Service Requests', 'bbab-service-center'); ?></h2>
                <span class="bbab-box-count"><?php echo esc_html($sr_total_count); ?> <?php esc_html_e('open', 'bbab-service-center'); ?></span>
            </div>
            <div class="bbab-box-content">
                <?php if (empty($service_requests)): ?>
                    <p class="bbab-no-items"><?php esc_html_e('No open service requests.', 'bbab-service-center'); ?></p>
                <?php else: ?>
                    <table class="bbab-workbench-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ref', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Client', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Status', 'bbab-service-center'); ?></th>
                                <th class="bbab-col-center"><?php esc_html_e('TEs', 'bbab-service-center'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($service_requests as $sr):
                                $ref = get_post_meta($sr->ID, 'reference_number', true);
                                $status = get_post_meta($sr->ID, 'request_status', true);
                                $org_shortcode = $this->getOrgShortcode($sr->ID);
                                $te_count = $this->getSrTimeEntryCount($sr->ID);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->getEditLink($sr->ID)); ?>" class="bbab-ref-link">
                                        <?php echo esc_html($ref); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($org_shortcode): ?>
                                        <span class="bbab-org-badge"><?php echo esc_html($org_shortcode); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->renderStatusBadge($status); ?></td>
                                <td class="bbab-col-center">
                                    <?php if ($te_count > 0): ?>
                                        <a href="<?php echo esc_url($this->getTimeEntriesBySrUrl($sr->ID)); ?>" title="<?php esc_attr_e('View Time Entries', 'bbab-service-center'); ?>">
                                            <?php echo esc_html($te_count); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbab-requests')); ?>" class="button">
                    <?php esc_html_e('SR Hub', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=service_request')); ?>" class="button">
                    <?php esc_html_e('WP List', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=service_request')); ?>" class="button button-primary">
                    <?php esc_html_e('+ Add', 'bbab-service-center'); ?>
                </a>
            </div>
        </div>

        <!-- Projects Box -->
        <div class="bbab-workbench-box">
            <div class="bbab-box-header">
                <h2><?php esc_html_e('Projects', 'bbab-service-center'); ?></h2>
                <span class="bbab-box-count"><?php echo esc_html($project_total_count); ?> <?php esc_html_e('active', 'bbab-service-center'); ?></span>
            </div>
            <div class="bbab-box-content">
                <?php if (empty($projects)): ?>
                    <p class="bbab-no-items"><?php esc_html_e('No active projects.', 'bbab-service-center'); ?></p>
                <?php else: ?>
                    <table class="bbab-workbench-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Ref', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Client', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Status', 'bbab-service-center'); ?></th>
                                <th class="bbab-col-center"><?php esc_html_e('MS', 'bbab-service-center'); ?></th>
                                <th class="bbab-col-center"><?php esc_html_e('TEs', 'bbab-service-center'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project):
                                $ref = get_post_meta($project->ID, 'reference_number', true);
                                $status = get_post_meta($project->ID, 'project_status', true);
                                $org_shortcode = $this->getOrgShortcode($project->ID);
                                $ms_count = $this->getProjectMilestoneCount($project->ID);
                                $te_count = $this->getProjectTimeEntryCount($project->ID);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->getEditLink($project->ID)); ?>" class="bbab-ref-link">
                                        <?php echo esc_html($ref ?: $project->post_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($org_shortcode): ?>
                                        <span class="bbab-org-badge"><?php echo esc_html($org_shortcode); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->renderStatusBadge($status); ?></td>
                                <td class="bbab-col-center">
                                    <?php if ($ms_count > 0): ?>
                                        <a href="<?php echo esc_url($this->getMilestonesByProjectUrl($project->ID)); ?>" title="<?php esc_attr_e('View Milestones', 'bbab-service-center'); ?>">
                                            <?php echo esc_html($ms_count); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="bbab-col-center">
                                    <?php if ($te_count > 0): ?>
                                        <a href="<?php echo esc_url($this->getTimeEntriesByProjectUrl($project->ID)); ?>" title="<?php esc_attr_e('View Time Entries', 'bbab-service-center'); ?>">
                                            <?php echo esc_html($te_count); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbab-projects')); ?>" class="button">
                    <?php esc_html_e('Projects Hub', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=project')); ?>" class="button">
                    <?php esc_html_e('WP List', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=project')); ?>" class="button button-primary">
                    <?php esc_html_e('+ Add', 'bbab-service-center'); ?>
                </a>
            </div>
        </div>

        <!-- Invoices Box -->
        <div class="bbab-workbench-box">
            <div class="bbab-box-header">
                <h2><?php esc_html_e('Invoices', 'bbab-service-center'); ?></h2>
                <span class="bbab-box-count"><?php echo esc_html($invoice_total_count); ?> <?php esc_html_e('pending', 'bbab-service-center'); ?></span>
            </div>
            <div class="bbab-box-content">
                <?php if (empty($invoices)): ?>
                    <p class="bbab-no-items"><?php esc_html_e('No pending invoices.', 'bbab-service-center'); ?></p>
                <?php else: ?>
                    <table class="bbab-workbench-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Invoice #', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Client', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Status', 'bbab-service-center'); ?></th>
                                <th class="bbab-col-right"><?php esc_html_e('Amount', 'bbab-service-center'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice):
                                $number = get_post_meta($invoice->ID, 'invoice_number', true);
                                $status = get_post_meta($invoice->ID, 'invoice_status', true);
                                $amount = floatval(get_post_meta($invoice->ID, 'total_amount', true));
                                $org_shortcode = $this->getOrgShortcode($invoice->ID);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->getEditLink($invoice->ID)); ?>" class="bbab-ref-link">
                                        <?php echo esc_html($number); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($org_shortcode): ?>
                                        <span class="bbab-org-badge"><?php echo esc_html($org_shortcode); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->renderStatusBadge($status); ?></td>
                                <td class="bbab-col-right"><?php echo esc_html($this->formatCurrency($amount)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbab-invoices')); ?>" class="button">
                    <?php esc_html_e('Invoices Hub', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=invoice')); ?>" class="button">
                    <?php esc_html_e('WP List', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=invoice')); ?>" class="button button-primary">
                    <?php esc_html_e('+ Add', 'bbab-service-center'); ?>
                </a>
            </div>
        </div>

        <!-- Row Divider -->
        <div class="bbab-row-divider">
            <span class="bbab-divider-label">Client Items</span>
        </div>

        <!-- Roadmap Items Box -->
        <div class="bbab-workbench-box">
            <div class="bbab-box-header">
                <h2><?php esc_html_e('Roadmap Items', 'bbab-service-center'); ?></h2>
                <span class="bbab-box-count"><?php echo esc_html($roadmap_total_count); ?> <?php esc_html_e('active', 'bbab-service-center'); ?></span>
            </div>
            <div class="bbab-box-content">
                <?php if (empty($roadmap_items)): ?>
                    <p class="bbab-no-items"><?php esc_html_e('No active roadmap items.', 'bbab-service-center'); ?></p>
                <?php else: ?>
                    <table class="bbab-workbench-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Feature', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Client', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Status', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Priority', 'bbab-service-center'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roadmap_items as $item):
                                $status = get_post_meta($item->ID, 'roadmap_status', true);
                                $priority = get_post_meta($item->ID, 'priority', true);
                                $org_shortcode = $this->getOrgShortcode($item->ID);
                                $title = mb_strlen($item->post_title) > 30 ? mb_substr($item->post_title, 0, 30) . '...' : $item->post_title;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->getEditLink($item->ID)); ?>" title="<?php echo esc_attr($item->post_title); ?>">
                                        <?php echo esc_html($title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($org_shortcode): ?>
                                        <span class="bbab-org-badge"><?php echo esc_html($org_shortcode); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->renderStatusBadge($status); ?></td>
                                <td>
                                    <?php if ($priority): ?>
                                        <span class="bbab-priority-badge priority-<?php echo esc_attr(strtolower($priority)); ?>">
                                            <?php echo esc_html($priority); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbab-roadmap')); ?>" class="button">
                    <?php esc_html_e('Roadmap Hub', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=roadmap_item')); ?>" class="button">
                    <?php esc_html_e('WP List', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=roadmap_item')); ?>" class="button button-primary">
                    <?php esc_html_e('+ Add', 'bbab-service-center'); ?>
                </a>
            </div>
        </div>

        <!-- Client Tasks Box -->
        <div class="bbab-workbench-box">
            <div class="bbab-box-header">
                <h2><?php esc_html_e('Client Tasks', 'bbab-service-center'); ?></h2>
                <span class="bbab-box-count"><?php echo esc_html($task_total_count); ?> <?php esc_html_e('pending', 'bbab-service-center'); ?></span>
            </div>
            <div class="bbab-box-content">
                <?php if (empty($client_tasks)): ?>
                    <p class="bbab-no-items"><?php esc_html_e('No pending client tasks.', 'bbab-service-center'); ?></p>
                <?php else: ?>
                    <table class="bbab-workbench-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Task', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Client', 'bbab-service-center'); ?></th>
                                <th><?php esc_html_e('Due Date', 'bbab-service-center'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_tasks as $task):
                                $description = get_post_meta($task->ID, 'task_description', true) ?: $task->post_title;
                                $due_date = get_post_meta($task->ID, 'due_date', true);
                                $org_shortcode = $this->getTaskOrgShortcode($task->ID);
                                $display_desc = mb_strlen($description) > 35 ? mb_substr($description, 0, 35) . '...' : $description;

                                // Due date formatting
                                $due_class = '';
                                $due_display = '&mdash;';
                                if (!empty($due_date)) {
                                    $due_timestamp = strtotime($due_date);
                                    $today = strtotime('today');
                                    $soon = strtotime('+3 days');

                                    $due_display = date_i18n('M j', $due_timestamp);
                                    if ($due_timestamp < $today) {
                                        $due_class = 'bbab-overdue';
                                    } elseif ($due_timestamp <= $soon) {
                                        $due_class = 'bbab-due-soon';
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($this->getEditLink($task->ID)); ?>" title="<?php echo esc_attr($description); ?>">
                                        <?php echo esc_html($display_desc); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($org_shortcode): ?>
                                        <span class="bbab-org-badge"><?php echo esc_html($org_shortcode); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($due_date)): ?>
                                        <span class="<?php echo esc_attr($due_class); ?>"><?php echo esc_html($due_display); ?></span>
                                    <?php else: ?>
                                        <span class="bbab-text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="bbab-box-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=bbab-tasks')); ?>" class="button">
                    <?php esc_html_e('Tasks Hub', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=client_task')); ?>" class="button">
                    <?php esc_html_e('WP List', 'bbab-service-center'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=client_task')); ?>" class="button button-primary">
                    <?php esc_html_e('+ Add', 'bbab-service-center'); ?>
                </a>
            </div>
        </div>

    </div><!-- .bbab-workbench-grid -->
</div><!-- .bbab-workbench-wrap -->
