/* UFSC Plugin - Admin JavaScript */

jQuery(document).ready(function($) {
    
    // Auto-hide success messages after 5 seconds
    $('.ufsc-alert.success').delay(5000).fadeOut();
    
    // Confirmation for delete buttons
    $('.button-link-delete').on('click', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Form validation enhancements
    $('form').on('submit', function(e) {
        var hasErrors = false;
        
        // Check required fields
        $(this).find('input[required], select[required]').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('error');
                hasErrors = true;
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Email validation
        $(this).find('input[type="email"]').each(function() {
            var email = $(this).val();
            if (email && !isValidEmail(email)) {
                $(this).addClass('error');
                hasErrors = true;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            alert('Veuillez corriger les erreurs dans le formulaire.');
            return false;
        }
    });
    
    // Real-time field validation
    $('input[type="email"]').on('blur', function() {
        var email = $(this).val();
        if (email && !isValidEmail(email)) {
            $(this).addClass('error');
        } else {
            $(this).removeClass('error');
        }
    });
    
    // Helper function for email validation
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Smooth scrolling to errors
    if ($('.ufsc-alert.error').length) {
        $('html, body').animate({
            scrollTop: $('.ufsc-alert.error').offset().top - 100
        }, 500);
    }
    
    // Dashboard card hover effects
    $('.ufsc-card').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    // Loading states for forms
    $('form').on('submit', function() {
        var $submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
        $submitBtn.prop('disabled', true).text('Enregistrement...');
    });
    
    // Club selector with auto-region population
    $('#ufsc-club-selector').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var region = selectedOption.data('region') || '';
        
        // Update the region field
        $('#ufsc-auto-region').val(region);
        
        // Also update any other region field that might exist
        $('input[name="region"], select[name="region"]').val(region);
    });
    
    // Initialize region on page load if club is already selected
    if ($('#ufsc-club-selector').val()) {
        $('#ufsc-club-selector').trigger('change');
    }
    
    // Select all licences checkbox
    $('#select-all-licences').on('change', function() {
        $('input[name="licence_ids[]"]').prop('checked', $(this).prop('checked'));
    });
    
    // Update select all when individual checkboxes change
    $('input[name="licence_ids[]"]').on('change', function() {
        var total = $('input[name="licence_ids[]"]').length;
        var checked = $('input[name="licence_ids[]"]:checked').length;
        $('#select-all-licences').prop('checked', total === checked);
    });
    
    // Quick status change for licence list (AJAX)
    $('.ufsc-quick-status').on('change', function() {
        var $select = $(this);
        var licenceId = $select.data('licence-id');
        var newStatus = $select.val();
        var originalStatus = $select.data('original-status');
        
        // Show loading state
        $select.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ufsc_update_licence_status',
                licence_id: licenceId,
                status: newStatus,
                nonce: $('#ufsc-ajax-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Update the badge
                    var $badge = $select.closest('tr').find('.ufsc-badge');
                    $badge.removeClass().addClass('ufsc-badge ufsc-badge-' + response.data.badge_class)
                          .text(response.data.status_label);
                    
                    // Show success toast
                    showToast('Statut mis à jour', 'success');
                    
                    // Update data attribute
                    $select.data('original-status', newStatus);
                } else {
                    // Revert to original status
                    $select.val(originalStatus);
                    showToast('Erreur lors de la mise à jour', 'error');
                }
            },
            error: function() {
                // Revert to original status
                $select.val(originalStatus);
                showToast('Erreur de communication', 'error');
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });
    
    // Send to payment bulk action
    $('.ufsc-send-to-payment').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var selectedLicences = [];
        
        // Get selected licences from checkboxes
        $('input[name="licence_ids[]"]:checked').each(function() {
            selectedLicences.push($(this).val());
        });
        
        if (selectedLicences.length === 0) {
            alert('Veuillez sélectionner au moins une licence.');
            return;
        }
        
        if (!confirm('Envoyer ' + selectedLicences.length + ' licence(s) au paiement ?')) {
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true).text('Envoi en cours...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ufsc_send_to_payment',
                licence_ids: selectedLicences,
                nonce: $('#ufsc-ajax-nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showToast('Commande créée avec succès', 'success');
                    // Optionally redirect to payment page
                    if (response.data.payment_url) {
                        window.open(response.data.payment_url, '_blank');
                    }
                } else {
                    showToast(response.data.message || 'Erreur lors de la création de la commande', 'error');
                }
            },
            error: function() {
                showToast('Erreur de communication', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Envoyer au paiement');
            }
        });
    });
    
    // Toast notification function
    function showToast(message, type) {
        var toastClass = type === 'success' ? 'notice-success' : 'notice-error';
        var $toast = $('<div class="notice ' + toastClass + ' is-dismissible ufsc-toast">')
            .append('<p>' + message + '</p>')
            .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');
        
        // Insert after h1 if exists, otherwise prepend to wrap
        if ($('.wrap h1').length) {
            $toast.insertAfter('.wrap h1');
        } else {
            $('.wrap').prepend($toast);
        }
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            $toast.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
        
        // Handle dismiss button
        $toast.find('.notice-dismiss').on('click', function() {
            $toast.fadeOut(function() {
                $(this).remove();
            });
        });
    }
});

/* Additional CSS for JavaScript enhancements */
var additionalCSS = `
    .ufsc-field input.error,
    .ufsc-field select.error,
    .ufsc-field textarea.error {
        border-color: #dc3232 !important;
        box-shadow: 0 0 0 2px rgba(220, 50, 50, 0.1) !important;
    }
    
    .ufsc-card.hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    }
    
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .ufsc-readonly-field {
        background-color: #f7f7f7 !important;
        color: #666 !important;
    }
    
    .ufsc-club-selector {
        min-width: 300px;
    }
    
    .ufsc-quick-status {
        min-width: 120px;
    }
    
    .ufsc-toast {
        position: relative;
        margin: 10px 0;
    }
    
    .ufsc-toast.notice {
        border-left: 4px solid;
        padding: 12px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .ufsc-send-to-payment {
        background: #f56e28;
        border-color: #f56e28;
        color: white;
    }
    
    .ufsc-send-to-payment:hover {
        background: #e85d1a;
        border-color: #e85d1a;
    }
`;

// Inject additional CSS
if (!document.getElementById('ufsc-dynamic-css')) {
    var style = document.createElement('style');
    style.id = 'ufsc-dynamic-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}