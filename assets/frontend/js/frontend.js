/* UFSC Frontend JavaScript */

jQuery(document).ready(function($) {
    
    // Dashboard navigation
    initDashboardNavigation();
    
    // Logo upload functionality
    initLogoUpload();
    
    // Form enhancements
    initFormEnhancements();
    
    // Import/Export functionality
    initImportExport();
    
    // Accessibility improvements
    initAccessibility();
    
    /**
     * Initialize dashboard navigation
     */
    function initDashboardNavigation() {
        var $tabs = $('.ufsc-nav-btn');
        var $triggers = $('.ufsc-nav-btn, .ufsc-tab-trigger');

        function updateRedirectFields() {
            var currentUrl = window.location.href;
            $('input[name="redirect_to"]').val(currentUrl);
        }

        function activateTab(section, focusPanel, updateUrl) {
            focusPanel = focusPanel !== false;
            updateUrl = updateUrl !== false;

            var $tab = $tabs.filter('[data-section="' + section + '"]');
            var $panel = $('#ufsc-section-' + section);

            if (!$tab.length || !$panel.length) {
                return;
            }

            $tabs.removeClass('active')
                 .attr({'aria-selected': 'false', tabindex: -1});
            $tab.addClass('active')
                .attr({'aria-selected': 'true', tabindex: 0});

            $('.ufsc-dashboard-section')
                .removeClass('active')
                .attr('hidden', true);
            $panel.addClass('active').removeAttr('hidden');

            if (focusPanel) {
                $panel.focus();
            } else {
                $tab.focus();
            }

            if (updateUrl) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', section);
                url.hash = section;
                history.replaceState(null, '', url.toString());
            }

            updateRedirectFields();
        }

        $triggers.on('click', function(e) {
            e.preventDefault();


            var section = $(this).data('section');

            // Update nav buttons
            $('.ufsc-nav-btn').removeClass('active');
            $(this).addClass('active');

            // Show corresponding section
            $('.ufsc-dashboard-section').removeClass('active');
            $('#ufsc-section-' + section).addClass('active');

            // Update URL with hash and tab parameter
            if (history.pushState) {
                var url = new URL(window.location.href);
                url.hash = section;
                url.searchParams.set('tab', section);
                history.pushState(null, '', url.toString());
            }

            // Focus management for accessibility
            $('#ufsc-section-' + section).focus();
        });

        // Handle URL hash or tab parameter on page load
        var params = new URLSearchParams(window.location.search);
        var target = params.get('tab') || window.location.hash.substring(1);
        if (target && $('.ufsc-nav-btn[data-section="' + target + '"]').length) {
            activateTab(target, true, false);
        }

        $tabs.on('keydown', function(e) {
            var index = $tabs.index(this);
            var newIndex;

            switch (e.key) {
                case 'ArrowRight':
                    newIndex = (index + 1) % $tabs.length;
                    break;
                case 'ArrowLeft':
                    newIndex = (index - 1 + $tabs.length) % $tabs.length;
                    break;
                case 'Home':
                    newIndex = 0;
                    break;
                case 'End':
                    newIndex = $tabs.length - 1;
                    break;
                default:
                    return;
            }

            e.preventDefault();
            activateTab($tabs.eq(newIndex).data('section'), false);
        });

        var params = new URLSearchParams(window.location.search);
        var initial = params.get('tab') || window.location.hash.substring(1);
        if (!initial || !$tabs.filter('[data-section="' + initial + '"]').length) {
            initial = $tabs.first().data('section');

        }

        activateTab(initial, false, false);

        $(window).on('popstate', function() {
            var p = new URLSearchParams(window.location.search);
            var section = p.get('tab') || window.location.hash.substring(1);
            if (section) {
                activateTab(section, false, false);
            }
        });
    }
    
    /**
     * Initialize logo upload functionality
     */
    function initLogoUpload() {
        // File input change handler
        $('input[name="club_logo"]').on('change', function() {
            var file = this.files[0];
            
            if (file) {
                // Validate file type
                var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'];
                if (allowedTypes.indexOf(file.type) === -1) {
                    alert(ufsc_frontend_vars.strings.invalid_file_type);
                    $(this).val('');
                    return;
                }
                
                // Validate file size (2MB max)
                var maxSize = 2 * 1024 * 1024; // 2MB in bytes
                if (file.size > maxSize) {
                    alert(ufsc_frontend_vars.strings.file_too_large);
                    $(this).val('');
                    return;
                }
                
                // Show preview
                showImagePreview(file, $(this).closest('.ufsc-logo-upload'));
            }
        });
        
        // Logo remove handler
        $('.ufsc-logo-remove').on('click', function(e) {
            e.preventDefault();
            
            if (confirm(ufsc_frontend_vars.strings.confirm_remove_logo)) {
                var clubId = $(this).data('club-id');
                removeLogo(clubId, $(this).closest('.ufsc-logo-preview'));
            }
        });
    }
    
    /**
     * Show image preview
     */
    function showImagePreview(file, container) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var preview = '<div class="ufsc-logo-preview-temp">' +
                         '<img src="' + e.target.result + '" alt="' + ufsc_frontend_vars.strings.logo_preview + '">' +
                         '<p class="ufsc-help-text">' + ufsc_frontend_vars.strings.logo_preview_text + '</p>' +
                         '</div>';
            
            container.after(preview);
            container.hide();
        };
        
        reader.readAsDataURL(file);
    }
    
    /**
     * Remove logo via AJAX
     */
    function removeLogo(clubId, container) {
        $.ajax({
            url: ufsc_frontend_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ufsc_remove_logo',
                club_id: clubId,
                nonce: ufsc_frontend_vars.nonce
            },
            beforeSend: function() {
                container.addClass('ufsc-loading');
            },
            success: function(response) {
                if (response.success) {
                    // Replace with upload form
                    var uploadForm = '<div class="ufsc-logo-upload">' +
                                   '<input type="file" id="club_logo" name="club_logo" accept="image/*">' +
                                   '<label for="club_logo" class="ufsc-upload-label">' + ufsc_frontend_vars.strings.choose_logo + '</label>' +
                                   '<p class="ufsc-help-text">' + ufsc_frontend_vars.strings.logo_help + '</p>' +
                                   '</div>';
                    
                    container.replaceWith(uploadForm);
                    
                    // Reinitialize file input
                    initLogoUpload();
                    
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(ufsc_frontend_vars.strings.ajax_error, 'error');
            },
            complete: function() {
                container.removeClass('ufsc-loading');
            }
        });
    }
    
    /**
     * Initialize form enhancements
     */
    function initFormEnhancements() {
        // Real-time validation
        $('input[type="email"]').on('blur', validateEmail);
        $('input[type="tel"]').on('blur', validatePhone);
        $('input[name="code_postal"]').on('blur', validatePostalCode);
        
        // Form submission with loading states
        $('form').on('submit', function() {
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
            
            // Add loading state
            $submitBtn.attr('aria-busy', 'true');
            $form.addClass('ufsc-loading');
            
            // Store original text
            var originalText = $submitBtn.text();
            $submitBtn.data('original-text', originalText);
            $submitBtn.text(ufsc_frontend_vars.strings.saving);
        });
        
        // Auto-hide success messages
        $('.ufsc-message.ufsc-success').delay(5000).fadeOut();
        
        // Character count for textareas
        $('textarea[maxlength]').each(function() {
            addCharacterCounter($(this));
        });
    }
    
    /**
     * Email validation
     */
    function validateEmail() {
        var email = $(this).val();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            showFieldError($(this), ufsc_frontend_vars.strings.invalid_email);
        } else {
            clearFieldError($(this));
        }
    }
    
    /**
     * Phone validation
     */
    function validatePhone() {
        var phone = $(this).val();
        var phoneRegex = /^[\d\s\-\+\(\)\.]{10,}$/;
        
        if (phone && !phoneRegex.test(phone)) {
            showFieldError($(this), ufsc_frontend_vars.strings.invalid_phone);
        } else {
            clearFieldError($(this));
        }
    }
    
    /**
     * Postal code validation
     */
    function validatePostalCode() {
        var postalCode = $(this).val();
        var postalRegex = /^\d{5}$/;
        
        if (postalCode && !postalRegex.test(postalCode)) {
            showFieldError($(this), ufsc_frontend_vars.strings.invalid_postal_code);
        } else {
            clearFieldError($(this));
        }
    }
    
    /**
     * Show field error
     */
    function showFieldError($field, message) {
        $field.addClass('ufsc-field-error');
        
        var $errorMsg = $field.siblings('.ufsc-field-error-msg');
        if ($errorMsg.length === 0) {
            $errorMsg = $('<div class="ufsc-field-error-msg" role="alert"></div>');
            $field.after($errorMsg);
        }
        
        $errorMsg.text(message);
    }
    
    /**
     * Clear field error
     */
    function clearFieldError($field) {
        $field.removeClass('ufsc-field-error');
        $field.siblings('.ufsc-field-error-msg').remove();
    }
    
    /**
     * Add character counter to textarea
     */
    function addCharacterCounter($textarea) {
        var maxLength = $textarea.attr('maxlength');
        var $counter = $('<div class="ufsc-char-counter"></div>');
        
        $textarea.after($counter);
        
        function updateCounter() {
            var remaining = maxLength - $textarea.val().length;
            $counter.text(remaining + ' ' + ufsc_frontend_vars.strings.characters_remaining);
            
            if (remaining < 20) {
                $counter.addClass('ufsc-warning');
            } else {
                $counter.removeClass('ufsc-warning');
            }
        }
        
        $textarea.on('input', updateCounter);
        updateCounter();
    }
    
    /**
     * Initialize import/export functionality
     */
    function initImportExport() {
        // Export buttons with progress feedback
        $('a[href*="ufsc_export"]').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);

            // Prevent multiple clicks
            if ($btn.data('exporting')) {
                return;
            }

            var originalText = $btn.text();
            $btn.data('original-text', originalText);
            $btn.data('exporting', true);

            // Build export URL
            var url = new URL($btn.attr('href'), window.location.href);

            // Append filter parameters if form exists
            var $form = $('.ufsc-filters-form');
            if ($form.length) {
                $form.serializeArray().forEach(function(field) {
                    if (field.value) {
                        var key = field.name.replace(/^ufsc_/, '');
                        url.searchParams.set(key, field.value);
                    }
                });
            }

            // Append selected licence IDs if available
            var selectedIds = $('input[name="licence_ids[]"]:checked').map(function() {
                return $(this).val();
            }).get();
            if (selectedIds.length) {
                selectedIds.forEach(id => url.searchParams.append('ids[]', id));
            }

            // UI feedback
            $btn.addClass('ufsc-loading').attr('aria-busy', 'true');
            $btn.text(ufsc_frontend_vars.strings.exporting);

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url.toString(), true);
            xhr.responseType = 'blob';

            xhr.onprogress = function(event) {
                if (event.lengthComputable) {
                    var percent = Math.round((event.loaded / event.total) * 100);
                    $btn.text(ufsc_frontend_vars.strings.exporting + ' ' + percent + '%');
                }
            };

            xhr.onload = function() {
                if (xhr.status === 200) {
                    var blob = xhr.response;
                    var downloadUrl = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    var disposition = xhr.getResponseHeader('Content-Disposition');
                    var filename = 'export.' + (url.searchParams.get('ufsc_export') === 'xlsx' ? 'xlsx' : 'csv');
                    if (disposition && disposition.indexOf('filename=') !== -1) {
                        var match = disposition.match(/filename="?([^";]+)"?/);
                        if (match && match[1]) {
                            filename = match[1];
                        }
                    }
                    a.href = downloadUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(downloadUrl);
                } else {
                    if (xhr.response && typeof xhr.response.text === 'function') {
                        xhr.response
                            .text()
                            .then(function(text) {
                                alert(text);
                            })
                            .catch(function() {
                                alert(ufsc_frontend_vars.strings.ajax_error);
                            });
                    } else {
                        alert(xhr.responseText || xhr.response || ufsc_frontend_vars.strings.ajax_error);
                    }
                }

                $btn.removeClass('ufsc-loading').removeAttr('aria-busy');
                $btn.text(originalText);
                $btn.data('exporting', false);
            };

            xhr.onerror = function() {
                alert(ufsc_frontend_vars.strings.ajax_error);
                $btn.removeClass('ufsc-loading').removeAttr('aria-busy');
                $btn.text(originalText);
                $btn.data('exporting', false);
            };

            xhr.send();
        });
        
        // CSV import preview
        $('form').on('submit', function(e) {
            if ($(this).find('input[name="ufsc_import_preview"]').length) {
                e.preventDefault();
                handleImportPreview($(this));
            }
        });
    }
    
    /**
     * Handle CSV import preview
     */
    function handleImportPreview($form) {
        var formData = new FormData($form[0]);
        
        $.ajax({
            url: ufsc_frontend_vars.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $form.addClass('ufsc-loading');
            },
            success: function(response) {
                if (response.success) {
                    showImportPreview(response.data);
                } else {
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showMessage(ufsc_frontend_vars.strings.ajax_error, 'error');
            },
            complete: function() {
                $form.removeClass('ufsc-loading');
            }
        });
    }
    
    /**
     * Show import preview
     */
    function showImportPreview(data) {
        var $modal = $('#ufsc-import-modal');
        var $content = $modal.find('.ufsc-modal-content');
        
        // Replace modal content with preview
        var previewHtml = '<h3>' + ufsc_frontend_vars.strings.import_preview + '</h3>';
        
        if (data.errors && data.errors.length > 0) {
            previewHtml += '<div class="ufsc-message ufsc-error">';
            previewHtml += '<h4>' + ufsc_frontend_vars.strings.import_errors + '</h4>';
            previewHtml += '<ul>';
            data.errors.forEach(function(error) {
                previewHtml += '<li>' + error + '</li>';
            });
            previewHtml += '</ul></div>';
        }
        
        if (data.preview && data.preview.length > 0) {
            previewHtml += '<div class="ufsc-import-preview">';
            previewHtml += '<h4>' + ufsc_frontend_vars.strings.preview_data + '</h4>';
            previewHtml += '<table class="ufsc-table">';
            previewHtml += '<thead><tr>';
            previewHtml += '<th>' + ufsc_frontend_vars.strings.name + '</th>';
            previewHtml += '<th>' + ufsc_frontend_vars.strings.first_name + '</th>';
            previewHtml += '<th>' + ufsc_frontend_vars.strings.email + '</th>';
            previewHtml += '<th>' + ufsc_frontend_vars.strings.status + '</th>';
            previewHtml += '</tr></thead><tbody>';
            
            data.preview.forEach(function(row) {
                previewHtml += '<tr>';
                previewHtml += '<td>' + (row.nom || '') + '</td>';
                previewHtml += '<td>' + (row.prenom || '') + '</td>';
                previewHtml += '<td>' + (row.email || '') + '</td>';
                previewHtml += '<td>' + (row.status || '') + '</td>';
                previewHtml += '</tr>';
            });
            
            previewHtml += '</tbody></table></div>';
            
            // Add import button if no errors
            if (!data.errors || data.errors.length === 0) {
                previewHtml += '<div class="ufsc-form-actions">';
                previewHtml += '<button type="button" class="ufsc-btn ufsc-btn-primary" onclick="confirmImport()">';
                previewHtml += ufsc_frontend_vars.strings.confirm_import;
                previewHtml += '</button>';
                previewHtml += '</div>';
            }
        }
        
        $content.html(previewHtml);
    }
    
    /**
     * Initialize accessibility improvements
     */
    function initAccessibility() {
        // Add ARIA labels to buttons without text
        $('button:empty, a:empty').each(function() {
            if (!$(this).attr('aria-label') && !$(this).attr('title')) {
                var $icon = $(this).find('i, svg');
                if ($icon.length) {
                    $(this).attr('aria-label', ufsc_frontend_vars.strings.button_action);
                }
            }
        });
        
        // Ensure form labels are properly associated
        $('input, select, textarea').each(function() {
            var $input = $(this);
            var id = $input.attr('id');
            
            if (id) {
                var $label = $('label[for="' + id + '"]');
                if ($label.length === 0) {
                    // Find label by proximity
                    $label = $input.closest('.ufsc-form-field').find('label');
                    if ($label.length) {
                        $label.attr('for', id);
                    }
                }
            }
        });
        
        // Add skip links for keyboard navigation
        if ($('.ufsc-club-dashboard').length && !$('.ufsc-skip-links').length) {
            var skipLinks = '<div class="ufsc-skip-links">';
            skipLinks += '<a href="#ufsc-dashboard-nav" class="ufsc-skip-link">' + ufsc_frontend_vars.strings.skip_to_nav + '</a>';
            skipLinks += '<a href="#ufsc-dashboard-content" class="ufsc-skip-link">' + ufsc_frontend_vars.strings.skip_to_content + '</a>';
            skipLinks += '</div>';
            
            $('.ufsc-club-dashboard').prepend(skipLinks);
        }
        
        // Manage focus for dynamic content
        $(document).on('click', '.ufsc-nav-btn', function() {
            var targetSection = $(this).data('section');
            setTimeout(function() {
                $('#ufsc-section-' + targetSection).attr('tabindex', '-1').focus();
            }, 100);
        });
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        var $message = $('<div class="ufsc-message ufsc-' + type + '" role="alert">' + message + '</div>');
        
        // Find container or create one
        var $container = $('.ufsc-dashboard-section.active, .ufsc-club-dashboard, .ufsc-content').first();
        if ($container.length === 0) {
            $container = $('body');
        }
        
        $container.prepend($message);
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 300);
    }
    
    /**
     * Debounce function for performance
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    // Apply debouncing to search inputs
    $('.ufsc-filter-group input[name="ufsc_search"]').on('input', debounce(function() {
        // Auto-submit search after typing stops
        if ($(this).val().length >= 3 || $(this).val().length === 0) {
            $(this).closest('form').submit();
        }
    }, 500));
    
});

/**
 * Global functions for inline handlers
 */
