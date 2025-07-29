/**
 * LicenceLand Admin JavaScript
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Main admin functionality
    var LicenceLandAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initAjaxHandlers();
        },
        
        bindEvents: function() {
            // Feature toggle handlers
            $('.licenceland-feature-toggle').on('change', this.handleFeatureToggle);
            
            // CD Keys management
            $('.cd-key-delete').on('click', this.handleDeleteCDKey);
            $('.cd-key-add').on('click', this.handleAddCDKeys);
            
            // Backorder management
            $('.process-backorder').on('click', this.handleProcessBackorder);
            
            // Settings form enhancements
            $('.licenceland-settings-form').on('submit', this.handleSettingsSubmit);
            
            // Shop type switching
            $('.shop-type-switch').on('click', this.handleShopTypeSwitch);
            
            // IP search functionality
            $('#ip-search-form').on('submit', this.handleIPSearch);
        },
        
        initTooltips: function() {
            // Initialize tooltips for help text
            $('.licenceland-help').each(function() {
                var $this = $(this);
                var helpText = $this.attr('data-help');
                
                if (helpText) {
                    $this.tooltip({
                        content: helpText,
                        position: { my: 'left+5 center', at: 'right center' }
                    });
                }
            });
        },
        
        initAjaxHandlers: function() {
            // Global AJAX error handler
            $(document).ajaxError(function(event, xhr, settings, error) {
                LicenceLandAdmin.showNotice('An error occurred while processing your request.', 'error');
            });
        },
        
        handleFeatureToggle: function(e) {
            var $checkbox = $(this);
            var feature = $checkbox.attr('data-feature');
            var enabled = $checkbox.is(':checked');
            
            // Show loading state
            $checkbox.prop('disabled', true);
            
            // Send AJAX request to update setting
            $.ajax({
                url: licenceland_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'licenceland_toggle_feature',
                    feature: feature,
                    enabled: enabled,
                    nonce: licenceland_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LicenceLandAdmin.showNotice('Feature updated successfully!', 'success');
                    } else {
                        LicenceLandAdmin.showNotice('Failed to update feature.', 'error');
                        $checkbox.prop('checked', !enabled); // Revert checkbox
                    }
                },
                error: function() {
                    LicenceLandAdmin.showNotice('Failed to update feature.', 'error');
                    $checkbox.prop('checked', !enabled); // Revert checkbox
                },
                complete: function() {
                    $checkbox.prop('disabled', false);
                }
            });
        },
        
        handleDeleteCDKey: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var cdKey = $button.attr('data-key');
            var productId = $button.attr('data-product-id');
            
            if (!confirm(licenceland_ajax.strings.confirm_delete)) {
                return;
            }
            
            $button.prop('disabled', true);
            
            $.ajax({
                url: licenceland_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'licenceland_delete_cd_key',
                    cd_key: cdKey,
                    product_id: productId,
                    nonce: licenceland_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.cd-key-item').fadeOut(function() {
                            $(this).remove();
                        });
                        LicenceLandAdmin.showNotice('CD key deleted successfully!', 'success');
                    } else {
                        LicenceLandAdmin.showNotice('Failed to delete CD key.', 'error');
                    }
                },
                error: function() {
                    LicenceLandAdmin.showNotice('Failed to delete CD key.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        handleAddCDKeys: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $textarea = $button.siblings('textarea');
            var productId = $button.attr('data-product-id');
            var keys = $textarea.val();
            
            if (!keys.trim()) {
                LicenceLandAdmin.showNotice('Please enter CD keys to add.', 'warning');
                return;
            }
            
            $button.prop('disabled', true);
            
            $.ajax({
                url: licenceland_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'licenceland_add_cd_keys',
                    keys: keys,
                    product_id: productId,
                    nonce: licenceland_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val('');
                        LicenceLandAdmin.showNotice(response.data.message, 'success');
                        // Optionally refresh the CD keys list
                        if (typeof LicenceLandAdmin.refreshCDKeysList === 'function') {
                            LicenceLandAdmin.refreshCDKeysList(productId);
                        }
                    } else {
                        LicenceLandAdmin.showNotice('Failed to add CD keys.', 'error');
                    }
                },
                error: function() {
                    LicenceLandAdmin.showNotice('Failed to add CD keys.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        handleSettingsSubmit: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true).val(licenceland_ajax.strings.saving);
            
            // Form will submit normally, but we can add custom validation here
            // The loading state will be cleared when the page reloads
        },
        
        handleShopTypeSwitch: function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var shopType = $link.attr('data-shop-type');
            
            // Show loading state
            $link.addClass('licenceland-loading');
            
            // Redirect to switch shop type
            window.location.href = $link.attr('href');
        },
        
        handleIPSearch: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('button[type="submit"]');
            var $results = $('.ip-search-results');
            
            // Show loading state
            $submitButton.prop('disabled', true).text('Searching...');
            $results.html('<div class="licenceland-loading">Searching...</div>');
            
            // Form will submit normally, loading state will be cleared on page reload
        },
        
        handleProcessBackorder: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var backorderId = $button.data('backorder-id');
            
            if (!backorderId) {
                LicenceLandAdmin.showNotice('Invalid backorder ID', 'error');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text('Processing...');
            
            // Process backorder via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'licenceland_process_backorder',
                    backorder_id: backorderId,
                    nonce: licenceland_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        LicenceLandAdmin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        LicenceLandAdmin.showNotice(response.data || 'Error processing backorder', 'error');
                        $button.prop('disabled', false).text('Process');
                    }
                },
                error: function() {
                    LicenceLandAdmin.showNotice('Network error occurred', 'error');
                    $button.prop('disabled', false).text('Process');
                }
            });
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="licenceland-notice licenceland-notice-' + type + '">' + message + '</div>');
            
            // Remove existing notices
            $('.licenceland-notice').remove();
            
            // Add new notice at the top of the page
            $('.wrap h1').after($notice);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        refreshCDKeysList: function(productId) {
            // This function can be implemented to refresh the CD keys list via AJAX
            // For now, we'll just reload the page
            location.reload();
        },
        
        // Utility functions
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },
        
        formatCurrency: function(amount, currency) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency || 'USD'
            }).format(amount);
        },
        
        validateEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        validateIP: function(ip) {
            var re = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            return re.test(ip);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        LicenceLandAdmin.init();
    });
    
    // Make it globally available
    window.LicenceLandAdmin = LicenceLandAdmin;
    
})(jQuery);