<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Admin;

use BBAB\ServiceCenter\Modules\Billing\BillingAlerts;
use BBAB\ServiceCenter\Utils\Settings;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Client Portal Admin Menu.
 *
 * Consolidates all client portal CPT menus under a single "Service Center" menu.
 * Provides an overview page with alert widgets and CPT cards.
 *
 * Migrated from: WPCode Snippet #2228
 */
class ClientPortalMenu {

    /**
     * CPTs to consolidate under this menu.
     */
    private const CPTS = [
        'client_organization',
        'monthly_report',
        'time_entry',
        'client_task',
        'invoice',
        'invoice_line_item',
        'service_request',
        'project',
        'milestone',
        'project_report',
        'kb_article',
        'roadmap_item',
    ];

    /**
     * Taxonomies to include in menu.
     */
    private const TAXONOMIES = [
        'work_type' => 'time_entry',
        'kb_category' => 'kb_article',
    ];

    /**
     * Register menu hooks.
     */
    public function register(): void {
        // Register main menu and submenus
        add_action('admin_menu', [$this, 'registerMainMenu'], 9);
        add_action('admin_menu', [$this, 'moveSubmenus'], 99);

        // Fix menu highlighting
        add_filter('parent_file', [$this, 'fixParentHighlight']);
        add_filter('submenu_file', [$this, 'fixSubmenuHighlight'], 10, 2);

        // Enqueue styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);

        Logger::debug('ClientPortalMenu', 'Registered admin menu hooks');
    }

    /**
     * Register the main Client Portal menu.
     */
    public function registerMainMenu(): void {
        // Get menu title from settings (with alert badge if applicable)
        $menu_title = $this->getMenuTitle();

        add_menu_page(
            $menu_title,
            $menu_title,
            'manage_options',
            'bbab-portal-admin',
            [$this, 'renderOverviewPage'],
            'dashicons-groups',
            30
        );

        // Overview submenu (same slug as parent to make it the landing page)
        add_submenu_page(
            'bbab-portal-admin',
            'Overview',
            'Overview',
            'manage_options',
            'bbab-portal-admin',
            [$this, 'renderOverviewPage']
        );
    }

    /**
     * Get menu title with optional alert badge.
     */
    private function getMenuTitle(): string {
        $title = Settings::get('service_center_name', 'Service Center');

        $alert_count = BillingAlerts::getTotalAlertCount();
        if ($alert_count > 0) {
            $title .= sprintf(
                ' <span class="awaiting-mod update-plugins count-%d"><span class="plugin-count">%d</span></span>',
                $alert_count,
                $alert_count
            );
        }

        return $title;
    }

