/**
 * BBAB Service Center - Admin Workbench JavaScript
 *
 * Handles admin-side functionality including:
 * - Simulation controls
 * - Workbench interactions
 * - Admin AJAX operations
 */
(function($) {
    'use strict';

    /**
     * BBAB Admin Workbench module
     */
    var BBAdminWorkbench = {

        /**
         * Initialize the module.
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Simulation form enhancements (if any)
            // Additional event bindings will be added as needed
        },

        /**
         * Make an AJAX request to the plugin backend.
         *
         * @param {string} handler - The handler name
         * @param {object} data - Additional data to send
         * @param {function} callback - Success callback
         */
        ajax: function(handler, data, callback) {
            if (typeof bbabScAdmin === 'undefined') {
                console.error('BBAB Admin: Ajax config not available');
                return;
            }

            var requestData = {
                action: bbabScAdmin.action,
                handler: handler,
                nonce: bbabScAdmin.nonce,
                data: JSON.stringify(data || {})
            };

            $.post(bbabScAdmin.url, requestData, function(response) {
                if (typeof callback === 'function') {
                    callback(response);
                }
            }).fail(function(xhr, status, error) {
                console.error('BBAB Admin AJAX Error:', error);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BBAdminWorkbench.init();
    });

})(jQuery);
