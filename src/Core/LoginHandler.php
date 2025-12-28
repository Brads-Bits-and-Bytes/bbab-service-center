<?php
declare(strict_types=1);

namespace BBAB\ServiceCenter\Core;

use BBAB\ServiceCenter\Utils\Settings;
use BBAB\ServiceCenter\Utils\Logger;

/**
 * Login Redirect Handler.
 *
 * Handles post-login redirects when users authenticate through wp-login.php.
 * Clients are redirected to the portal; admins go to wp-admin.
 *
 * Note: This supplements PortalAccessControl. Most client logins will happen
 * on the portal page itself. This handles edge cases like:
 * - Direct wp-login.php access
 * - Password reset flows
 * - Plugin/theme redirects
 *
 * Migrated from: WPCode Snippet #1040
 */
class LoginHandler {

    /**
     * Register login redirect hook.
     */
    public function register(): void {
        add_filter('login_redirect', [$this, 'redirectAfterLogin'], 99, 3);

        Logger::debug('LoginHandler', 'Registered login redirect hook');
    }

    /**
     * Redirect users after login.
     *
     * @param string   $redirect_to           The redirect destination URL.
     * @param string   $requested_redirect_to The requested redirect destination URL (passed as parameter).
     * @param \WP_User|\WP_Error $user        WP_User object if login was successful, WP_Error on failure.
     * @return string The URL to redirect to.
     */
    public function redirectAfterLogin($redirect_to, $requested_redirect_to, $user): string {
        // Don't process errors
        if (!is_object($user) || is_wp_error($user)) {
            return $redirect_to;
        }

        // Admins go to wp-admin
        if (in_array('administrator', (array) $user->roles, true)) {
            Logger::debug('LoginHandler', 'Admin login, redirecting to wp-admin', [
                'user_id' => $user->ID,
            ]);
            return admin_url();
        }

        // Check if a specific redirect was requested (e.g., from portal login)
        // Respect the requested redirect if it's within the portal
        if (!empty($requested_redirect_to) && strpos($requested_redirect_to, 'client-dashboard') !== false) {
            Logger::debug('LoginHandler', 'Respecting requested redirect to portal page', [
                'user_id' => $user->ID,
                'redirect_to' => $requested_redirect_to,
            ]);
            return $requested_redirect_to;
        }

        // Default: redirect clients to the dashboard
        $dashboard_url = home_url('/client-dashboard/');

        // If dashboard_page_id is configured, use its permalink
        $dashboard_page_id = (int) Settings::get('dashboard_page_id', 0);
        if ($dashboard_page_id > 0) {
            $permalink = get_permalink($dashboard_page_id);
            if ($permalink) {
                $dashboard_url = $permalink;
            }
        }

        Logger::debug('LoginHandler', 'Client login, redirecting to dashboard', [
            'user_id' => $user->ID,
            'redirect_to' => $dashboard_url,
        ]);

        return $dashboard_url;
    }
}
