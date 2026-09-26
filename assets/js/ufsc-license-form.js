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
		initCompliancePanels();
		initFighterLevel();
    });

	function initFighterLevel() {
		const birth = $('#date_naissance');
		const sex = $('#sexe');
		const weight = $('#poids');
		const level = $('[data-ufsc-fighter-level]');
		const preview = $('[data-ufsc-sport-category-preview]');
		if (!birth.length || !level.length) return;
		let userSelected = Boolean(level.val());

		const ringWeights = {
			'cadette_15_F':[40,44,48,52,56,60,65],
			'cadet_15_M':[45,48,51,54,57,60,63.5,67,71,75,81],
			'junior_F':[40,44,48,52,56,60,65],
			'junior_M':[45,48,51,54,57,60,63.5,67,71,75,81],
			'senior_F':[48,52,56,60,65,70],
			'senior_M':[51,54,57,60,63.5,67,71,75,81,86,91]
		};
		const tatamiWeights = {
			'pre_M':[18,23,28,32,37,42,47], 'pre_F':[18,23,28,32,37,42,47],
			'poussin_M':[18,23,28,32,37,42,47], 'poussin_F':[18,23,28,32,37,42,47],
			'benjamin_M':[23,28,32,37,42,47,52], 'benjamin_F':[23,28,32,37,42,47,52],
			'minime_F':[28,32,37,42,46,50,55,60], 'minime_M':[28,32,37,42,47,52,57,63,69],
			'cadet_F':[37,42,46,50,55,60,65], 'cadet_M':[37,42,47,52,57,63,69,74],
			'junior_F':[42,46,50,55,60,65,70], 'junior_M':[47,52,57,63,69,74,79,84,89,94],
			'senior_F':[50,55,60,65,70], 'senior_M':[57,63,69,74,79,84,89,94],
			'veteran_F':[50,55,60,65,70], 'veteran_M':[57,63,69,74,79,84,89,94]
		};

		function sportAge() {
			const value = birth.val();
			if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
			const birthYear = parseInt(value.slice(0,4),10);
			const seasonStart = parseInt(level.attr('data-season-start-year'),10) || (new Date()).getFullYear();
			return seasonStart - birthYear;
		}
		function ageCategory(age, gender, ring) {
			if (ring) {
				if (age === 15) return {key:(gender==='F'?'cadette_15_F':'cadet_15_M'), label:(gender==='F'?'Cadette 2e année':'Cadet 2e année')};
				if (age >= 16 && age <= 17) return {key:'junior_'+gender,label:(gender==='F'?'Junior fille':'Junior garçon')};
				if (age >= 18 && age <= 40) return {key:'senior_'+gender,label:(gender==='F'?'Senior féminine':'Senior masculin')};
				return null;
			}
			if (age >= 6 && age <= 7) return {key:'pre_'+gender,label:'Pré-poussin'};
			if (age >= 8 && age <= 9) return {key:'poussin_'+gender,label:'Poussin'};
			if (age >= 10 && age <= 11) return {key:'benjamin_'+gender,label:'Benjamin'};
			if (age >= 12 && age <= 13) return {key:'minime_'+gender,label:'Minime'};
			if (age >= 14 && age <= 15) return {key:'cadet_'+gender,label:(gender==='F'?'Cadette':'Cadet')};
			if (age >= 16 && age <= 17) return {key:'junior_'+gender,label:(gender==='F'?'Junior fille':'Junior garçon')};
			if (age >= 18 && age <= 40) return {key:'senior_'+gender,label:(gender==='F'?'Senior féminine':'Senior masculin')};
			if (age >= 41 && age <= 50) return {key:'veteran_'+gender,label:(gender==='F'?'Vétéran féminine':'Vétéran masculin')};
			return null;
		}
		function weightCategory(limits, kilos) {
			if (!limits || !limits.length || !Number.isFinite(kilos)) return '';
			for (let i=0;i<limits.length;i++) if (kilos <= limits[i]) return '-' + limits[i] + ' kg';
			return '+' + limits[limits.length-1] + ' kg';
		}
		function refreshPreview(age) {
			if (!preview.length) return;
			const gender = sex.val();
			const kilos = parseFloat(String(weight.val() || '').replace(',','.'));
			const currentLevel = level.val();
			const ring = ['combat','classe_b','classe_a','pro'].indexOf(currentLevel) !== -1;
			if (age === null || (gender !== 'M' && gender !== 'F')) {
				preview.text('Renseignez la date de naissance et le sexe pour calculer la catégorie 2026/2027.');
				return;
			}
			const cat = ageCategory(age, gender, ring);
			if (!cat) {
				preview.text('Aucune catégorie de compétition détectée pour cette pratique et cet âge.');
				return;
			}
			const limits = (ring ? ringWeights : tatamiWeights)[cat.key] || [];
			const weightLabel = weightCategory(limits, kilos);
			preview.text('Catégorie détectée : ' + cat.label + (weightLabel ? ' — ' + weightLabel : ' — renseignez le poids'));
		}
		function refreshLevelOptions() {
			const age = sportAge();
			level.find('option').prop('hidden', false).prop('disabled', false);
			if (age === null || age < 0) { refreshPreview(age); return; }

			level.find('option[value="classe_b"],option[value="classe_a"],option[value="pro"]').prop('hidden', !(age >= 18 && age <= 40)).prop('disabled', !(age >= 18 && age <= 40));
			level.find('option[value="combat"]').prop('hidden', !(age >= 15 && age < 18)).prop('disabled', !(age >= 15 && age < 18));
			level.find('option[value="veteran"]').prop('hidden', !(age >= 41 && age <= 50)).prop('disabled', !(age >= 41 && age <= 50));
			if (level.find('option:selected').prop('disabled')) { level.val(''); userSelected = false; }
			if (!userSelected && !level.val()) {
				level.val(age < 18 ? 'assaut' : (age <= 40 ? 'classe_b' : 'veteran')).trigger('change.select2');
			}
			refreshPreview(age);
		}
		birth.on('change input', function() { if (!level.data('ufsc-manual-level')) userSelected = false; refreshLevelOptions(); });
		sex.on('change', refreshLevelOptions);
		weight.on('change input', refreshLevelOptions);
		level.on('change', function() { userSelected = Boolean(level.val()); if (document.activeElement === level[0]) level.data('ufsc-manual-level', true); refreshPreview(sportAge()); });
		refreshLevelOptions();
	}

	function initCompliancePanels() {
		const birth = $('#date_naissance');
		const role = $('#role');
		const adult = $('#ufsc-health-adult');
		const minor = $('#ufsc-health-minor');
		const honorability = $('#ufsc-honorability');
		const adultDocument = $('[data-ufsc-health-document="adult"]');
		const minorDocument = $('[data-ufsc-health-document="minor"]');
		function refresh() {
			const value = birth.val();
			const date = value ? new Date(value + 'T00:00:00') : null;
			const now = new Date();
			let age = date && !isNaN(date.getTime()) ? now.getFullYear() - date.getFullYear() : 18;
			if (date && (now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate()))) age--;
			const isMinor = age < 18;
			adult.prop('hidden', isMinor).find(':input').prop('disabled', isMinor);
			minor.prop('hidden', !isMinor).find(':input').prop('disabled', !isMinor);
			adultDocument.prop('hidden', isMinor);
			minorDocument.prop('hidden', !isMinor);
			const honorabilityRoles = ['president','secretaire','tresorier','dirigeant','entraineur','coach','educateur','encadrant','responsable_technique'];
			const needsHonorability = honorabilityRoles.indexOf(role.val()) !== -1;
			honorability.prop('hidden', !needsHonorability).find(':input').prop('disabled', !needsHonorability);
		}
		birth.on('change input', refresh);
		role.on('change', refresh);
		refresh();
	}

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
        function resetSubmitting(form) {
            form.removeData('ufscSubmitting');
            form.find('[aria-busy="true"]').each(function() {
                const button = $(this);
                const originalText = button.data('original-text');
                if (typeof originalText === 'string') button.text(originalText);
                button.removeAttr('aria-busy aria-disabled');
            });
        }
        $('.ufsc-licence-form')
        .off('.ufscSingleSubmit')
        .on('ufsc:resetSubmitting.ufscSingleSubmit', function() {
            resetSubmitting($(this));
        })
        .on('submit.ufscSingleSubmit', function(event) {
            const form = $(this);
            if (form.data('ufscSubmitting')) {
                event.preventDefault();
                return false;
            }
            form.data('ufscSubmitting', true);
            const submitter = event.originalEvent && event.originalEvent.submitter;
            const button = submitter ? $(submitter) : $();
            if (button.length) {
                button.attr('aria-disabled', 'true').attr('aria-busy', 'true');
                button.data('original-text', button.text()).append('…');
            }
            // Do not disable the clicked submitter: disabled controls are not
            // successful controls and would drop ufsc_submit_action.
            return true;
        });
    }

    // Initialize enhancements
    $(document).ready(function() {
        enhanceSaveButtons();
    });

})(jQuery);

