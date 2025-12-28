<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Modules\Roadmap;

use BBAB\ServiceCenter\Utils\Settings;
use BBAB\ServiceCenter\Utils\UserContext;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Process WPForms Feature Request submissions.
 *
 * Creates Roadmap Items from form submissions with:
 * - Title and description from form fields
 * - Auto-assigned organization from submitting user
 * - Initial "Idea" status
 * - Email notification to admin
 *
 * Migrated from: WPCode Snippet #1937
 */
class FormProcessor {

    /**
     * Register hooks.
     */
    public static function register(): void {
        // Use the generic wpforms_process_complete hook and check form ID inside
        add_action('wpforms_process_complete', [self::class, 'processSubmission'], 10, 4);

        Logger::debug('RoadmapFormProcessor', 'Registered WPForms handler');
    }

    /**
     * Process roadmap form submission.
     *
     * @param array $fields    Form fields.
     * @param array $entry     Entry data.
     * @param array $form_data Form configuration.
     * @param int   $entry_id  Entry ID.
     */
    public static function processSubmission(array $fields, array $entry, array $form_data, int $entry_id): void {
        // Check if this is the roadmap form
        $roadmap_form_id = Settings::get('roadmap_form_id');

        if (!$roadmap_form_id || (int) $form_data['id'] !== (int) $roadmap_form_id) {
            return;
        }

        Logger::info('RoadmapFormProcessor', 'Processing roadmap form submission', [
            'form_id' => $form_data['id'],
            'entry_id' => $entry_id,
        ]);

        // Verify user is logged in
        if (!is_user_logged_in()) {
            Logger::warning('RoadmapFormProcessor', 'Form submitted by non-logged-in user');
            return;
        }

        $user_id = get_current_user_id();
        $org_id = UserContext::getCurrentOrgId();

        // Must have organization assigned
        if (empty($org_id)) {
            Logger::warning('RoadmapFormProcessor', 'User has no organization', ['user_id' => $user_id]);
            return;
        }

        // Extract form fields by label (resilient to field ID changes)
        $title = '';
        $description = '';

        foreach ($fields as $field_id => $field_data) {
            $label = strtolower(trim($field_data['name'] ?? ''));
            $value = $field_data['value'] ?? '';

            if ($label === 'feature title' || $label === 'title') {
                $title = sanitize_text_field($value);
            } elseif ($label === 'description') {
                $description = sanitize_textarea_field($value);
            }
        }

        // Validate required fields
        if (empty($title)) {
            Logger::warning('RoadmapFormProcessor', 'Form submitted without title');
            return;
        }

        // Create the Roadmap Item
        $post_id = wp_insert_post([
            'post_type' => 'roadmap_item',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if (!$post_id || is_wp_error($post_id)) {
            Logger::error('RoadmapFormProcessor', 'Failed to create roadmap item', [
                'error' => is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error',
            ]);
            return;
        }

        // Set roadmap item fields using standard meta (consistent with ServiceRequests FormProcessor)
        update_post_meta($post_id, 'description', $description);
        update_post_meta($post_id, 'roadmap_status', 'Idea');
        update_post_meta($post_id, 'organization', $org_id);
        update_post_meta($post_id, 'submitted_by', $user_id);
        update_post_meta($post_id, 'submitted_date', current_time('Y-m-d'));
        update_post_meta($post_id, 'priority', 'Medium');

        Logger::info('RoadmapFormProcessor', 'Created roadmap item from form', [
            'post_id' => $post_id,
            'user_id' => $user_id,
            'org_id' => $org_id,
        ]);

        // Send email notification to admin
        self::sendNotificationEmail($post_id, $title, $description, $user_id, $org_id);
    }

    /**
     * Send notification email to admin.
     *
     * @param int    $post_id     Roadmap item ID.
     * @param string $title       Feature title.
     * @param string $description Feature description.
     * @param int    $user_id     Submitting user ID.
     * @param int    $org_id      Organization ID.
     */
    private static function sendNotificationEmail(int $post_id, string $title, string $description, int $user_id, int $org_id): void {
        $user_info = get_userdata($user_id);
        $org_title = get_the_title($org_id);

        $to = get_option('admin_email');
        $subject = 'New Feature Request from ' . $org_title;

        $message = "A client has submitted a new feature request.\n\n";
        $message .= "Title: {$title}\n";
        $message .= "Client: {$org_title}\n";
        $message .= "Submitted by: " . ($user_info ? $user_info->display_name : 'Unknown') . "\n\n";
        $message .= "Description:\n{$description}\n\n";
        $message .= "Review it here: " . admin_url('post.php?post=' . $post_id . '&action=edit');

        $sent = wp_mail($to, $subject, $message);

        if ($sent) {
            Logger::debug('RoadmapFormProcessor', 'Notification email sent', ['to' => $to]);
        } else {
            Logger::warning('RoadmapFormProcessor', 'Failed to send notification email', ['to' => $to]);
        }
    }
}
