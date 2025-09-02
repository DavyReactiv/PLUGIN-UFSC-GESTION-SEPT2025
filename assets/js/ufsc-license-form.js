/**
 * UFSC License Form JavaScript
 * Client-side validation for license forms
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initLicenseFormValidation();
        initClubRegionSync();
    });

    /**
     * Initialize form validation for license forms
     */
    function initLicenseFormValidation() {
        const form = $('form[action*="ufsc_sql_save_licence"]');
        if (form.length === 0) return;

        // Real-time validation on blur
        form.find('input[type="email"]').on('blur', validateEmail);
        form.find('input[type="tel"]').on('blur', validatePhone);
        form.find('input[type="date"]').on('blur', validateDate);
        form.find('input[required]').on('blur', validateRequired);

        // Form submission validation
        form.on('submit', function(e) {
            if (!validateForm($(this))) {
                e.preventDefault();
                showValidationErrors();
                return false;
            }
        });

        // Add visual indicators for required fields
        form.find('input[required]').each(function() {
            const label = $(this).closest('.ufsc-field').find('label');
            if (label.find('.required-indicator').length === 0) {
                label.append(' <span class="required-indicator" style="color: #dc3545;">*</span>');
            }
        });
    }

    /**
     * Validate email field
     */
    function validateEmail() {
        const input = $(this);
        const email = input.val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        clearFieldError(input);

        if (email && !emailRegex.test(email)) {
            showFieldError(input, 'Adresse email invalide');
            return false;
        }

        return true;
    }

    /**
     * Validate phone field
     */
    function validatePhone() {
        const input = $(this);
        const phone = input.val().trim();
        // French phone number regex (flexible)
        const phoneRegex = /^(?:(?:\+|00)33[\s.-]{0,3}(?:\(0\)[\s.-]{0,3})?|0)[1-9](?:[\s.-]?\d{2}){4}$/;

        clearFieldError(input);

        if (phone && !phoneRegex.test(phone.replace(/[\s.-]/g, ''))) {
            showFieldError(input, 'Numéro de téléphone invalide');
            return false;
        }

        return true;
    }

    /**
     * Validate date field
     */
    function validateDate() {
        const input = $(this);
        const date = input.val();

        clearFieldError(input);

        if (date) {
            const dateObj = new Date(date);
            const today = new Date();
            
            if (isNaN(dateObj.getTime())) {
                showFieldError(input, 'Date invalide');
                return false;
            }

            // For birth date, check if it's not in the future
            if (input.attr('name') === 'date_naissance' && dateObj > today) {
                showFieldError(input, 'La date de naissance ne peut pas être dans le futur');
                return false;
            }

            // Check for reasonable birth date (not more than 120 years ago)
            if (input.attr('name') === 'date_naissance') {
                const maxAge = new Date();
                maxAge.setFullYear(maxAge.getFullYear() - 120);
                if (dateObj < maxAge) {
                    showFieldError(input, 'Date de naissance invalide');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate required field
     */
    function validateRequired() {
        const input = $(this);
        const value = input.val().trim();

        clearFieldError(input);

        if (input.prop('required') && !value) {
            showFieldError(input, 'Ce champ est obligatoire');
            return false;
        }

        return true;
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        let isValid = true;

        // Validate all fields
        form.find('input[type="email"]').each(function() {
            if (!validateEmail.call(this)) isValid = false;
        });

        form.find('input[type="tel"]').each(function() {
            if (!validatePhone.call(this)) isValid = false;
        });

        form.find('input[type="date"]').each(function() {
            if (!validateDate.call(this)) isValid = false;
        });

        form.find('input[required]').each(function() {
            if (!validateRequired.call(this)) isValid = false;
        });

        return isValid;
    }

    /**
     * Show field error
     */
    function showFieldError(input, message) {
        const field = input.closest('.ufsc-field');
        field.addClass('error');
        
        // Remove existing error message
        field.find('.error-message').remove();
        
        // Add error message
        input.after('<div class="error-message">' + message + '</div>');
        
        // Add error styling to input
        input.addClass('ufsc-field-error');
    }

    /**
     * Clear field error
     */
    function clearFieldError(input) {
        const field = input.closest('.ufsc-field');
        field.removeClass('error');
        field.find('.error-message').remove();
        input.removeClass('ufsc-field-error');
    }

    /**
     * Show validation errors summary
     */
    function showValidationErrors() {
        const errorFields = $('.ufsc-field.error');
        if (errorFields.length > 0) {
            // Scroll to first error
            $('html, body').animate({
                scrollTop: errorFields.first().offset().top - 100
            }, 500);

            // Show notification
            showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
        }
    }

    /**
     * Initialize club-region synchronization
     */
    function initClubRegionSync() {
        const clubSelector = $('#ufsc-club-selector');
        const regionField = $('#ufsc-auto-region');

        if (clubSelector.length && regionField.length) {
            clubSelector.on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const region = selectedOption.data('region') || '';
                regionField.val(region);
            });
        }
    }

    /**
     * Show notification
     */
    function showNotification(message, type) {
        type = type || 'info';
        const className = 'notice notice-' + type;
        
        // Remove existing notifications
        $('.ufsc-temp-notice').remove();
        
        // Create notification
        const notice = $('<div class="' + className + ' ufsc-temp-notice is-dismissible"><p>' + message + '</p></div>');
        
        // Insert after h1
        $('h1').first().after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);

        // Make dismissible
        notice.on('click', '.notice-dismiss', function() {
            notice.remove();
        });
    }

    /**
     * Enhance save buttons with loading states
     */
    function enhanceSaveButtons() {
        $('form').on('submit', function() {
            const form = $(this);
            const buttons = form.find('button[type="submit"]');
            
            buttons.prop('disabled', true).each(function() {
                const btn = $(this);
                const originalText = btn.text();
                btn.data('original-text', originalText);
                btn.html('<span class="ufsc-spinner"></span> Enregistrement...');
            });
        });
    }

    // Initialize enhancements
    $(document).ready(function() {
        enhanceSaveButtons();
    });

})(jQuery);