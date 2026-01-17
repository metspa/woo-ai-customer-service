/**
 * WooCommerce AI Customer Service Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color pickers
        $('.woo-ai-chat-color-picker').wpColorPicker();

        // API key field handling
        var apiKeyField = $('#woo_ai_chat_api_key');
        if (apiKeyField.length) {
            // Clear placeholder dots when user focuses
            apiKeyField.on('focus', function() {
                if ($(this).val().indexOf('\u2022') !== -1) {
                    $(this).val('');
                }
            });
        }

        // Form validation
        $('form').on('submit', function() {
            var emailField = $('#woo_ai_chat_support_email');
            if (emailField.length && emailField.val()) {
                var email = emailField.val();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Please enter a valid support email address.');
                    emailField.focus();
                    return false;
                }
            }
            return true;
        });

        // Test API connection button
        $('#test-api-connection').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var resultSpan = $('#api-test-result');
            var originalText = button.text();

            button.prop('disabled', true).text('Testing...');
            resultSpan.html('').removeClass('success error');

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_test_api',
                    nonce: wooAiChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultSpan.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' + response.data.message);
                    } else {
                        resultSpan.html('<span class="dashicons dashicons-warning" style="color: red;"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    resultSpan.html('<span class="dashicons dashicons-warning" style="color: red;"></span> Could not test connection. Please try again.');
                },
                complete: function() {
                    button.prop('disabled', false).text(originalText);
                }
            });
        });

        // Conversation status update buttons
        $('.woo-ai-chat-status-buttons').on('click', '.woo-ai-chat-status-btn', function(e) {
            e.preventDefault();
            var button = $(this);
            var container = button.closest('.woo-ai-chat-status-buttons');
            var conversationId = container.data('id');
            var status = button.data('status');
            var resultP = $('#status-update-result');

            // Disable all buttons
            container.find('button').prop('disabled', true);

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_update_status',
                    nonce: wooAiChatAdmin.nonce,
                    conversation_id: conversationId,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        // Update button styles
                        container.find('button').removeClass('button-primary').find('.dashicons-yes').remove();
                        button.addClass('button-primary').append(' <span class="dashicons dashicons-yes" style="font-size: 16px; height: 16px; width: 16px;"></span>');
                        resultP.html('<span style="color: green;">' + response.data.message + '</span>');

                        // Update page title badge if exists
                        var badgeInTitle = $('h1 span[style*="border-radius"]');
                        if (badgeInTitle.length) {
                            var colors = {
                                'active': '#2271b1',
                                'needs_attention': '#d63638',
                                'waiting': '#dba617',
                                'resolved': '#00a32a'
                            };
                            badgeInTitle.css('background', colors[status] || '#666').text(response.data.label);
                        }
                    } else {
                        resultP.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    resultP.html('<span style="color: red;">Failed to update status. Please try again.</span>');
                },
                complete: function() {
                    container.find('button').prop('disabled', false);
                    setTimeout(function() { resultP.html(''); }, 3000);
                }
            });
        });

        // Delete conversation from list
        $('.woo-ai-chat-delete-convo').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var conversationId = button.data('id');
            var row = button.closest('tr');

            if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                return;
            }

            button.prop('disabled', true);

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_delete_conversation',
                    nonce: wooAiChatAdmin.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert('Failed to delete: ' + response.data.message);
                        button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Failed to delete conversation. Please try again.');
                    button.prop('disabled', false);
                }
            });
        });

        // Delete conversation from single view
        $('.woo-ai-chat-delete-convo-single').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var conversationId = button.data('id');

            if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                return;
            }

            button.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_delete_conversation',
                    nonce: wooAiChatAdmin.nonce,
                    conversation_id: conversationId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = wooAiChatAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=woo-ai-chat-conversations&deleted=1');
                    } else {
                        alert('Failed to delete: ' + response.data.message);
                        button.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Delete Conversation');
                    }
                },
                error: function() {
                    alert('Failed to delete conversation. Please try again.');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> Delete Conversation');
                }
            });
        });
    });
})(jQuery);
