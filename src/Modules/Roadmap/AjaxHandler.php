<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Modules\Roadmap;

use BBAB\ServiceCenter\Utils\UserContext;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * AJAX handlers for client roadmap interactions.
 *
 * Handles:
 * - "I'm Interested" - Moves Idea to ADR In Progress
 * - "Decline" - Marks item as Declined
 * - "Approve" - Marks proposed item as Approved
 *
 * All actions verify org membership before processing.
 *
 * Migrated from: WPCode Snippet #1953
 */
class AjaxHandler {

    /**
     * Register AJAX hooks.
     */
    public static function register(): void {
        // Register AJAX actions (logged-in users only)
        add_action('wp_ajax_roadmap_interested', [self::class, 'handleInterested']);
        add_action('wp_ajax_roadmap_decline', [self::class, 'handleDecline']);
        add_action('wp_ajax_roadmap_approve', [self::class, 'handleApprove']);

        Logger::debug('RoadmapAjaxHandler', 'Registered AJAX handlers');
    }

    /**
     * Handle "I'm Interested" action.
     *
     * Moves item from Idea to ADR In Progress.
     */
    public static function handleInterested(): void {
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

        // Validate request
        $validation = self::validateRequest($item_id, 'interest');
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
            return;
        }

        // Update status to ADR In Progress
        update_post_meta($item_id, 'roadmap_status', 'ADR In Progress');

        Logger::info('RoadmapAjax', 'Client expressed interest', [
            'item_id' => $item_id,
            'user_id' => get_current_user_id(),
        ]);

        // Send email notification
        self::sendInterestEmail($item_id);

        wp_send_json_success(['message' => 'Thanks! Brad will prepare a proposal.']);
    }

    /**
     * Handle "Decline" action.
     *
     * Works for both Idea and Proposed stages.
     */
    public static function handleDecline(): void {
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

        // Validate request
        $validation = self::validateRequest($item_id, 'decline');
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
            return;
        }

        // Update status to Declined
        update_post_meta($item_id, 'roadmap_status', 'Declined');
        update_post_meta($item_id, 'declined_date', current_time('Y-m-d'));

        Logger::info('RoadmapAjax', 'Client declined item', [
            'item_id' => $item_id,
            'user_id' => get_current_user_id(),
        ]);

        wp_send_json_success(['message' => 'Got it. Maybe another time!']);
    }

    /**
     * Handle "Approve" action.
     *
     * Moves item from Proposed to Approved.
     */
    public static function handleApprove(): void {
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

        // Validate request
        $validation = self::validateRequest($item_id, 'approve');
        if (is_wp_error($validation)) {
            wp_send_json_error(['message' => $validation->get_error_message()]);
            return;
        }

        // Update status to Approved
        update_post_meta($item_id, 'roadmap_status', 'Approved');
        update_post_meta($item_id, 'approved_date', current_time('Y-m-d'));

        Logger::info('RoadmapAjax', 'Client approved proposal', [
            'item_id' => $item_id,
            'user_id' => get_current_user_id(),
        ]);

        // Send email notification
        self::sendApprovalEmail($item_id);

        wp_send_json_success(['message' => 'Approved! Brad will be in touch about next steps.']);
    }

    /**
     * Validate AJAX request.
     *
     * @param int    $item_id   Roadmap item ID.
     * @param string $action    Action type for nonce verification.
     * @return true|\WP_Error True if valid, WP_Error if not.
     */
    private static function validateRequest(int $item_id, string $action) {
        if (!$item_id) {
            return new \WP_Error('invalid_request', 'Invalid request: No item ID');
        }

        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'roadmap_' . $action . '_' . $item_id)) {
            return new \WP_Error('invalid_nonce', 'Invalid request: Security check failed');
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            return new \WP_Error('not_logged_in', 'You must be logged in');
        }

        // Verify user belongs to this item's org
        $user_org = UserContext::getCurrentOrgId();
        $item_org = get_post_meta($item_id, 'organization', true);

        if (!$user_org || $user_org != $item_org) {
            Logger::warning('RoadmapAjax', 'Org mismatch on AJAX request', [
                'item_id' => $item_id,
                'user_org' => $user_org,
                'item_org' => $item_org,
            ]);
            return new \WP_Error('permission_denied', 'Permission denied');
        }

        return true;
    }

    /**
     * Send email notification when client expresses interest.
     *
     * @param int $item_id Roadmap item ID.
     */
    private static function sendInterestEmail(int $item_id): void {
        $user_id = get_current_user_id();
        $user_org = UserContext::getCurrentOrgId();

        $item_title = get_the_title($item_id);
        $org_title = get_the_title($user_org);
        $user_info = get_userdata($user_id);

        $to = get_option('admin_email');
        $subject = 'Client Interested: ' . $item_title;

        $message = "A client has expressed interest in a roadmap idea!\n\n";
        $message .= "Feature: {$item_title}\n";
        $message .= "Client: {$org_title}\n";
        $message .= "Expressed by: " . ($user_info ? $user_info->display_name : 'Unknown') . "\n\n";
        $message .= "The item has been moved to 'ADR In Progress'.\n\n";
        $message .= "View it here: " . admin_url('post.php?post=' . $item_id . '&action=edit');

        wp_mail($to, $subject, $message);
    }

    /**
     * Send email notification when client approves proposal.
     *
     * @param int $item_id Roadmap item ID.
     */
    private static function sendApprovalEmail(int $item_id): void {
        $user_id = get_current_user_id();
        $user_org = UserContext::getCurrentOrgId();

        $item_title = get_the_title($item_id);
        $org_title = get_the_title($user_org);
        $user_info = get_userdata($user_id);

        $to = get_option('admin_email');
        $subject = 'Proposal Approved: ' . $item_title;

        $message = "A client has approved a roadmap proposal!\n\n";
        $message .= "Feature: {$item_title}\n";
        $message .= "Client: {$org_title}\n";
        $message .= "Approved by: " . ($user_info ? $user_info->display_name : 'Unknown') . "\n\n";
        $message .= "You can now create a Project for this work.\n\n";
        $message .= "View it here: " . admin_url('post.php?post=' . $item_id . '&action=edit');

        wp_mail($to, $subject, $message);
    }
}
