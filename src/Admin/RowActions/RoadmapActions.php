<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Admin\RowActions;

use BBAB\ServiceCenter\Utils\Logger;

/**
 * Custom row actions for Roadmap Items.
 *
 * Handles:
 * - "Start ADR" - Changes Idea status to ADR In Progress
 * - "Create Project" - Creates a linked Project from Approved items
 *
 * Migrated from: WPCode Snippet #1930
 */
class RoadmapActions {

    /**
     * Register all hooks.
     */
    public static function register(): void {
        // Add row actions
        add_filter('post_row_actions', [self::class, 'addRowActions'], 10, 2);

        // Handle action requests
        add_action('admin_post_roadmap_start_adr', [self::class, 'handleStartAdr']);
        add_action('admin_post_roadmap_create_project', [self::class, 'handleCreateProject']);

        // Admin notices for action results
        add_action('admin_notices', [self::class, 'showActionNotices']);

        Logger::debug('RoadmapActions', 'Registered roadmap row action hooks');
    }

    /**
     * Add custom row actions.
     *
     * @param array    $actions Existing actions.
     * @param \WP_Post $post    Current post.
     * @return array Modified actions.
     */
    public static function addRowActions(array $actions, \WP_Post $post): array {
        if ($post->post_type !== 'roadmap_item') {
            return $actions;
        }

        $pod = function_exists('pods') ? pods('roadmap_item', $post->ID) : null;
        $status = $pod ? $pod->field('roadmap_status') : get_post_meta($post->ID, 'roadmap_status', true);

        // "Start ADR" - only on Idea status
        if ($status === 'Idea') {
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=roadmap_start_adr&post_id=' . $post->ID),
                'roadmap_start_adr_' . $post->ID
            );
            $actions['start_adr'] = '<a href="' . esc_url($url) . '" style="color: #b45309;">Start ADR</a>';
        }

        // "Create Project" - only on Approved status with no linked project
        if ($status === 'Approved') {
            $related_project = $pod ? $pod->field('related_project') : get_post_meta($post->ID, 'related_project', true);

            if (empty($related_project)) {
                $url = wp_nonce_url(
                    admin_url('admin-post.php?action=roadmap_create_project&post_id=' . $post->ID),
                    'roadmap_create_project_' . $post->ID
                );
                $actions['create_project'] = '<a href="' . esc_url($url) . '" style="color: #059669;">Create Project</a>';
            }
        }

        return $actions;
    }

    /**
     * Handle "Start ADR" action.
     */
    public static function handleStartAdr(): void {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (!$post_id) {
            wp_die('Invalid request: No post ID provided');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'roadmap_start_adr_' . $post_id)) {
            wp_die('Invalid request: Nonce verification failed');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Permission denied: You cannot edit this post');
        }

        // Update status
        update_post_meta($post_id, 'roadmap_status', 'ADR In Progress');

        Logger::info('RoadmapActions', 'Started ADR for roadmap item', ['post_id' => $post_id]);

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'post_type' => 'roadmap_item',
            'roadmap_action' => 'adr_started',
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * Handle "Create Project" action.
     */
    public static function handleCreateProject(): void {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        if (!$post_id) {
            wp_die('Invalid request: No post ID provided');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'roadmap_create_project_' . $post_id)) {
            wp_die('Invalid request: Nonce verification failed');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Permission denied: You cannot edit this post');
        }

        $pod = function_exists('pods') ? pods('roadmap_item', $post_id) : null;
        $title = get_the_title($post_id);

        // Get organization ID
        $org_id = null;
        if ($pod) {
            $org = $pod->field('organization');
            $org_id = $org['ID'] ?? null;
        }
        if (!$org_id) {
            $org_id = get_post_meta($post_id, 'organization', true);
        }

        // Get description
        $description = $pod ? $pod->field('description') : get_post_meta($post_id, 'description', true);

        // Create the Project
        $project_id = wp_insert_post([
            'post_type' => 'project',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if ($project_id && !is_wp_error($project_id)) {
            // Set project fields
            if ($org_id) {
                update_post_meta($project_id, 'organization', $org_id);
            }
            update_post_meta($project_id, 'project_status', 'Planned');
            update_post_meta($project_id, 'scope_summary', $description);

            // Link roadmap item to project
            update_post_meta($post_id, 'related_project', $project_id);

            Logger::info('RoadmapActions', 'Created project from roadmap item', [
                'roadmap_id' => $post_id,
                'project_id' => $project_id,
            ]);

            // Redirect straight to the new project edit screen
            wp_redirect(add_query_arg([
                'action' => 'edit',
                'post' => $project_id,
                'roadmap_linked' => '1',
            ], admin_url('post.php')));
        } else {
            Logger::error('RoadmapActions', 'Failed to create project from roadmap item', [
                'roadmap_id' => $post_id,
                'error' => is_wp_error($project_id) ? $project_id->get_error_message() : 'Unknown error',
            ]);

            wp_redirect(add_query_arg([
                'post_type' => 'roadmap_item',
                'roadmap_action' => 'project_error',
            ], admin_url('edit.php')));
        }
        exit;
    }

    /**
     * Show admin notices for action results.
     */
    public static function showActionNotices(): void {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // Show roadmap action notices on roadmap list
        if ($screen->post_type === 'roadmap_item' && $screen->base === 'edit') {
            $action = isset($_GET['roadmap_action']) ? sanitize_text_field($_GET['roadmap_action']) : '';

            if ($action === 'adr_started') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>Roadmap item moved to <strong>ADR In Progress</strong>.</p>';
                echo '</div>';
            }

            if ($action === 'project_error') {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>Failed to create project. Please try again.</p>';
                echo '</div>';
            }
        }

        // Show linked notice on project edit screen
        if ($screen->post_type === 'project' && $screen->base === 'post') {
            $linked = isset($_GET['roadmap_linked']) ? sanitize_text_field($_GET['roadmap_linked']) : '';

            if ($linked === '1') {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>Project created and linked to roadmap item!</p>';
                echo '</div>';
            }
        }
    }
}
