/* UFSC Frontend JavaScript */
jQuery(document).ready(function($) {
    
    // Form validation for licence form
    $('.ufsc-public-form form').on('submit', function(e) {
        var form = $(this);
        var errors = [];
        
        // Check required fields
        form.find('input[required], select[required]').each(function() {
            if (!$(this).val()) {
                errors.push($(this).prev('label').text() || $(this).attr('name'));
            }
        });
        
        // Email validation
        var email = form.find('input[name="email"]').val();
        if (email && !isValidEmail(email)) {
            errors.push('Email invalide');
        }
        
        // Display errors
        if (errors.length > 0) {
            e.preventDefault();
            showErrors(errors);
        }
    });
    
    // Email validation function
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Show errors function
    function showErrors(errors) {
        var errorHtml = '<div class="ufsc-alert ufsc-alert-error">';
        errorHtml += '<strong>Erreurs :</strong><ul>';
        errors.forEach(function(error) {
            errorHtml += '<li>' + error + '</li>';
        });
        errorHtml += '</ul></div>';
        
        $('.ufsc-public-form').prepend(errorHtml);
        
        // Scroll to error
        $('html, body').animate({
            scrollTop: $('.ufsc-alert-error').offset().top - 20
        }, 500);
        
        // Remove error after 5 seconds
        setTimeout(function() {
            $('.ufsc-alert-error').fadeOut();
        }, 5000);
    }
    
    // Auto-hide success messages
    setTimeout(function() {
        $('.ufsc-alert-success').fadeOut();
    }, 3000);
    
});