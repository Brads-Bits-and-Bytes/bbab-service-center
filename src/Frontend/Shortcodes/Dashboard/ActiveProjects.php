<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Active Projects Preview shortcode.
 *
 * Shows up to 3 most recently updated active projects with milestone progress.
 *
 * Shortcode: [dashboard_active_projects]
 * Migrated from: WPCode Snippet #1470
 */
class ActiveProjects extends BaseShortcode {

    protected string $tag = 'dashboard_active_projects';

    /**
     * Render the active projects output.
     */
    protected function output(array $atts, int $org_id): string {
        // First, get all projects for this org
        $all_projects = pods('project', [
            'where' => "organization.ID = {$org_id}",
            'limit' => -1,
        ]);

        $active_project_ids = [];

        // Filter to only Active or Waiting on Client
        while ($all_projects->fetch()) {
            $status = $all_projects->field('project_status');
            if (in_array($status, ['Active', 'Waiting on Client'], true)) {
                $active_project_ids[] = $all_projects->id();
            }
        }

        // If no active projects, show empty state
        if (empty($active_project_ids)) {
            return '<div class="bbab-active-projects bbab-empty-state">
                <h3>Your Active Projects</h3>
                <div style="background: white; border-radius: 8px; padding: 24px; text-align: center;">
                    <p style="color: #64748b; margin: 0 0 12px 0;">No active projects at the moment.</p>
                    <p style="color: #94a3b8; font-size: 14px; margin: 0;">When you have ongoing projects, they\'ll appear here with progress updates.</p>
                </div>
            </div>';
        }

        // Now get just the first 3 active projects, sorted by modified date
        $projects_pod = pods('project', [
            'where' => 't.ID IN (' . implode(',', $active_project_ids) . ')',
            'orderby' => 't.post_modified DESC',
            'limit' => 3,
        ]);

        $projects = [];

        while ($projects_pod->fetch()) {
            $project_id = $projects_pod->id();
            $project_name = $projects_pod->field('project_name');
            $status = $projects_pod->field('project_status');
            $description = $projects_pod->field('description');
            $target_completion = $projects_pod->field('target_completion');
            $client_notes = $projects_pod->field('client_visible_notes');

            // Get milestone count
            $milestones = pods('milestone', [
                'where' => "related_project.ID = {$project_id}",
                'limit' => -1,
            ]);
            $milestone_count = $milestones->total();

            // Count completed milestones (have paid invoices >= milestone amount)
            $completed_milestones = 0;
            while ($milestones->fetch()) {
                $m_id = $milestones->id();
                $m_amount = floatval($milestones->field('milestone_amount'));

                $invoices = pods('invoice', [
                    'where' => "related_milestone.ID = {$m_id}",
                    'limit' => -1,
                ]);

                $paid = 0;
                while ($invoices->fetch()) {
                    $inv_status = $invoices->field('invoice_status');
                    if ($inv_status === 'Paid') {
                        $paid += floatval($invoices->field('amount'));
                    } else {
                        $paid += floatval($invoices->field('amount_paid'));
                    }
                }

                if ($paid >= $m_amount && $m_amount > 0) {
                    $completed_milestones++;
                }
            }

            $projects[] = [
                'id' => $project_id,
                'name' => $project_name,
                'status' => $status,
                'description' => $description,
                'target_completion' => $target_completion,
                'client_notes' => $client_notes,
                'milestone_count' => $milestone_count,
                'completed_milestones' => $completed_milestones,
                'slug' => get_post_field('post_name', $project_id),
            ];
        }

        // Don't display if no active projects (shouldn't happen, but safety check)
        if (empty($projects)) {
            return '<div class="bbab-active-projects bbab-empty-state">
                <h3>Your Active Projects</h3>
                <div style="background: white; border-radius: 8px; padding: 24px; text-align: center;">
                    <p style="color: #64748b; margin: 0 0 12px 0;">No active projects at the moment.</p>
                    <p style="color: #94a3b8; font-size: 14px; margin: 0;">When you have ongoing projects, they\'ll appear here with progress updates.</p>
                </div>
            </div>';
        }

        ob_start();
        ?>
        <div class="bbab-active-projects">
            <h3>Your Active Projects</h3>

            <?php foreach ($projects as $project): ?>
                <div class="bbab-project-preview-card">
                    <div class="bbab-preview-header">
                        <h4><?php echo esc_html($project['name']); ?></h4>
                        <?php if (!empty($project['target_completion']) && strtotime($project['target_completion']) > 0): ?>
                            <span class="bbab-target-date">
                                Target: <?php echo esc_html(date('M j, Y', strtotime($project['target_completion']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($project['milestone_count'] > 0): ?>
                        <div class="bbab-milestone-progress">
                            <span class="bbab-milestone-count">
                                <?php echo esc_html($project['completed_milestones']); ?> of <?php echo esc_html($project['milestone_count']); ?> milestones complete
                            </span>
                            <div class="bbab-mini-progress-bar">
                                <?php $m_percent = $project['milestone_count'] > 0 ? ($project['completed_milestones'] / $project['milestone_count']) * 100 : 0; ?>
                                <div class="bbab-mini-progress-fill" style="width: <?php echo esc_attr($m_percent); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($project['client_notes'])): ?>
                        <div class="bbab-latest-update">
                            <span class="bbab-update-label">Latest Update:</span>
                            <div class="bbab-update-content">
                                <?php echo esc_html(wp_trim_words(strip_tags($project['client_notes']), 20, '...')); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a href="/project/<?php echo esc_attr($project['slug']); ?>/" class="bbab-view-project-link">
                        View Project Details &rarr;
                    </a>
                </div>
            <?php endforeach; ?>

            <a href="/projects/" class="bbab-view-all-projects-link">View All Projects &rarr;</a>
        </div>

        <style>
            .bbab-active-projects {
                background: #F3F5F8;
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
                font-family: 'Poppins', sans-serif;
            }
            .bbab-active-projects h3 {
                font-size: 22px;
                font-weight: 600;
                color: #1C244B;
                margin: 0 0 16px 0;
            }
            .bbab-project-preview-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 12px;
            }
            .bbab-project-preview-card:last-child {
                margin-bottom: 0;
            }
            .bbab-preview-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                flex-wrap: wrap;
                gap: 8px;
            }
            .bbab-preview-header h4 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
                color: #1C244B;
            }
            .bbab-target-date {
                font-size: 13px;
                color: #7f8c8d;
            }
            .bbab-milestone-progress {
                margin-bottom: 12px;
            }
            .bbab-milestone-count {
                font-size: 13px;
                color: #324A6D;
                display: block;
                margin-bottom: 6px;
            }
            .bbab-mini-progress-bar {
                height: 6px;
                background: #e0e0e0;
                border-radius: 3px;
                overflow: hidden;
            }
            .bbab-mini-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #3498db 0%, #2ecc71 100%);
                border-radius: 3px;
                transition: width 0.3s ease;
            }
            .bbab-latest-update {
                background: #f9f9f9;
                border-left: 3px solid #467FF7;
                padding: 10px 12px;
                margin-bottom: 12px;
                border-radius: 0 6px 6px 0;
            }
            .bbab-update-label {
                font-size: 11px;
                font-weight: 600;
                color: #467FF7;
                text-transform: uppercase;
                display: block;
                margin-bottom: 4px;
            }
            .bbab-update-content {
                font-size: 14px;
                color: #324A6D;
                line-height: 1.4;
            }
            .bbab-view-project-link {
                display: inline-block;
                font-size: 14px;
                font-weight: 500;
                color: #467FF7;
                text-decoration: none;
            }
            .bbab-view-project-link:hover {
                text-decoration: underline;
            }

            .bbab-view-all-projects-link {
                display: block;
                text-align: center;
                color: #467FF7;
                text-decoration: none;
                margin-top: 16px;
                font-family: 'Poppins', sans-serif;
                font-size: 14px;
                font-weight: 500;
            }
            .bbab-view-all-projects-link:hover {
                text-decoration: underline;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