window.confirmImport = function() {
    if (confirm(ufsc_frontend_vars.strings.confirm_import_action)) {
        // Submit import form
        jQuery.ajax({
            url: ufsc_frontend_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'ufsc_import_commit',
                nonce: ufsc_frontend_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(ufsc_frontend_vars.strings.ajax_error);
            }
        });
    }
};

// CSS for field errors and other dynamic styles
jQuery(document).ready(function($) {
    var dynamicCSS = `
        .ufsc-field-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2) !important;
        }
        
        .ufsc-field-error-msg {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        
        .ufsc-char-counter {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
            margin-top: 0.25rem;
        }
        
        .ufsc-char-counter.ufsc-warning {
            color: #856404;
            font-weight: 500;
        }
        
        .ufsc-skip-links {
            position: absolute;
            top: -40px;
            left: 6px;
            z-index: 1000;
        }
        
        .ufsc-skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: #3498db;
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 1001;
        }
        
        .ufsc-skip-link:focus {
            top: 6px;
        }
        
        .ufsc-import-preview {
            margin: 1rem 0;
        }
        
        .ufsc-import-preview .ufsc-table {
            max-height: 300px;
            overflow-y: auto;
            display: block;
        }
        
        .ufsc-import-preview .ufsc-table thead {
            display: table-header-group;
        }
        
        .ufsc-import-preview .ufsc-table tbody {
            display: table-row-group;
        }
    `;
    
    $('<style>').prop('type', 'text/css').html(dynamicCSS).appendTo('head');
});