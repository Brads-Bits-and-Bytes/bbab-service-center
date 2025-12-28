<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Core;

use BBAB\ServiceCenter\Utils\UserContext;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Organization Query Filter.
 *
 * Filters frontend queries to show only content belonging to the current
 * user's organization. This is a security layer that ensures clients never
 * see other organizations' data, even if they somehow access a page.
 *
 * Handles:
 * - Elementor Posts widget queries (via custom query IDs)
 * - WP_Query pre_get_posts for CPT archives
 *
 * Migrated from: WPCode Snippet #1056
 */
class OrgQueryFilter {

    /**
     * CPTs that should be filtered by organization.
     */
    private const FILTERED_CPTS = [
        'service_request',
        'project',
        'milestone',
        'invoice',
        'monthly_report',
        'roadmap_item',
        'client_task',
    ];

    /**
     * Register query filter hooks.
     */
    public function register(): void {
        // Elementor query filters
        add_action('elementor/query/client_org_reports', [$this, 'filterElementorQuery']);
        add_action('elementor/query/client_org_projects', [$this, 'filterElementorQuery']);
        add_action('elementor/query/client_org_invoices', [$this, 'filterElementorQuery']);
        add_action('elementor/query/client_org_requests', [$this, 'filterElementorQuery']);

        // Generic pre_get_posts for archive pages
        add_action('pre_get_posts', [$this, 'filterArchiveQueries']);

        Logger::debug('OrgQueryFilter', 'Registered organization query filters');
    }

    /**
     * Filter Elementor Posts widget queries by organization.
     *
     * Uses direct DB query to avoid conflicts with Elementor's own query hooks.
     *
     * @param \WP_Query $query The query object.
     */
    public function filterElementorQuery(\WP_Query $query): void {
        // Get current org (simulation-aware)
        $org_id = UserContext::getCurrentOrgId();

        if (!$org_id) {
            // No org - return no results
            $query->set('post__in', [0]);
            Logger::debug('OrgQueryFilter', 'Elementor query blocked - no org context');
            return;
        }

        // Get posts belonging to this org using direct DB query
        // This avoids recursive query issues with Elementor
        global $wpdb;

        $org_post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = 'organization'
             AND meta_value = %s",
            (string) $org_id
        ));

        if (!empty($org_post_ids)) {
            $query->set('post__in', array_map('intval', $org_post_ids));
        } else {
            // No posts for this org
            $query->set('post__in', [0]);
        }

        Logger::debug('OrgQueryFilter', 'Elementor query filtered', [
            'org_id' => $org_id,
            'post_count' => count($org_post_ids),
        ]);
    }

    /**
     * Filter archive queries for client CPTs.
     *
     * Only applies on frontend, non-admin, main queries for our CPTs.
     *
     * @param \WP_Query $query The query object.
     */
    public function filterArchiveQueries(\WP_Query $query): void {
        // Only filter frontend queries
        if (is_admin()) {
            return;
        }

        // Only filter main queries on archives
        if (!$query->is_main_query()) {
            return;
        }

        // Only filter archive pages
        if (!$query->is_archive() && !$query->is_post_type_archive()) {
            return;
        }

        // Check if this is one of our filtered CPTs
        $post_type = $query->get('post_type');
        if (!in_array($post_type, self::FILTERED_CPTS, true)) {
            return;
        }

        // Admins see everything
        if (current_user_can('manage_options')) {
            return;
        }

        // Get current org
        $org_id = UserContext::getCurrentOrgId();

        if (!$org_id) {
            // No org - return no results
            $query->set('post__in', [0]);
            Logger::debug('OrgQueryFilter', 'Archive query blocked - no org context', [
                'post_type' => $post_type,
            ]);
            return;
        }

        // Add org filter to query
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => 'organization',
            'value' => $org_id,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta_query);

        Logger::debug('OrgQueryFilter', 'Archive query filtered by org', [
            'post_type' => $post_type,
            'org_id' => $org_id,
        ]);
    }
}