    /**
     * Move CPT menus under the Client Portal menu.
     */
    public function moveSubmenus(): void {
        // Remove default CPT top-level menus
        foreach (self::CPTS as $cpt) {
            remove_menu_page('edit.php?post_type=' . $cpt);
        }

        // Add submenus in grouped order

        // Service & Support
        add_submenu_page('bbab-portal-admin', 'Service Requests', 'Service Requests', 'manage_options', 'edit.php?post_type=service_request');
        add_submenu_page('bbab-portal-admin', 'Time Entries', 'Time Entries', 'manage_options', 'edit.php?post_type=time_entry');

        // Billing & Invoicing
        add_submenu_page('bbab-portal-admin', 'Invoices', 'Invoices', 'manage_options', 'edit.php?post_type=invoice');
        add_submenu_page('bbab-portal-admin', 'Invoice Line Items', 'Line Items', 'manage_options', 'edit.php?post_type=invoice_line_item');
        add_submenu_page('bbab-portal-admin', 'Monthly Reports', 'Monthly Reports', 'manage_options', 'edit.php?post_type=monthly_report');

        // Projects
        add_submenu_page('bbab-portal-admin', 'Projects', 'Projects', 'manage_options', 'edit.php?post_type=project');
        add_submenu_page('bbab-portal-admin', 'Milestones', 'Milestones', 'manage_options', 'edit.php?post_type=milestone');
        add_submenu_page('bbab-portal-admin', 'Project Reports', 'Project Reports', 'manage_options', 'edit.php?post_type=project_report');

        // Strategy & Roadmap
        add_submenu_page('bbab-portal-admin', 'Roadmap Items', 'Roadmap Items', 'manage_options', 'edit.php?post_type=roadmap_item');

        // Clients & Knowledge
        add_submenu_page('bbab-portal-admin', 'Organizations', 'Organizations', 'manage_options', 'edit.php?post_type=client_organization');
        add_submenu_page('bbab-portal-admin', 'Client Tasks', 'Client Tasks', 'manage_options', 'edit.php?post_type=client_task');
        add_submenu_page('bbab-portal-admin', 'KB Articles', 'KB Articles', 'manage_options', 'edit.php?post_type=kb_article');

        // Taxonomies
        add_submenu_page('bbab-portal-admin', 'Work Types', 'Work Types', 'manage_options', 'edit-tags.php?taxonomy=work_type&post_type=time_entry');
        add_submenu_page('bbab-portal-admin', 'KB Categories', 'KB Categories', 'manage_options', 'edit-tags.php?taxonomy=kb_category&post_type=kb_article');
    }

    /**
     * Fix parent menu highlighting for CPT and taxonomy pages.
     *
     * @param string|null $parent_file Current parent file.
     * @return string Modified parent file.
     */
    public function fixParentHighlight(?string $parent_file): string {
        if ($parent_file === null) {
            return '';
        }
        global $current_screen;

        if (!$current_screen) {
            return $parent_file;
        }

        // Check if this is one of our CPTs or taxonomies
        if (in_array($current_screen->post_type, self::CPTS, true) ||
            in_array($current_screen->taxonomy, array_keys(self::TAXONOMIES), true)) {
            return 'bbab-portal-admin';
        }

        return $parent_file;
    }

    /**
     * Fix submenu highlighting for taxonomy pages.
     *
     * @param string|null $submenu_file Current submenu file.
     * @param string      $parent_file  Parent file.
     * @return string|null Modified submenu file.
     */
    public function fixSubmenuHighlight(?string $submenu_file, string $parent_file): ?string {
        if ($submenu_file === null) {
            return null;
        }
        global $current_screen;

        if (!$current_screen) {
            return $submenu_file;
        }

        if ($current_screen->taxonomy === 'work_type') {
            return 'edit-tags.php?taxonomy=work_type&post_type=time_entry';
        }

        if ($current_screen->taxonomy === 'kb_category') {
            return 'edit-tags.php?taxonomy=kb_category&post_type=kb_article';
        }

        return $submenu_file;
    }

