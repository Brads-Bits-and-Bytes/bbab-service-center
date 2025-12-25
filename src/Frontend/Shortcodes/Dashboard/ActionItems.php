<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Action Items shortcode.
 *
 * Shows pending and recently completed action items from Brad.
 * Only displays tasks that are unassigned or assigned to the current user.
 *
 * Shortcode: [dashboard_action_items]
 * Migrated from: WPCode Snippet #1329
 */
class ActionItems extends BaseShortcode {

    protected string $tag = 'dashboard_action_items';

    /**
     * Render the action items output.
     */
    protected function output(array $atts, int $org_id): string {
        global $wpdb;

        $user_id = get_current_user_id();

        // Direct database query to get task IDs linked to this org
        // Only show tasks that are either unassigned OR assigned to current user
        $task_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->prefix}podsrel r ON p.ID = r.item_id
             LEFT JOIN {$wpdb->postmeta} au ON p.ID = au.post_id AND au.meta_key = 'assigned_user'
             WHERE p.post_type = 'client_task'
             AND p.post_status = 'publish'
             AND r.related_item_id = %d
             AND (au.meta_value IS NULL OR au.meta_value = '' OR au.meta_value = %d)
             ORDER BY p.post_date DESC",
            $org_id,
            $user_id
        ));

        // Separate pending and completed tasks
        $pending_tasks = [];
        $completed_tasks = [];

        foreach ($task_ids as $task_id) {
            $status = get_post_meta($task_id, 'task_status', true);
            if ($status === 'Pending') {
                $pending_tasks[] = $task_id;
            } elseif ($status === 'Completed') {
                $completed_date = get_post_meta($task_id, 'completed_date', true);
                $completed_tasks[] = [
                    'id' => $task_id,
                    'completed_date' => $completed_date ? strtotime($completed_date) : 0,
                ];
            }
        }

        // Sort completed by date (most recent first) and take top 5
        usort($completed_tasks, function ($a, $b) {
            return $b['completed_date'] - $a['completed_date'];
        });
        $completed_tasks = array_slice($completed_tasks, 0, 5);

        ob_start();
        ?>
        <div class="action-items-section">
            <h3 class="section-title">&#128221; Action Items from Brad</h3>

            <?php if (!empty($pending_tasks)): ?>
                <div class="tasks-list">
                    <?php foreach ($pending_tasks as $task_id):
                        $description = get_post_meta($task_id, 'task_description', true);
                        $due_date = get_post_meta($task_id, 'due_date', true);
                        $notes = get_post_meta($task_id, 'task_notes', true);

                        // Calculate urgency
                        $urgency_class = 'no-date';
                        $due_text = 'No due date';

                        if (!empty($due_date) && strtotime($due_date) !== false && strtotime($due_date) > 0) {
                            $due_timestamp = strtotime($due_date);
                            $today = strtotime('today');
                            $days_until = (int) floor(($due_timestamp - $today) / DAY_IN_SECONDS);

                            if ($days_until < 0) {
                                $urgency_class = 'overdue';
                                $days_overdue = abs($days_until);
                                $due_text = 'Due: ' . date('M j, Y', $due_timestamp) . ' (' . $days_overdue . ' day' . ($days_overdue != 1 ? 's' : '') . ' overdue)';
                            } elseif ($days_until <= 3) {
                                $urgency_class = 'due-soon';
                                $due_text = 'Due: ' . date('M j, Y', $due_timestamp) . ' (' . $days_until . ' day' . ($days_until != 1 ? 's' : '') . ')';
                            } else {
                                $urgency_class = 'upcoming';
                                $due_text = 'Due: ' . date('M j, Y', $due_timestamp);
                            }
                        }
                        ?>
                        <div class="task-card <?php echo esc_attr($urgency_class); ?>">
                            <div class="task-indicator"></div>
                            <div class="task-content">
                                <div class="task-description"><?php echo esc_html($description); ?></div>
                                <div class="task-due"><?php echo esc_html($due_text); ?></div>
                                <?php if ($notes): ?>
                                    <div class="task-notes"><?php echo wp_kses_post($notes); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-tasks">
                    <p>&#9989; No pending action items - you're all caught up!</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($completed_tasks)): ?>
                <div class="completed-section">
                    <h4 class="completed-title">Recently Completed</h4>
                    <div class="completed-list">
                        <?php foreach ($completed_tasks as $task):
                            $task_id = $task['id'];
                            $description = get_post_meta($task_id, 'task_description', true);
                            $completed_date = get_post_meta($task_id, 'completed_date', true);
                            ?>
                            <div class="completed-item">
                                <span class="completed-check">&#10003;</span>
                                <span class="completed-desc"><?php echo esc_html($description); ?></span>
                                <span class="completed-date">Completed <?php echo esc_html(date('M j, Y', strtotime($completed_date))); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .action-items-section {
                background: #F3F5F8;
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
            }
            .section-title {
                font-family: 'Poppins', sans-serif;
                font-size: 22px;
                font-weight: 600;
                color: #1C244B;
                margin: 0 0 16px 0;
            }
            .task-card {
                background: white;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 12px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                border-left: 4px solid #95a5a6;
            }
            .task-card.overdue {
                border-left-color: #e74c3c;
                background: #fdf2f2;
            }
            .task-card.due-soon {
                border-left-color: #f39c12;
            }
            .task-card.upcoming {
                border-left-color: #27ae60;
            }
            .task-card.no-date {
                border-left-color: #95a5a6;
            }
            .task-indicator {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-top: 4px;
                flex-shrink: 0;
            }
            .task-card.overdue .task-indicator { background: #e74c3c; }
            .task-card.due-soon .task-indicator { background: #f39c12; }
            .task-card.upcoming .task-indicator { background: #27ae60; }
            .task-card.no-date .task-indicator { background: #95a5a6; }
            .task-content {
                flex: 1;
            }
            .task-description {
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                font-weight: 500;
                color: #1C244B;
                margin-bottom: 4px;
            }
            .task-due {
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                color: #324A6D;
            }
            .task-card.overdue .task-due {
                color: #c0392b;
                font-weight: 500;
            }
            .task-notes {
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                color: #324A6D;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #eee;
            }
            .no-tasks {
                text-align: center;
                padding: 24px;
            }
            .no-tasks p {
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                color: #27ae60;
                margin: 0;
            }

            /* Recently Completed Section */
            .completed-section {
                margin-top: 24px;
                padding-top: 24px;
                border-top: 1px solid #ddd;
            }
            .completed-title {
                font-family: 'Poppins', sans-serif;
                font-size: 16px;
                font-weight: 500;
                color: #324A6D;
                margin: 0 0 12px 0;
            }
            .completed-list {
                background: white;
                border-radius: 8px;
                overflow: hidden;
            }
            .completed-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-bottom: 1px solid #f0f0f0;
                flex-wrap: wrap;
            }
            .completed-item:last-child {
                border-bottom: none;
            }
            .completed-check {
                color: #27ae60;
                font-size: 16px;
                font-weight: 600;
                flex-shrink: 0;
            }
            .completed-desc {
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                color: #324A6D;
                flex: 1;
                min-width: 0;
            }
            .completed-date {
                font-family: 'Poppins', sans-serif;
                font-size: 12px;
                color: #7f8c8d;
                flex-shrink: 0;
            }

            /* Mobile responsive - stack date below description */
            @media (max-width: 480px) {
                .completed-item {
                    flex-wrap: wrap;
                }
                .completed-desc {
                    flex-basis: calc(100% - 28px);
                }
                .completed-date {
                    flex-basis: 100%;
                    padding-left: 28px;
                    margin-top: -4px;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
