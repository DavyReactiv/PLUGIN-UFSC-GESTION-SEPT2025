/**
 * UFSC Club Form JavaScript
 * Handles conditional field visibility and form enhancements
 */
(function($) {
    'use strict';
    
    /**
     * Initialize form functionality when DOM is ready
     */
    $(document).ready(function() {
        initConditionalFields();
        initFormValidation();
        initFormEnhancements();
    });
    
    /**
     * Initialize conditional field visibility based on user association type
     */
    function initConditionalFields() {
        const $userAssociationRadios = $('input[name="user_association"]');
        const $createUserFields = $('#create-user-fields');
        const $existingUserFields = $('#existing-user-fields');
        
        if ($userAssociationRadios.length === 0) {
            return; // No conditional fields on this form
        }
        
        // Handle radio button changes
        $userAssociationRadios.on('change', function() {
            const selectedValue = $(this).val();
            
            // Hide all conditional sections
            $createUserFields.hide();
            $existingUserFields.hide();
            
            // Show relevant section based on selection
            switch (selectedValue) {
                case 'create':
                    $createUserFields.show();
                    toggleRequiredFields($createUserFields, true);
                    toggleRequiredFields($existingUserFields, false);
                    break;
                    
                case 'existing':
                    $existingUserFields.show();
                    toggleRequiredFields($existingUserFields, true);
                    toggleRequiredFields($createUserFields, false);
                    break;
                    
                case 'current':
                default:
                    toggleRequiredFields($createUserFields, false);
                    toggleRequiredFields($existingUserFields, false);
                    break;
            }
        });
        
        // Initialize based on current selection
        $userAssociationRadios.filter(':checked').trigger('change');
    }
    
    /**
     * Toggle required attribute on fields within a container
     * 
     * @param {jQuery} $container Container with fields
     * @param {boolean} required Whether fields should be required
     */
    function toggleRequiredFields($container, required) {
        const $fields = $container.find('input, select, textarea');
        
        if (required) {
            $fields.attr('required', 'required');
        } else {
            $fields.removeAttr('required');
        }
    }
    
    /**
     * Initialize form validation enhancements
     */
    function initFormValidation() {
        const $form = $('.ufsc-club-form');
        
        if ($form.length === 0) {
            return;
        }
        
        // Custom validation for IBAN
        $('input[name="iban"]').on('blur', function() {
            const iban = $(this).val().trim();
            if (iban && !isValidIBAN(iban)) {
                showFieldError($(this), 'Format IBAN invalide');
            } else {
                clearFieldError($(this));
            }
        });
        
        // Custom validation for postal code
        $('input[name="code_postal"]').on('blur', function() {
            const postalCode = $(this).val().trim();
            if (postalCode && !/^\d{5}$/.test(postalCode)) {
                showFieldError($(this), 'Le code postal doit contenir 5 chiffres');
            } else {
                clearFieldError($(this));
            }
        });
        
        // Email validation for dirigeants
        $('input[type="email"]').on('blur', function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                showFieldError($(this), 'Format d\'email invalide');
            } else {
                clearFieldError($(this));
            }
        });
        
        // File size validation
        $('input[type="file"]').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            const $input = $(this);
            const isLogo = $input.attr('name') === 'logo_upload';
            const maxSize = isLogo ? 2 * 1024 * 1024 : 5 * 1024 * 1024; // 2MB for logo, 5MB for docs
            
            if (file.size > maxSize) {
                const maxMB = maxSize / (1024 * 1024);
                showFieldError($input, `Le fichier est trop volumineux. Taille maximum : ${maxMB} MB`);
                $input.val(''); // Clear the file input
            } else {
                clearFieldError($input);
            }
        });
        
        // Form submission validation
        $form.on('submit', function(e) {
            let hasErrors = false;
            
            // Clear previous errors
            $('.ufsc-field-error').remove();
            
            // Validate required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val().trim();
                
                if (!value) {
                    showFieldError($field, 'Ce champ est requis');
                    hasErrors = true;
                }
            });
            
            // If errors found, prevent submission and focus first error
            if (hasErrors) {
                e.preventDefault();
                $('.ufsc-field-error').first().get(0).scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return false;
            }
        });
    }
    
    /**
     * Initialize form enhancements
     */
    function initFormEnhancements() {
        // Auto-dismiss success/error messages
        setTimeout(function() {
            $('.ufsc-alert').fadeOut(500);
        }, 5000);
        
        // Show loading state on form submission
        $('.ufsc-club-form').on('submit', function() {
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            $submitBtn.prop('disabled', true).text('Enregistrement...');
            
            // Re-enable after a timeout as fallback
            setTimeout(function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }, 30000);
        });
        
        // Character counters for text areas (if any)
        $('textarea[maxlength]').each(function() {
            const $textarea = $(this);
            const maxLength = parseInt($textarea.attr('maxlength'));
            const $counter = $('<div class="ufsc-char-counter"></div>');
            
            $textarea.after($counter);
            
            $textarea.on('input', function() {
                const remaining = maxLength - $(this).val().length;
                $counter.text(`${remaining} caractères restants`);
                
                if (remaining < 50) {
                    $counter.addClass('ufsc-char-counter-warning');
                } else {
                    $counter.removeClass('ufsc-char-counter-warning');
                }
            });
            
            $textarea.trigger('input'); // Initialize counter
        });
    }
    
    /**
     * Show error message for a specific field
     * 
     * @param {jQuery} $field The field element
     * @param {string} message Error message
     */
    function showFieldError($field, message) {
        clearFieldError($field);
        
        const $error = $('<div class="ufsc-field-error">' + message + '</div>');
        $field.addClass('ufsc-field-invalid').after($error);
    }
    
    /**
     * Clear error message for a specific field
     * 
     * @param {jQuery} $field The field element
     */
    function clearFieldError($field) {
        $field.removeClass('ufsc-field-invalid').next('.ufsc-field-error').remove();
    }
    
    /**
     * Validate email format
     * 
     * @param {string} email Email to validate
     * @return {boolean} True if valid
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Basic IBAN validation
     * 
     * @param {string} iban IBAN to validate
     * @return {boolean} True if valid format
     */
    function isValidIBAN(iban) {
        // Remove spaces and convert to uppercase
        iban = iban.replace(/\s+/g, '').toUpperCase();
        
        // Basic format check (2 letters, 2 digits, then alphanumeric)
        const ibanRegex = /^[A-Z]{2}\d{2}[A-Z0-9]+$/;
        
        return ibanRegex.test(iban) && iban.length >= 15 && iban.length <= 34;
    }
    
})(jQuery);