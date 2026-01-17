/**
 * WooCommerce AI Customer Service Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color pickers
        $('.woo-ai-chat-color-picker').wpColorPicker();

        // Save API Key button handler
        $('#save-api-key').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var apiKeyField = $('#woo_ai_chat_api_key');
            var statusDiv = $('#api-key-status');
            var apiKey = apiKeyField.val().trim();

            if (!apiKey) {
                statusDiv.html('<span style="color: red; font-weight: bold;">✗ Please enter an API key</span>');
                return;
            }

            if (apiKey.indexOf('sk-') !== 0) {
                statusDiv.html('<span style="color: red; font-weight: bold;">✗ API key must start with sk-</span>');
                return;
            }

            button.prop('disabled', true).text('Saving...');
            statusDiv.html('<span style="color: #666;">Saving...</span>');

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_save_api_key',
                    nonce: wooAiChatAdmin.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        statusDiv.html('<span style="color: green; font-weight: bold;">✓ ' + response.data.message + '</span>');
                        apiKeyField.val(''); // Clear the field after saving
                    } else {
                        statusDiv.html('<span style="color: red; font-weight: bold;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    statusDiv.html('<span style="color: red; font-weight: bold;">✗ Error saving API key</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Save API Key');
                }
            });
        });

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
            var resultDiv = $('#api-test-result');
            var originalText = button.text();

            button.prop('disabled', true).text('Testing...');
            resultDiv.hide().empty();

            $.ajax({
                url: wooAiChatAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_ai_chat_test_api',
                    nonce: wooAiChatAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultDiv.attr('style', 'margin-top: 15px; padding: 15px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; display: block; background: #d4edda; border: 2px solid #28a745; color: #155724;')
                            .html('&#10004; <strong>SUCCESS - Connected!</strong> Your API key is working correctly.');
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Unknown error';
                        resultDiv.attr('style', 'margin-top: 15px; padding: 15px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; display: block; background: #f8d7da; border: 2px solid #dc3545; color: #721c24;')
                            .html('&#10008; <strong>FAILED:</strong> ' + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    resultDiv.attr('style', 'margin-top: 15px; padding: 15px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; display: block; background: #f8d7da; border: 2px solid #dc3545; color: #721c24;')
                        .html('&#10008; <strong>ERROR:</strong> Could not connect to server. Please try again.');
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
