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
`;

// Inject additional CSS
if (!document.getElementById('ufsc-dynamic-css')) {
    var style = document.createElement('style');
    style.id = 'ufsc-dynamic-css';
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}