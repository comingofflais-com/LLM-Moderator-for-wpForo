/**
 * LLM Moderator for wpForo - Admin JavaScript
 * Handles admin interface functionality including flag type management and cleanup operations
 */

jQuery(document).ready(function($) {
    console.log('LLM Moderator admin.js loaded successfully');
    
    // Test: If this alert shows, JavaScript is loading
    // alert('LLM Moderator admin.js loaded');
    
    // ============================================
    // Flag Type Management
    // ============================================
    
    // Add new flag type row
    $('#add-flag-type').on('click', function() {
        var index = $('.flag-type-row').length;
        var html = '<div class="flag-type-row" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">' +
            '<input type="hidden" name="colaias_wpforo_ai_flag_types[' + index + '][index]" value="' + index + '">' +
            '<div style="margin-bottom: 10px;">' +
                '<label style="display: inline-block; width: 120px; font-weight: bold;">Flag Type:</label>' +
                '<input type="text" name="colaias_wpforo_ai_flag_types[' + index + '][type]" value="" placeholder="e.g., flag, nsfw, spam" style="width: 200px;">' +
            '</div>' +
            '<div style="margin-bottom: 10px;">' +
                '<label style="display: inline-block; width: 120px; font-weight: bold;">Enabled:</label>' +
                '<input type="checkbox" name="colaias_wpforo_ai_flag_types[' + index + '][enabled]" value="1" checked>' +
                '<span class="description">Enable this flag type for moderation</span>' +
            '</div>' +
            '<div style="margin-bottom: 10px;">' +
                '<label style="display: inline-block; width: 120px; font-weight: bold;">Should Mute:</label>' +
                '<input type="checkbox" name="colaias_wpforo_ai_flag_types[' + index + '][shouldMute]" value="1" checked>' +
                '<span class="description">Mute users when this flag type is triggered</span>' +
            '</div>' +
            '<div style="margin-bottom: 10px;">' +
                '<label style="display: inline-block; width: 120px; font-weight: bold;">Mute Duration ( days ):</label>' +
                '<input type="number" name="colaias_wpforo_ai_flag_types[' + index + '][muteDuration]" value="7" min="0" max="365" style="width: 80px;">' +
                '<span class="description">Days to mute for this specific flag type</span>' +
            '</div>' +
            '<div style="margin-bottom: 10px;">' +
                '<label style="display: inline-block; width: 120px; font-weight: bold;">Append Message:</label>' +
                '<input type="text" name="colaias_wpforo_ai_flag_types[' + index + '][appendString]" value="" placeholder="Leave blank for no message. Use {TYPE} and {REASON} for LLM response formatting tags ( case sensitive )." style="width: 600px; font-size: 14px;">' +
                '<div style="margin-top: 5px; max-width: 400px; color: #666; font-size: 13px;">' +
                    'Message to append at the end of flagged content. The {TYPE} and {REASON} formatting tags ( case sensitive ) will be automatically replaced with the actual values from the LLM AI moderator response.' +
                '</div>' +
            '</div>' +
            '<button type="button" class="button button-small remove-flag-type" style="color: #dc3232; border-color: #dc3232;">Remove</button>' +
        '</div>';
        
        $('#flag-types-container').append(html);
    });
    
    // Remove flag type row
    $(document).on('click', '.remove-flag-type', function() {
        $(this).closest('.flag-type-row').remove();
        // Reindex the rows
        $('.flag-type-row').each(function(newIndex) {
            $(this).find('input[name$="[index]"]').val(newIndex);
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name').replace(/\[\d+\]/, '[' + newIndex + ']');
                $(this).attr('name', name);
            });
        });
    });
    
    // ============================================
    // Cleanup Operations
    // ============================================
    
    $('#colaias-wpforo-ai-cleanup-now').on('click', function() {
        var button = $(this);
        var status = $('#colaias-wpforo-ai-cleanup-status');
        
        button.prop('disabled', true);
        status.text('Running cleanup...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'colaias_wpforo_ai_manual_cleanup',
                _ajax_nonce: button.data('nonce')
            },
            success: function(response) {
                if (response.success) {
                    status.text('Cleanup completed! ' + response.data.message);
                    // Reload the page to show updated list
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    status.text('Error: ' + response.data);
                }
            },
            error: function() {
                status.text('AJAX error occurred');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
});