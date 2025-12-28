<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Core;

use BBAB\ServiceCenter\Utils\UserContext;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * CPT Single Page Access Control.
 *
 * Restricts access to single CPT pages by organization.
 * Uses template_redirect to prevent content from ever being generated
 * for unauthorized users.
 *
 * Protected CPTs:
 * - project
 * - milestone
 * - invoice
 * - monthly_report
 * - roadmap_item
 * - client_task
 *
 * Note: service_request is handled by its own AccessControl class
 * (Frontend/Shortcodes/ServiceRequests/AccessControl.php)
 *
 * Migrated from: WPCode Snippet #1134 (extended to all client CPTs)
 */
class CPTAccessControl {

    /**
     * CPTs protected by this access control.
     * Maps CPT slug to redirect URL.
     */
    private const PROTECTED_CPTS = [
        'project' => '/projects/',
        'milestone' => '/projects/',
        'invoice' => '/billing/',
        'monthly_report' => '/billing/',
        'roadmap_item' => '/roadmap/',
        'client_task' => '/client-dashboard/',
    ];

    /**
     * Register access control hook.
     */
    public function register(): void {
        add_action('template_redirect', [$this, 'restrictAccess']);

        Logger::debug('CPTAccessControl', 'Registered CPT access control hook');
    }

    /**
     * Restrict access to single CPT pages.
     */
    public function restrictAccess(): void {
        // Only check on singular pages
        global $post;
        if (!$post || !is_singular()) {
            return;
        }

        $post_type = get_post_type($post);

        // Check if this is a protected CPT
        if (!isset(self::PROTECTED_CPTS[$post_type])) {
            return;
        }

        // Must be logged in
        if (!is_user_logged_in()) {
            Logger::debug('CPTAccessControl', 'Unauthenticated access denied', [
                'post_type' => $post_type,
                'post_id' => $post->ID,
            ]);
            wp_redirect(wp_login_url(get_permalink($post->ID)));
            exit;
        }

        // Admins can see everything (including simulation mode)
        if (current_user_can('manage_options')) {
            return;
        }

        // Get user's org (simulation-aware)
        $user_org_id = UserContext::getCurrentOrgId();

        // Get post's organization
        $post_org_id = $this->getPostOrgId($post);

        // Check access
        if (empty($user_org_id) || (string) $user_org_id !== (string) $post_org_id) {
            Logger::warning('CPTAccessControl', 'Access denied - org mismatch', [
                'post_type' => $post_type,
                'post_id' => $post->ID,
                'post_org' => $post_org_id,
                'user_org' => $user_org_id,
                'user_id' => get_current_user_id(),
            ]);

            $redirect_path = self::PROTECTED_CPTS[$post_type];
            wp_redirect(home_url($redirect_path));
            exit;
        }

        Logger::debug('CPTAccessControl', 'Access granted', [
            'post_type' => $post_type,
            'post_id' => $post->ID,
            'org_id' => $user_org_id,
        ]);
    }

    /**
     * Get the organization ID for a post.
     *
     * Handles both direct organization field and related items
     * (e.g., milestone → project → organization).
     *
     * @param \WP_Post $post The post object.
     * @return int|string|null The organization ID or null.
     */
    private function getPostOrgId(\WP_Post $post) {
        $post_type = get_post_type($post);

        // Direct organization relationship
        $org_id = get_post_meta($post->ID, 'organization', true);

        if (!empty($org_id)) {
            return $org_id;
        }

        // Handle nested relationships
        switch ($post_type) {
            case 'milestone':
                // Milestone → Project → Organization
                $project_id = get_post_meta($post->ID, 'related_project', true);
                if ($project_id) {
                    return get_post_meta((int) $project_id, 'organization', true);
                }
                break;
        }

        return null;
    }
}