/** Progressive six-step licence assistant; the unenhanced server form remains the no-JS fallback. */
(function($) {
    'use strict';
    $(function() {
        var form = $('.ufsc-licence-form').first();
        if (!form.length || !form.find('.ufsc-licence-wizard-progress').length) return;
        if (form.data('ufscWizardInitialized')) return;
        form.data('ufscWizardInitialized', true);
        var formScope = form.closest('.ufsc-add-licence-section');
        if (!formScope.length) formScope = form.parent();
        var cards = form.find('> .ufsc-grid > .ufsc-form-section');
        var compliance = form.find('.ufsc-compliance-section');
        var review = form.find('[data-wizard-review]');
        var finalActions = form.find('.ufsc-licence-final-actions');
        var current = Math.max(1, Math.min(6, Number(form.find('#ufsc_wizard_step').val()) || 1));
        var map = [1, 2, 3, 3, 4, 5, 5];
        cards.each(function(index) { $(this).attr('data-wizard-step', map[index] || 5); });
        compliance.attr('data-wizard-step', 5);
        review.attr('data-wizard-step', 6);
        finalActions.attr('data-wizard-step', 6);
        form.addClass('ufsc-licence-wizard-enhanced');

        function fieldLabel(input) {
            return $.trim(input.closest('.ufsc-field, label').find('label').first().text()) || input.attr('name');
        }
        function buildReview() {
            var list = review.find('dl').empty();
            ['nom','prenom','email','telephone','date_naissance','sexe','adresse','code_postal','ville','role','fighter_level','poids','numero_licence'].forEach(function(name) {
                var input = form.find('[name="' + name + '"]').first();
                if (!input.length) return;
                var value = input.is('select') ? input.find('option:selected').text() : input.val();
                $('<dt>').text(fieldLabel(input)).appendTo(list);
                $('<dd>').text($.trim(value || '') || 'Non renseigné').appendTo(list);
            });
        }
        function show(step) {
            current = Math.max(1, Math.min(6, step));
            form.attr('data-wizard-current-step', current); form.find('#ufsc_wizard_step').val(current);
            form.find('[data-wizard-step]').prop('hidden', true);
            form.find('[data-wizard-step="' + current + '"]').prop('hidden', false);
            form.find('[data-wizard-indicator]').removeAttr('aria-current').filter('[data-wizard-indicator="' + current + '"]').attr('aria-current', 'step');
            form.find('[data-wizard-previous]').prop('disabled', current === 1);
            form.find('[data-wizard-next]').toggle(current < 6).text(current === 5 ? 'Vérifier les informations' : 'Continuer');
            if (current === 6) buildReview();
            var heading = form.find('[data-wizard-step="' + current + '"] h4, [data-wizard-step="' + current + '"] h5').first()[0];
            if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus({preventScroll: true}); }
        }
        function validateStep() {
            var invalid = [];
            form.find('[data-wizard-step="' + current + '"] :input:enabled[required]').each(function() {
                if (!this.checkValidity()) { invalid.push(this); $(this).attr('aria-invalid', 'true'); }
                else $(this).removeAttr('aria-invalid');
            });
            var summary = form.find('.ufsc-licence-wizard-errors');
            if (!invalid.length) { summary.prop('hidden', true).empty(); return true; }
            summary.text('Veuillez corriger ' + invalid.length + ' champ(s) obligatoire(s) avant de continuer.').prop('hidden', false).focus();
            invalid[0].focus(); return false;
        }
        form.on('click', '[data-wizard-next]', function() { if (validateStep()) show(current + 1); });
        form.on('click', '[data-wizard-previous]', function() { show(current - 1); });
        form.on('click', '[data-wizard-indicator]', function() {
            var target = Number($(this).attr('data-wizard-indicator'));
            if (target < current || validateStep()) show(target);
        });
        form.on('submit', function(event) {
            // Submit-button name/value is the no-JavaScript contract and remains
            // reliable under strict CSP. `originalEvent.submitter` also prevents a
            // stale hidden value from turning an add-to-cart click into a save.
            var submitter = event.originalEvent && event.originalEvent.submitter;
            var action = submitter && submitter.name === 'ufsc_submit_action'
                ? submitter.value
                : form.find('#ufsc_submit_action').val();
            if (action === 'save_draft') {
                var nom=form.find('[name="nom"]')[0], prenom=form.find('[name="prenom"]')[0];
                if (!nom.value.trim() || !prenom.value.trim()) { event.preventDefault(); form.triggerHandler('ufsc:resetSubmitting'); show(1); (!nom.value.trim() ? nom : prenom).focus(); return false; }
                return true; // Drafts intentionally accept all other incomplete data.
            }
            if (!this.checkValidity()) {
                event.preventDefault();
                form.triggerHandler('ufsc:resetSubmitting');
                var first = form.find(':invalid').first();
                var owner = Number(first.closest('[data-wizard-step]').attr('data-wizard-step')) || 1;
                show(owner); first.focus();
            }
        });
        var previousToggle = form.find('#has_license_number');
        var previousField = form.find('[data-depends="has_license_number"]');
        function syncPreviousNumber() {
            var enabled = previousToggle.is(':checked');
            previousField.prop('hidden', !enabled);
            previousField.find('input').prop('required', enabled).prop('disabled', !enabled);
            if (!enabled) previousField.find('input').val('');
        }
        previousToggle.on('change', syncPreviousNumber); syncPreviousNumber();
        show(current);
        formScope.find('[data-ufsc-error-field]').on('click.ufscWizardErrors', function(event) {
            var field = String($(this).data('ufsc-error-field') || '');
            var step = Number($(this).data('ufsc-error-step')) || 1;
            var input = field ? form.find('#' + field).first() : $();
            if (!input.length) return;
            event.preventDefault();
            show(step);
            input.attr('aria-invalid', 'true').focus();
        });
        var serverSummary = formScope.find('[data-ufsc-server-errors]').first();
        if (serverSummary.length) {
            serverSummary.focus();
            var firstServerError = serverSummary.find('[data-ufsc-error-field]').first();
            if (firstServerError.length) firstServerError.trigger('click');
        }
    });
})(jQuery);
