<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Frontend\Shortcodes\Dashboard;

use BBAB\ServiceCenter\Frontend\Shortcodes\BaseShortcode;

/**
 * Dashboard Roadmap shortcode.
 *
 * Displays feature requests/suggestions for client's organization.
 * Shows Brad's ideas, client submissions, in-progress items, and past requests.
 *
 * Shortcode: [dashboard_roadmap]
 * Migrated from: WPCode Snippet #1946
 */
class Roadmap extends BaseShortcode {

    protected string $tag = 'dashboard_roadmap';

    /**
     * Render the roadmap output.
     */
    protected function output(array $atts, int $org_id): string {
        // Get admin user IDs (for distinguishing Brad's ideas from client submissions)
        // Cast to int since get_users returns strings
        $admin_users = array_map('intval', get_users(['role' => 'administrator', 'fields' => 'ID']));

        // First get ALL Idea status items, then filter in PHP
        // This avoids complex meta_query issues with Pods-stored vs standard meta
        $all_ideas = get_posts([
            'post_type' => 'roadmap_item',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'roadmap_status',
                    'value' => 'Idea',
                    'compare' => '=',
                ],
            ],
        ]);

        // Filter by organization in PHP (handles both Pods and standard meta)
        $brads_ideas = [];
        $client_ideas = [];

        foreach ($all_ideas as $item) {
            // Get organization - try both Pods and raw meta
            $item_org = null;
            if (function_exists('pods')) {
                $pod = pods('roadmap_item', $item->ID);
                $org_field = $pod->field('organization');
                if (is_array($org_field) && !empty($org_field['ID'])) {
                    $item_org = (int) $org_field['ID'];
                } elseif (is_numeric($org_field)) {
                    $item_org = (int) $org_field;
                }
            }
            if (!$item_org) {
                $item_org = (int) get_post_meta($item->ID, 'organization', true);
            }

            // Skip if not matching org
            if ($item_org !== $org_id) {
                continue;
            }

            // Get submitted_by
            $submitted_by = null;
            if (function_exists('pods')) {
                $pod = pods('roadmap_item', $item->ID);
                $user_field = $pod->field('submitted_by');
                if (is_array($user_field) && !empty($user_field['ID'])) {
                    $submitted_by = (int) $user_field['ID'];
                } elseif (is_numeric($user_field)) {
                    $submitted_by = (int) $user_field;
                }
            }
            if (!$submitted_by) {
                $submitted_by = (int) get_post_meta($item->ID, 'submitted_by', true);
            }

            // Categorize: Brad's ideas vs Client ideas
            if (empty($submitted_by) || in_array($submitted_by, $admin_users)) {
                $brads_ideas[] = $item;
            } else {
                $client_ideas[] = $item;
            }
        }

        // Query: In Progress items (ADR In Progress or Proposed)
        $all_in_progress = get_posts([
            'post_type' => 'roadmap_item',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'roadmap_status',
                    'value' => ['ADR In Progress', 'Proposed'],
                    'compare' => 'IN',
                ],
            ],
        ]);

        // Filter in_progress by org
        $in_progress = array_filter($all_in_progress, function($item) use ($org_id) {
            return self::getItemOrgId($item->ID) === $org_id;
        });

        // Query: Past requests (Approved or Declined)
        $all_past = get_posts([
            'post_type' => 'roadmap_item',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'roadmap_status',
                    'value' => ['Approved', 'Declined'],
                    'compare' => 'IN',
                ],
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'approved_date',
            'order' => 'DESC',
        ]);

        // Filter past_requests by org
        $past_requests = array_filter($all_past, function($item) use ($org_id) {
            return self::getItemOrgId($item->ID) === $org_id;
        });

        ob_start();
        ?>

        <div class="roadmap-dashboard">
            <div class="roadmap-section-header">
                <h3>Feature Roadmap</h3>
                <a href="/feature-request/" class="new-feature-btn">+ Submit Idea</a>
            </div>

            <?php if (!empty($brads_ideas)): ?>
            <div class="roadmap-section brads-ideas">
                <h3>&#128161; Ideas for Your Review</h3>
                <p class="section-desc">Brad has some suggestions for improving your site. Let us know what interests you!</p>
                <div class="roadmap-cards">
                    <?php foreach ($brads_ideas as $item):
                        $pod = pods('roadmap_item', $item->ID);
                        $description = $pod->field('description');
                        $truncated = wp_trim_words(wp_strip_all_tags($description), 30, '...');
                    ?>
                    <div class="roadmap-card idea-card" data-item-id="<?php echo esc_attr($item->ID); ?>">
                        <h4><?php echo esc_html($item->post_title); ?></h4>
                        <p class="card-description"><?php echo esc_html($truncated); ?></p>
                        <div class="card-actions">
                            <button type="button" class="btn-interested" data-item-id="<?php echo esc_attr($item->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('roadmap_interest_' . $item->ID)); ?>">&#128077; I'm Interested</button>
                            <button type="button" class="btn-not-now" data-item-id="<?php echo esc_attr($item->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('roadmap_decline_' . $item->ID)); ?>">Not Right Now</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($client_ideas)): ?>
            <div class="roadmap-section client-ideas">
                <h3>&#128172; Your Submitted Ideas</h3>
                <div class="roadmap-cards">
                    <?php foreach ($client_ideas as $item):
                        $pod = pods('roadmap_item', $item->ID);
                        $description = $pod->field('description');
                        $truncated = wp_trim_words(wp_strip_all_tags($description), 30, '...');
                    ?>
                    <div class="roadmap-card submitted-card">
                        <h4><?php echo esc_html($item->post_title); ?></h4>
                        <p class="card-description"><?php echo esc_html($truncated); ?></p>
                        <span class="status-badge pending">&#9203; Pending Review</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($in_progress)): ?>
            <div class="roadmap-section in-progress">
                <h3>&#128736; In Progress</h3>
                <div class="roadmap-cards">
                    <?php foreach ($in_progress as $item):
                        $pod = pods('roadmap_item', $item->ID);
                        $status = $pod->field('roadmap_status');
                        $description = $pod->field('description');
                        $truncated = wp_trim_words(wp_strip_all_tags($description), 30, '...');
                        $estimated_hours = $pod->field('estimated_hours');
                        $adr_pdf = $pod->field('adr_pdf');
                    ?>
                    <div class="roadmap-card progress-card" data-item-id="<?php echo esc_attr($item->ID); ?>">
                        <h4><?php echo esc_html($item->post_title); ?></h4>
                        <p class="card-description"><?php echo esc_html($truncated); ?></p>

                        <?php if ($status === 'ADR In Progress'): ?>
                            <span class="status-badge adr-progress">&#128221; Brad is preparing a proposal</span>
                        <?php elseif ($status === 'Proposed'): ?>
                            <span class="status-badge proposed">&#128230; Ready for your review</span>
                            <?php if ($estimated_hours): ?>
                                <p class="estimated-hours">Estimated: <?php echo esc_html($estimated_hours); ?> hours</p>
                            <?php endif; ?>
                            <?php if ($adr_pdf && !empty($adr_pdf['guid'])): ?>
                                <a href="<?php echo esc_url($adr_pdf['guid']); ?>" target="_blank" class="btn-view-proposal">&#128196; View Proposal</a>
                            <?php endif; ?>
                            <div class="card-actions proposal-actions">
                                <button type="button" class="btn-approve" data-item-id="<?php echo esc_attr($item->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('roadmap_approve_' . $item->ID)); ?>">&#9989; Approve</button>
                                <button type="button" class="btn-decline" data-item-id="<?php echo esc_attr($item->ID); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('roadmap_decline_' . $item->ID)); ?>">&#10060; Decline</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($past_requests)): ?>
            <div class="roadmap-section past-requests">
                <h3 class="collapsible-header" onclick="this.parentElement.classList.toggle('expanded')">
                    &#128218; Past Requests <span class="toggle-icon">&#9654;</span>
                </h3>
                <div class="collapsible-content">
                    <table class="past-requests-table">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Project</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_requests as $item):
                                $pod = pods('roadmap_item', $item->ID);
                                $status = $pod->field('roadmap_status');
                                $approved_date = $pod->field('approved_date');
                                $declined_date = $pod->field('declined_date');
                                $related_project = $pod->field('related_project');
                                $date = ($status === 'Approved') ? $approved_date : $declined_date;
                            ?>
                            <tr>
                                <td><?php echo esc_html($item->post_title); ?></td>
                                <td>
                                    <?php if ($status === 'Approved'): ?>
                                        <span class="status-badge approved">&#9989; Approved</span>
                                    <?php else: ?>
                                        <span class="status-badge declined">&#10060; Declined</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $date ? esc_html(date('M j, Y', strtotime($date))) : '—'; ?></td>
                                <td>
                                    <?php if ($related_project): ?>
                                        <a href="<?php echo esc_url(get_permalink($related_project['ID'])); ?>">View Project</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($brads_ideas) && empty($client_ideas) && empty($in_progress) && empty($past_requests)): ?>
            <div class="roadmap-empty">
                <p>No roadmap items yet. Have an idea for improving your site? <a href="/feature-request/">Submit a suggestion!</a></p>
            </div>
            <?php endif; ?>

        </div>

        <style>
        .roadmap-dashboard {
            background: #F3F5F8;
            border-radius: 12px;
            padding: 24px;
        }
        .roadmap-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .roadmap-section-header h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 22px;
            font-weight: 600;
            color: #1C244B;
            margin: 0;
        }
        .new-feature-btn {
            background: #467FF7;
            color: white !important;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .new-feature-btn:hover {
            background: #3366cc;
            color: white !important;
        }

        .roadmap-section {
            margin-bottom: 30px;
        }

        .roadmap-section h3 {
            margin-bottom: 10px;
            font-size: 1.3em;
        }

        .section-desc {
            color: #666;
            margin-bottom: 15px;
        }

        .roadmap-cards {
            display: grid;
            gap: 15px;
        }

        .roadmap-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .roadmap-card h4 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
        }

        .card-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .card-actions button, .btn-view-proposal {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-interested, .btn-approve {
            background: #059669;
            color: white;
        }

        .btn-interested:hover, .btn-approve:hover {
            background: #047857;
        }

        .btn-not-now, .btn-decline {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-not-now:hover, .btn-decline:hover {
            background: #e5e7eb;
        }

        .btn-view-proposal {
            background: #3b82f6;
            color: white;
            margin-bottom: 10px;
        }

        .btn-view-proposal:hover {
            background: #2563eb;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.adr-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.proposed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.declined {
            background: #fee2e2;
            color: #991b1b;
        }

        .estimated-hours {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }

        /* Collapsible past requests */
        .past-requests .collapsible-header {
            cursor: pointer;
            user-select: none;
        }

        .past-requests .toggle-icon {
            font-size: 0.8em;
            margin-left: 5px;
            transition: transform 0.2s;
        }

        .past-requests.expanded .toggle-icon {
            transform: rotate(90deg);
        }

        .past-requests .collapsible-content {
            display: none;
            margin-top: 15px;
        }

        .past-requests.expanded .collapsible-content {
            display: block;
        }

        .past-requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .past-requests-table th,
        .past-requests-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .past-requests-table th {
            background: #f9fafb;
            font-weight: 600;
        }

        .roadmap-empty {
            background: #f9fafb;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
        }
        </style>

        <?php
        return ob_get_clean();
    }

    /**
     * Get organization ID for a roadmap item.
     *
     * Handles both Pods-stored and standard meta values.
     *
     * @param int $item_id Roadmap item ID.
     * @return int Organization ID or 0 if not found.
     */
    private static function getItemOrgId(int $item_id): int {
        $org_id = null;

        // Try Pods first
        if (function_exists('pods')) {
            $pod = pods('roadmap_item', $item_id);
            $org_field = $pod->field('organization');
            if (is_array($org_field) && !empty($org_field['ID'])) {
                $org_id = (int) $org_field['ID'];
            } elseif (is_numeric($org_field)) {
                $org_id = (int) $org_field;
            }
        }

        // Fallback to raw meta
        if (!$org_id) {
            $org_id = (int) get_post_meta($item_id, 'organization', true);
        }

        return $org_id ?: 0;
    }
}
