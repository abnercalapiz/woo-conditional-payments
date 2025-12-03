jQuery(document).ready(function($) {
    // Handle invoice status filter
    $('#wcp_invoice_status').on('change', function() {
        var filterValue = $(this).val();
        var currentUrl = window.location.href;
        var newUrl;
        
        if (filterValue) {
            // Add or update the filter parameter
            if (currentUrl.indexOf('wcp_invoice_status=') > -1) {
                newUrl = currentUrl.replace(/wcp_invoice_status=[^&]*/, 'wcp_invoice_status=' + filterValue);
            } else {
                newUrl = currentUrl + (currentUrl.indexOf('?') > -1 ? '&' : '?') + 'wcp_invoice_status=' + filterValue;
            }
        } else {
            // Remove the filter parameter
            newUrl = currentUrl.replace(/[&?]wcp_invoice_status=[^&]*/g, '');
            // Clean up URL
            newUrl = newUrl.replace(/\?&/, '?').replace(/\?$/, '');
        }
        
        window.location.href = newUrl;
    });

    // Toggle invoice payment status via AJAX
    $('.wcp-invoice-status').on('click', function(e) {
        e.preventDefault();
        
        var $this = $(this);
        var userId = $this.data('user-id');
        
        $.ajax({
            url: wcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wcp_toggle_invoice_payment',
                user_id: userId,
                nonce: wcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.enabled) {
                        $this.removeClass('disabled').addClass('enabled').text('Enabled');
                    } else {
                        $this.removeClass('enabled').addClass('disabled').text('Disabled');
                    }
                }
            }
        });
    });
});