    /**
     * Enqueue admin styles for the overview page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueueStyles(string $hook): void {
        if ($hook !== 'toplevel_page_bbab-portal-admin') {
            return;
        }

        wp_add_inline_style('wp-admin', $this->getOverviewStyles());
    }

    /**
     * Render the overview page.
     */
    public function renderOverviewPage(): void {
        ?>
        <div class="wrap bbab-portal-wrap">
            <h1>Client Portal Admin</h1>

            <!-- Alert Widgets Section -->
            <div class="bbab-alerts-section">
                <?php $this->renderBillingAlerts(); ?>
                <?php $this->renderSRAlerts(); ?>
                <?php $this->renderTaskAlerts(); ?>
            </div>

            <!-- CPT Cards by Group -->
            <div class="bbab-section">
                <h2 class="bbab-section-title">Service & Support</h2>
                <div class="bbab-admin-cards">
                    <?php $this->renderCPTCard('service_request', 'Service Requests', 'Client support tickets'); ?>
                    <?php $this->renderCPTCard('time_entry', 'Time Entries', 'Billable work tracking', false); ?>
                </div>
            </div>

            <div class="bbab-section">
                <h2 class="bbab-section-title">Billing & Invoicing</h2>
                <div class="bbab-admin-cards">
                    <?php $this->renderCPTCard('invoice', 'Invoices', 'Client billing'); ?>
                    <?php $this->renderCPTCard('invoice_line_item', 'Line Items', 'Invoice details'); ?>
                    <?php $this->renderCPTCard('monthly_report', 'Monthly Reports', 'Usage summaries'); ?>
                </div>
            </div>

            <div class="bbab-section">
                <h2 class="bbab-section-title">Projects</h2>
                <div class="bbab-admin-cards">
                    <?php $this->renderCPTCard('project', 'Projects', 'Client projects'); ?>
                    <?php $this->renderCPTCard('milestone', 'Milestones', 'Project deliverables'); ?>
                    <?php $this->renderCPTCard('project_report', 'Project Reports', 'Summaries & handoffs'); ?>
                </div>
            </div>

            <div class="bbab-section">
                <h2 class="bbab-section-title">Roadmap Items</h2>
                <div class="bbab-admin-cards">
                    <?php $this->renderCPTCard('roadmap_item', 'Roadmap Items', 'Planned features'); ?>
                </div>
            </div>

            <div class="bbab-section">
                <h2 class="bbab-section-title">Clients & Knowledge</h2>
                <div class="bbab-admin-cards">
                    <?php $this->renderCPTCard('client_organization', 'Organizations', 'Client companies'); ?>
                    <?php $this->renderCPTCard('client_task', 'Client Tasks', 'Action items for clients'); ?>
                    <?php $this->renderCPTCard('kb_article', 'KB Articles', 'Knowledge base'); ?>
                </div>
            </div>

            <div class="bbab-section">
                <h2 class="bbab-section-title">Taxonomies</h2>
                <div class="bbab-admin-cards">
                    <div class="bbab-admin-card bbab-card-taxonomy">
                        <h3>Work Types</h3>
                        <p>Time entry categories</p>
                        <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=work_type&post_type=time_entry')); ?>" class="button">Manage</a>
                    </div>
                    <div class="bbab-admin-card bbab-card-taxonomy">
                        <h3>KB Categories</h3>
                        <p>Knowledge base categories</p>
                        <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=kb_category&post_type=kb_article')); ?>" class="button">Manage</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render billing alerts widget.
     */
    private function renderBillingAlerts(): void {
        $overdue = BillingAlerts::getOverdueInvoices();
        $needs_invoice = BillingAlerts::getReportsNeedingInvoices();
        $due_soon = BillingAlerts::getInvoicesDueSoon(2);

        $total_alerts = count($overdue) + count($needs_invoice);
        $has_alerts = $total_alerts > 0;
        ?>
        <div class="bbab-alert-widget <?php echo $has_alerts ? 'has-alerts' : 'all-clear'; ?>">
            <h3>
                Billing
                <?php if ($has_alerts): ?>
                    <span class="alert-count"><?php echo esc_html((string) $total_alerts); ?></span>
                <?php else: ?>
                    <span class="all-clear-badge">All Clear</span>
                <?php endif; ?>
            </h3>

            <ul class="bbab-alert-list">
                <?php if (!empty($overdue)): ?>
                    <?php foreach (array_slice($overdue, 0, 5) as $inv): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $inv['id'] . '&action=edit')); ?>">
                                <?php echo esc_html($inv['number']); ?>
                            </a>
                            - <?php echo esc_html($inv['org_name']); ?>
                            <span class="alert-meta">
                                $<?php echo esc_html(number_format($inv['balance'], 2)); ?>
                                <span class="overdue-days">(<?php echo esc_html((string) $inv['days_overdue']); ?> days overdue)</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($overdue) > 5): ?>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=invoice&invoice_status=Overdue')); ?>">+ <?php echo esc_html((string) (count($overdue) - 5)); ?> more overdue</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($needs_invoice)): ?>
                    <?php foreach ($needs_invoice as $report): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $report['id'] . '&action=edit')); ?>">
                                <?php echo esc_html($report['org_name']); ?>
                            </a>
                            <span class="alert-meta">- <?php echo esc_html($report['month']); ?> needs invoice</span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($due_soon)): ?>
                    <?php foreach ($due_soon as $inv): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $inv['id'] . '&action=edit')); ?>">
                                <?php echo esc_html($inv['number']); ?>
                            </a>
                            - <?php echo esc_html($inv['org_name']); ?>
                            <span class="alert-meta">
                                $<?php echo esc_html(number_format($inv['amount'], 2)); ?>
                                <span class="due-soon">(due in <?php echo esc_html((string) $inv['days_until']); ?> days)</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($overdue) && empty($needs_invoice) && empty($due_soon)): ?>
                    <li class="all-clear-message">No billing alerts</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render service request alerts widget.
     */
    private function renderSRAlerts(): void {
        $new_srs = BillingAlerts::getNewServiceRequests();
        $in_progress = BillingAlerts::getInProgressSRCount();

        $has_alerts = count($new_srs) > 0;
        ?>
        <div class="bbab-alert-widget <?php echo $has_alerts ? 'has-alerts' : 'all-clear'; ?>">
            <h3>
                Service Requests
                <?php if ($has_alerts): ?>
                    <span class="alert-count"><?php echo esc_html((string) count($new_srs)); ?> new</span>
                <?php else: ?>
                    <span class="all-clear-badge">All Clear</span>
                <?php endif; ?>
            </h3>

            <ul class="bbab-alert-list">
                <?php if (!empty($new_srs)): ?>
                    <?php foreach (array_slice($new_srs, 0, 5) as $sr): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $sr['id'] . '&action=edit')); ?>">
                                <?php echo esc_html($sr['ref']); ?>
                            </a>
                            - <?php echo esc_html($sr['org_name']); ?>
                            <span class="alert-meta">- <?php echo esc_html(wp_trim_words($sr['subject'], 8)); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (count($new_srs) > 5): ?>
                        <li><a href="<?php echo esc_url(admin_url('edit.php?post_type=service_request&request_status=New')); ?>">+ <?php echo esc_html((string) (count($new_srs) - 5)); ?> more new</a></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($in_progress > 0): ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=service_request')); ?>"><?php echo esc_html((string) $in_progress); ?> in progress</a>
                    </li>
                <?php endif; ?>

                <?php if (empty($new_srs) && $in_progress == 0): ?>
                    <li class="all-clear-message">No active service requests</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render task alerts widget.
     */
    private function renderTaskAlerts(): void {
        $overdue_tasks = BillingAlerts::getOverdueTasks();
        $due_soon_tasks = BillingAlerts::getTasksDueSoon(3);

        $has_alerts = count($overdue_tasks) > 0;
        ?>
        <div class="bbab-alert-widget <?php echo $has_alerts ? 'has-alerts' : 'all-clear'; ?>">
            <h3>
                Client Tasks
                <?php if ($has_alerts): ?>
                    <span class="alert-count"><?php echo esc_html((string) count($overdue_tasks)); ?> overdue</span>
                <?php else: ?>
                    <span class="all-clear-badge">All Clear</span>
                <?php endif; ?>
            </h3>

            <ul class="bbab-alert-list">
                <?php if (!empty($overdue_tasks)): ?>
                    <?php foreach (array_slice($overdue_tasks, 0, 5) as $task): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $task['id'] . '&action=edit')); ?>">
                                <?php echo esc_html(wp_trim_words($task['description'], 8)); ?>
                            </a>
                            <span class="alert-meta">
                                - <?php echo esc_html($task['org_name']); ?>
                                <span class="overdue-days">(<?php echo esc_html((string) $task['days_overdue']); ?> days overdue)</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($due_soon_tasks)): ?>
                    <?php foreach (array_slice($due_soon_tasks, 0, 3) as $task): ?>
                        <li>
                            <a href="<?php echo esc_url(admin_url('post.php?post=' . $task['id'] . '&action=edit')); ?>">
                                <?php echo esc_html(wp_trim_words($task['description'], 8)); ?>
                            </a>
                            <span class="alert-meta">
                                - <?php echo esc_html($task['org_name']); ?>
                                <span class="due-soon">(due in <?php echo esc_html((string) $task['days_until']); ?> days)</span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($overdue_tasks) && empty($due_soon_tasks)): ?>
                    <li class="all-clear-message">No task alerts</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render a CPT card.
     *
     * @param string $post_type   The post type slug.
     * @param string $label       Display label.
     * @param string $description Short description.
     * @param bool   $show_add    Show "Add New" button.
     */
    private function renderCPTCard(string $post_type, string $label, string $description, bool $show_add = true): void {
        $count = wp_count_posts($post_type);
        $published = isset($count->publish) ? (int) $count->publish : 0;
        ?>
        <div class="bbab-admin-card">
            <h3><?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html((string) $published); ?>)</span></h3>
            <p><?php echo esc_html($description); ?></p>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . $post_type)); ?>" class="button">View</a>
            <?php if ($show_add): ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $post_type)); ?>" class="button button-primary">+ Add</a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get CSS styles for the overview page.
     *
     * @return string CSS styles.
     */
    private function getOverviewStyles(): string {
        return '
            .bbab-portal-wrap {
                max-width: 1400px;
            }

            /* Alert Section */
            .bbab-alerts-section {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0 30px 0;
            }

            .bbab-alert-widget {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 6px;
                padding: 16px 20px;
                border-left: 4px solid #ccd0d4;
            }

            .bbab-alert-widget.has-alerts {
                border-left-color: #d63638;
            }

            .bbab-alert-widget.all-clear {
                border-left-color: #00a32a;
            }

            .bbab-alert-widget h3 {
                margin: 0 0 12px 0;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .bbab-alert-widget h3 .alert-count {
                background: #d63638;
                color: #fff;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 12px;
            }

            .bbab-alert-widget h3 .all-clear-badge {
                background: #00a32a;
                color: #fff;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 11px;
            }

            .bbab-alert-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .bbab-alert-list li {
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f1;
                font-size: 13px;
            }

            .bbab-alert-list li:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .bbab-alert-list a {
                text-decoration: none;
                color: #2271b1;
            }

            .bbab-alert-list a:hover {
                text-decoration: underline;
            }

            .bbab-alert-list .alert-meta {
                color: #787c82;
                font-size: 12px;
            }

            .bbab-alert-list .overdue-days {
                color: #d63638;
                font-weight: 500;
            }

            .bbab-alert-list .due-soon {
                color: #dba617;
            }

            .bbab-alert-list .all-clear-message {
                color: #00a32a;
            }

            /* Section styling */
            .bbab-section {
                margin-bottom: 30px;
            }

            .bbab-section-title {
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
                margin: 0 0 15px 0;
                padding-bottom: 8px;
                border-bottom: 2px solid #f0f0f1;
            }

            /* Card Grid */
            .bbab-admin-cards {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 16px;
            }

            .bbab-admin-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 16px 20px;
                transition: box-shadow 0.2s;
            }

            .bbab-admin-card:hover {
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .bbab-admin-card h3 {
                margin: 0 0 8px 0;
                font-size: 15px;
            }

            .bbab-admin-card h3 .count {
                color: #787c82;
                font-weight: normal;
                font-size: 13px;
            }

            .bbab-admin-card p {
                color: #646970;
                margin: 0 0 12px 0;
                font-size: 13px;
            }

            .bbab-admin-card .button {
                margin-right: 8px;
            }

            .bbab-card-taxonomy {
                background: #f6f7f7;
            }
        ';
    }
}
