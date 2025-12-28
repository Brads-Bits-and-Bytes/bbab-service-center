/**
 * BBAB Service Center - Frontend Dashboard Scripts
 */

(function($) {
    'use strict';

    // AJAX helper
    window.bbabScAjax = window.bbabScAjax || {};

    const BBAB = {
        /**
         * Make an AJAX request through the AjaxRouter
         */
        ajax: function(handler, data, successCallback, errorCallback) {
            $.ajax({
                url: bbabScAjax.url,
                type: 'POST',
                data: {
                    action: bbabScAjax.action,
                    handler: handler,
                    nonce: bbabScAjax.nonce,
                    data: JSON.stringify(data)
                },
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response.data);
                    } else {
                        if (errorCallback) errorCallback(response.data);
                        else console.error('BBAB AJAX Error:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    if (errorCallback) errorCallback({ message: error });
                    else console.error('BBAB AJAX Error:', error);
                }
            });
        },

        /**
         * Show loading state on an element
         */
        showLoading: function($element) {
            $element.addClass('bbab-loading-state');
            $element.append('<div class="bbab-loading"><div class="bbab-spinner"></div></div>');
        },

        /**
         * Hide loading state
         */
        hideLoading: function($element) {
            $element.removeClass('bbab-loading-state');
            $element.find('.bbab-loading').remove();
        },

        /**
         * Format a date string
         */
        formatDate: function(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    };

    // Expose to global scope
    window.BBAB = BBAB;

    // Document ready
    $(function() {
        // Initialize any dashboard components here
        console.log('BBAB Service Center frontend loaded');

        // Initialize Roadmap handlers
        initRoadmapHandlers();
    });

    /**
     * Initialize Roadmap AJAX handlers
     * Handles: I'm Interested, Not Right Now, Decline, Approve buttons
     */
    function initRoadmapHandlers() {
        // "I'm Interested" button handler
        $(document).on('click', '.btn-interested', function() {
            var $btn = $(this);
            var $card = $btn.closest('.roadmap-card');
            var itemId = $btn.data('item-id');
            var nonce = $btn.data('nonce');

            $btn.prop('disabled', true).text('Sending...');

            $.post(bbabScAjax.url, {
                action: 'roadmap_interested',
                item_id: itemId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    $card.html('<p style="padding: 20px; text-align: center; color: #059669;">&#10004; ' + response.data.message + '</p>');
                    setTimeout(function() { $card.fadeOut(); }, 2000);
                } else {
                    alert(response.data.message || 'Something went wrong');
                    $btn.prop('disabled', false).text("I'm Interested");
                }
            }).fail(function() {
                alert('Something went wrong. Please try again.');
                $btn.prop('disabled', false).text("I'm Interested");
            });
        });

        // "Not Right Now" / "Decline" button handler
        $(document).on('click', '.btn-not-now, .btn-decline', function() {
            var $btn = $(this);
            var $card = $btn.closest('.roadmap-card');
            var itemId = $btn.data('item-id');
            var nonce = $btn.data('nonce');
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('Sending...');

            $.post(bbabScAjax.url, {
                action: 'roadmap_decline',
                item_id: itemId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    $card.html('<p style="padding: 20px; text-align: center; color: #666;">&#128077; ' + response.data.message + '</p>');
                    setTimeout(function() { $card.fadeOut(); }, 2000);
                } else {
                    alert(response.data.message || 'Something went wrong');
                    $btn.prop('disabled', false).text(originalText);
                }
            }).fail(function() {
                alert('Something went wrong. Please try again.');
                $btn.prop('disabled', false).text(originalText);
            });
        });

        // "Approve" button handler
        $(document).on('click', '.btn-approve', function() {
            var $btn = $(this);
            var $card = $btn.closest('.roadmap-card');
            var itemId = $btn.data('item-id');
            var nonce = $btn.data('nonce');

            $btn.prop('disabled', true).text('Sending...');

            $.post(bbabScAjax.url, {
                action: 'roadmap_approve',
                item_id: itemId,
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    $card.html('<p style="padding: 20px; text-align: center; color: #059669;">&#127881; ' + response.data.message + '</p>');
                    setTimeout(function() { $card.fadeOut(); }, 2000);
                } else {
                    alert(response.data.message || 'Something went wrong');
                    $btn.prop('disabled', false).text('Approve');
                }
            }).fail(function() {
                alert('Something went wrong. Please try again.');
                $btn.prop('disabled', false).text('Approve');
            });
        });
    }

})(jQuery);
