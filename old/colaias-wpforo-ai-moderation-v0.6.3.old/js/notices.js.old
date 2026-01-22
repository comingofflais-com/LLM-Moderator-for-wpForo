/**
 * WPForo AI Moderation Front-end Notices JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize notices when DOM is ready
    $(document).ready(function() {
        colaiasInitNotices();
    });
    
    function colaiasInitNotices() {
        // Find or create the main notices container
        let $container = $('.colaias-wpforo-ai-notices-container:first');
        
        if ($container.length === 0) {
            
            if ($container.length === 0) {
                // Create container if none exists (no shortcode)
                $('body').append('<div class="colaias-wpforo-ai-notices-container" style="position: fixed; top: 20px; left: 20px; z-index: 999999; width: 350px; max-width: 90vw;"></div>');
                $container = $('.colaias-wpforo-ai-notices-container:first');
            }
        }
        
        // Move all colaias-wpforo-ai-notice class notices to this container to the first container found
        $('.colaias-wpforo-ai-notice').each(function() {
            const $notice = $(this);
            const $parent = $notice.parent();
            
            // If notice parent is not in our main container, move it there (parent should be this container though)
            if (!$parent.is($container) && !$parent.hasClass('colaias-notice-content')) {
                $container.append($notice);
            }
        });
        
        // Handle notice dismissal
        $container.on('click', '.colaias-notice-dismiss', function(e) {
            e.preventDefault();
            const $button = $(this);
            const $notice = $button.closest('.colaias-wpforo-ai-notice');
            // const noticeId = $notice.data('notice-id');
            
            // Add dismissing class for animation
            $notice.addClass('dismissing');
            
            // Remove notice after animation completes
            setTimeout(function() {
                $notice.remove();
                
                // If container is empty, hide it
                if ($container.children().length === 0) {
                    $container.hide();
                }
            }, 300);
        });
        
        // Auto-dismiss notices based on data-duration attribute
        $container.find('.colaias-wpforo-ai-notice').each(function() {
            const $notice = $(this);
            const duration = parseInt($notice.data('duration')) || 10000; // Default to 10 seconds if not set
            
            // Only set auto-dismiss if duration is greater than 0
            if (duration > 0) {
                let autoDismiss = setTimeout(function() {
                    $notice.find('.colaias-notice-dismiss').trigger('click');
                }, duration);
                
                // Clear timeout if user hovers over notice
                $notice.on('mouseenter', function() {
                    if (autoDismiss) {
                        clearTimeout(autoDismiss);
                        autoDismiss = null;
                    }
                });
                
                // Restart timeout when mouse leaves
                $notice.on('mouseleave', function() {
                    if (autoDismiss === null) {
                        autoDismiss = setTimeout(function() {
                            $notice.find('.colaias-notice-dismiss').trigger('click');
                        }, duration);
                    }
                });
            }
        });
        
        // Show container if there are notices
        if ($container.children().length > 0) {
            $container.show();
        }
    }
    
    
    
})(jQuery);