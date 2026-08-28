/**
 * UFSC Club Form JavaScript
 * Canonical client layer for conditional fields, validation, premium wizard
 * and non-sensitive draft recovery after server-side validation redirects.
 */
(function($) {
    'use strict';

    const DRAFT_TTL_MS = 6 * 60 * 60 * 1000;

    $(document).ready(function() {
        $('.ufsc-club-form').each(function() {
            const $form = $(this);
            initConditionalFields($form);
            initLicenseConditionalFields($form);
            initDraftPersistence($form);
            initFormValidation($form);
            initPremiumWizard($form);
            initFormEnhancements($form);
        });
    });

    function initLicenseConditionalFields($form) {
        const $licenseToggle = $form.find('#has_license_number');
        const $licenseNumberField = $form.find('.ufsc-conditional-field[data-depends="has_license_number"]');

        if ($licenseToggle.length && $licenseNumberField.length) {
            toggleConditionalField($licenseToggle, $licenseNumberField);
            $licenseToggle.on('change.ufscClub', function() {
                toggleConditionalField($(this), $licenseNumberField);
            });
        }

        $form.find('.ufsc-toggle').each(function() {
            const $toggle = $(this);
            const dependsOn = $toggle.attr('name');
            const $dependentFields = $form.find('.ufsc-conditional-field[data-depends="' + dependsOn + '"]');
            if (!$dependentFields.length) return;

            toggleConditionalField($toggle, $dependentFields);
            $toggle.on('change.ufscClub', function() {
                toggleConditionalField($(this), $dependentFields);
            });
        });
    }

    function toggleConditionalField($toggle, $dependentFields) {
        const visible = $toggle.is(':checked');
        $dependentFields.toggle(visible);
        $dependentFields.find('input, select, textarea').prop('required', visible);
        if (!visible) {
            $dependentFields.find('input:not([type="hidden"]), select, textarea').each(function() {
                if (this.type === 'checkbox' || this.type === 'radio') {
                    this.checked = false;
                } else if (this.type !== 'file') {
                    $(this).val('');
                }
            });
        }
    }

    function initConditionalFields($form) {
        const $radios = $form.find('input[name="user_association"]');
        const $createUserFields = $form.find('#create-user-fields');
        const $existingUserFields = $form.find('#existing-user-fields');
        if (!$radios.length) return;

        function refresh() {
            const selected = $radios.filter(':checked').val() || 'current';
            $createUserFields.toggle(selected === 'create');
            $existingUserFields.toggle(selected === 'existing');
            toggleRequiredFields($createUserFields, selected === 'create');
            toggleRequiredFields($existingUserFields, selected === 'existing');
        }

        $radios.on('change.ufscClub', refresh);
        refresh();
    }

    function toggleRequiredFields($container, required) {
        $container.find('input, select, textarea').each(function() {
            $(this).prop('required', required);
        });
    }

    /* ------------------------------------------------------------------
     * Draft persistence
     * ------------------------------------------------------------------ */
    function draftKey($form) {
        const action = $form.attr('action') || '';
        const clubId = $form.find('input[name="club_id"]').val() || 'new';
        return 'ufsc_club_form_draft_v2|' + window.location.pathname + '|' + action + '|' + clubId;
    }

    function isPersistableField(el) {
        if (!el.name || el.disabled) return false;
        const type = (el.type || '').toLowerCase();
        if (['file', 'password', 'submit', 'button', 'reset'].includes(type)) return false;
        if (el.name === 'ufsc_club_nonce' || el.name === '_wpnonce' || el.name === '_wp_http_referer') return false;
        if (type === 'hidden' && !['club_id', 'affiliation', 'user_association'].includes(el.name)) return false;
        return true;
    }

    function serializeDraft($form) {
        const data = {};
        $form.find('input, select, textarea').each(function() {
            if (!isPersistableField(this)) return;
            const type = (this.type || '').toLowerCase();
            if (type === 'checkbox') {
                data[this.name] = !!this.checked;
            } else if (type === 'radio') {
                if (this.checked) data[this.name] = this.value;
            } else {
                data[this.name] = $(this).val();
            }
        });
        return { savedAt: Date.now(), data: data };
    }

    function saveDraft($form) {
        try {
            window.sessionStorage.setItem(draftKey($form), JSON.stringify(serializeDraft($form)));
        } catch (e) {
            // Storage can be unavailable in strict/private contexts. The form
            // remains fully functional; draft recovery is progressive enhancement.
        }
    }

    function readDraft($form) {
        try {
            const raw = window.sessionStorage.getItem(draftKey($form));
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.savedAt || !parsed.data) return null;
            if ((Date.now() - parsed.savedAt) > DRAFT_TTL_MS) {
                window.sessionStorage.removeItem(draftKey($form));
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function restoreDraft($form) {
        const draft = readDraft($form);
        if (!draft) return false;
        let restored = false;

        Object.keys(draft.data).forEach(function(name) {
            const value = draft.data[name];
            const $fields = $form.find('[name="' + cssEscape(name) + '"]');
            if (!$fields.length) return;

            const type = (($fields.first().attr('type') || '') + '').toLowerCase();
            if (type === 'radio') {
                const $target = $fields.filter('[value="' + cssEscape(String(value)) + '"]');
                if ($target.length) {
                    $fields.prop('checked', false);
                    $target.prop('checked', true).trigger('change');
                    restored = true;
                }
            } else if (type === 'checkbox') {
                $fields.prop('checked', !!value).trigger('change');
                restored = true;
            } else {
                const current = $fields.first().val();
                if ((current === '' || current === null) && value !== undefined && value !== null && value !== '') {
                    $fields.val(value).trigger('change');
                    restored = true;
                }
            }
        });

        return restored;
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
        return String(value).replace(/([ #;?%&,.+*~\':"!^$[\]()=>|\/])/g, '\\$1');
    }

    function initDraftPersistence($form) {
        let saveTimer = null;
        const debouncedSave = function() {
            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(function() { saveDraft($form); }, 250);
        };

        $form.on('input.ufscDraft change.ufscDraft', 'input, select, textarea', debouncedSave);

        const hasServerError = new URLSearchParams(window.location.search).has('ufsc_error');
        const restored = restoreDraft($form);
        if (restored || hasServerError) {
            const $notice = $('<div class="ufsc-draft-recovery" role="status"><div><strong>Vos informations ont été conservées</strong><span>Le formulaire a récupéré les données saisies avant l’erreur. Pour des raisons de sécurité du navigateur, les fichiers joints doivent être sélectionnés à nouveau si nécessaire.</span></div></div>');
            $form.prepend($notice);
        }

        // Keep the latest scalar state just before the POST. If PHP rejects the
        // request and redirects back with ufsc_error, values can be restored.
        $form.on('submit.ufscDraft', function() { saveDraft($form); });
    }

    /* ------------------------------------------------------------------
     * Premium wizard
     * ------------------------------------------------------------------ */
    function initPremiumWizard($form) {
        if ($form.data('ufsc-wizard-ready')) return;
        $form.data('ufsc-wizard-ready', true);

        const $allSections = $form.find('fieldset.ufsc-form-section');
        if ($allSections.length < 3) return;

        const $general = sectionByLegend($allSections, ['Informations générales']);
        const $web = sectionByLegend($allSections, ['Logo & Web']);
        const $legal = sectionByLegend($allSections, ['Informations légales et financières']);
        const $documents = sectionByLegend($allSections, ['Mes documents']);
        const $officers = $form.find('fieldset.ufsc-dirigeants').first();
        const $association = sectionByLegend($allSections, ['Association utilisateur']);
        const $admin = sectionByLegend($allSections, ['Administration']);

        // Detach nested logical sections before hiding any legacy wrapper. Input
        // names, nonce, form action and server handlers are left untouched.
        const groups = [
            [$general, $web],
            [$legal],
            [$documents],
            [$officers, $association, $admin]
        ];

        const labels = [
            ['Club', 'Coordonnées & identité'],
            ['Administration', 'Données légales'],
            ['Documents', 'Pièces du dossier'],
            ['Bureau', 'Dirigeants & validation']
        ];

        const $title = $form.children('h2').first();
        const $anchor = $title.length ? $title : $form.children().first();
        const $wizard = $('<div class="ufsc-premium-wizard" aria-label="Assistant de création du club"></div>');
        $wizard.append('<span class="ufsc-premium-wizard__eyebrow">Dossier d’affiliation UFSC</span>');
        $wizard.append('<h3 class="ufsc-premium-wizard__title">Complétez votre club étape par étape</h3>');
        $wizard.append('<p class="ufsc-premium-wizard__subtitle">Les informations sont réparties en quatre étapes courtes. Vous pouvez revenir en arrière sans perdre votre saisie.</p>');
        const $nav = $('<div class="ufsc-premium-wizard__nav" role="tablist"></div>');

        const panes = [];
        groups.forEach(function(group, index) {
            const paneId = 'ufsc-club-step-' + (index + 1);
            const $pane = $('<section class="ufsc-wizard-pane" role="tabpanel" id="' + paneId + '"></section>');
            group.forEach(function($section) {
                if ($section && $section.length) $pane.append($section.detach());
            });
            panes.push($pane);

            const $tab = $('<button type="button" class="ufsc-premium-wizard__tab" role="tab" aria-controls="' + paneId + '"><span class="ufsc-premium-wizard__index">' + (index + 1) + '</span><span><span class="ufsc-premium-wizard__label">' + labels[index][0] + '</span><span class="ufsc-premium-wizard__help">' + labels[index][1] + '</span></span></button>');
            $tab.attr('data-step', index);
            $nav.append($tab);
        });

        $wizard.append($nav);
        $anchor.after($wizard);
        panes.forEach(function($pane) { $wizard.after($pane); });

        // Improve the bureau cards without changing the historical database model:
        // postal code and city are entered separately in the UI, then stored in
        // the existing *_adresse value as a conventional two-line postal address.
        enhanceOfficerCards($form);

        // Move the honorability message inside the Bureau step instead of leaving
        // a detached notice below the full form.
        const $honorability = $('.ufsc-onboarding-honorability').first();
        if ($honorability.length && panes[3]) {
            $honorability.detach().appendTo(panes[3]);
        }

        // Hide now-empty legacy wrappers created by the historical nested markup.
        // The old "Documents légaux" fieldset is only a structural wrapper around
        // "Mes documents" and must never remain as an empty card in the wizard.
        $allSections.each(function() {
            const $section = $(this);
            const legend = $.trim($section.children('legend').first().text());
            const isLegacyDocumentsWrapper = legend === 'Documents légaux';
            if (!$section.closest('.ufsc-wizard-pane').length && (isLegacyDocumentsWrapper || $section.find(':input').length === 0)) {
                $section.hide().attr('aria-hidden', 'true');
            }
        });

        const $originalActions = $form.find('.ufsc-form-actions').first();
        if ($originalActions.length) {
            $originalActions.detach().appendTo(panes[3]);
        }

        panes.forEach(function($pane, index) {
            if (index === 3) return;
            const $controls = $('<div class="ufsc-wizard-controls"></div>');
            if (index > 0) {
                $controls.append('<button type="button" class="ufsc-wizard-btn ufsc-wizard-btn--back">← Étape précédente</button>');
            } else {
                $controls.append('<span aria-hidden="true"></span>');
            }
            $controls.append('<button type="button" class="ufsc-wizard-btn ufsc-wizard-btn--next">Continuer →</button>');
            $pane.append($controls);
        });

        panes[3].find('.ufsc-form-actions').prepend('<button type="button" class="ufsc-wizard-btn ufsc-wizard-btn--back">← Étape précédente</button>');

        let currentStep = 0;

        function showStep(index, focusTab) {
            index = Math.max(0, Math.min(index, panes.length - 1));
            currentStep = index;
            panes.forEach(function($pane, i) {
                const active = i === index;
                $pane.prop('hidden', !active).attr('aria-hidden', active ? 'false' : 'true');
            });
            $nav.find('.ufsc-premium-wizard__tab').each(function(i) {
                $(this)
                    .toggleClass('is-active', i === index)
                    .toggleClass('is-complete', i < index)
                    .attr('aria-selected', i === index ? 'true' : 'false')
                    .attr('tabindex', i === index ? '0' : '-1');
            });
            if (focusTab) {
                const tab = $nav.find('.ufsc-premium-wizard__tab').get(index);
                if (tab) tab.focus({preventScroll: true});
            }
            window.setTimeout(function() {
                const top = $wizard.offset().top - 110;
                if (window.scrollY > top + 220 || window.scrollY < top - 350) {
                    window.scrollTo({top: Math.max(0, top), behavior: 'smooth'});
                }
            }, 20);
        }

        function validatePane(index) {
            const $pane = panes[index];
            clearPaneErrors($pane);
            let firstInvalid = null;

            $pane.find(':input[required]:visible').each(function() {
                if (!fieldHasValue(this)) {
                    showFieldError($(this), 'Ce champ est requis');
                    if (!firstInvalid) firstInvalid = this;
                }
            });

            if (firstInvalid) {
                firstInvalid.focus({preventScroll: true});
                firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                return false;
            }
            return true;
        }

        $nav.on('click.ufscWizard', '.ufsc-premium-wizard__tab', function() {
            const target = parseInt($(this).attr('data-step'), 10);
            if (target > currentStep && !validatePane(currentStep)) return;
            showStep(target, true);
        });

        $form.on('click.ufscWizard', '.ufsc-wizard-btn--next', function() {
            if (!validatePane(currentStep)) return;
            showStep(currentStep + 1, true);
        });

        $form.on('click.ufscWizard', '.ufsc-wizard-btn--back', function() {
            showStep(currentStep - 1, true);
        });

        $form.on('ufsc:first-invalid', function(e, field) {
            const $pane = $(field).closest('.ufsc-wizard-pane');
            const index = panes.indexOf($pane);
            if (index >= 0) showStep(index, false);
        });

        showStep(0, false);
    }

    function splitStoredOfficerAddress(value) {
        const lines = String(value || '')
            .split(/\r?\n/)
            .map(function(line) { return $.trim(line); })
            .filter(Boolean);
        let postalCode = '';
        let city = '';
        if (lines.length > 1) {
            const match = lines[lines.length - 1].match(/^(\d{5})\s+(.+)$/);
            if (match) {
                postalCode = match[1];
                city = match[2];
                lines.pop();
            }
        }
        return {
            street: lines.join(' '),
            postalCode: postalCode,
            city: city
        };
    }

    function enhanceOfficerCards($form) {
        $form.find('.ufsc-dirigeant-section').each(function() {
            const $card = $(this);
            const $address = $card.find('input[name$="_adresse"]').first();
            if (!$address.length || $card.find('.ufsc-officer-postal-field').length) return;

            const addressName = $address.attr('name') || '';
            const prefix = addressName.replace(/_adresse$/, '');
            if (!prefix) return;

            const required = $address.prop('required');
            const parsed = splitStoredOfficerAddress($address.val());
            $address.val(parsed.street);

            const postalId = prefix + '_code_postal_ui';
            const cityId = prefix + '_ville_ui';
            const requiredClass = required ? ' required' : '';
            const requiredAttr = required ? ' required' : '';

            const $postal = $(
                '<div class="ufsc-field ufsc-officer-postal-field">' +
                    '<label class="ufsc-label' + requiredClass + '" for="' + postalId + '">Code postal</label>' +
                    '<input type="text" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" id="' + postalId + '" name="' + postalId + '"' + requiredAttr + ' />' +
                    '<div class="ufsc-field-error" aria-live="polite"></div>' +
                '</div>'
            );
            const $city = $(
                '<div class="ufsc-field ufsc-officer-postal-field">' +
                    '<label class="ufsc-label' + requiredClass + '" for="' + cityId + '">Ville</label>' +
                    '<input type="text" id="' + cityId + '" name="' + cityId + '"' + requiredAttr + ' />' +
                    '<div class="ufsc-field-error" aria-live="polite"></div>' +
                '</div>'
            );

            $postal.find('input').val(parsed.postalCode);
            $city.find('input').val(parsed.city);

            const $addressField = $address.closest('.ufsc-field');
            if ($addressField.length) {
                $addressField.after($city).after($postal);
            } else {
                $card.append($postal, $city);
            }

            // The card heading already tells the user whether this person is the
            // president, secretary, treasurer or coach. "Poste" therefore means
            // profession here, not the person's function in the club.
            const $job = $card.find('input[name="' + prefix + '_poste"]').first();
            if ($job.length) {
                const $jobLabel = $card.find('label[for="' + prefix + '_poste"]').first();
                if ($jobLabel.length) $jobLabel.text('Profession');
            }
        });

        $form.on('submit.ufscOfficerAddress', function() {
            $form.find('.ufsc-dirigeant-section').each(function() {
                const $card = $(this);
                const $address = $card.find('input[name$="_adresse"]').first();
                if (!$address.length) return;
                const prefix = ($address.attr('name') || '').replace(/_adresse$/, '');
                if (!prefix) return;

                const $postal = $card.find('input[name="' + prefix + '_code_postal_ui"]').first();
                const $city = $card.find('input[name="' + prefix + '_ville_ui"]').first();
                if (!$postal.length || !$city.length) return;

                const parsed = splitStoredOfficerAddress($address.val());
                const street = $.trim(parsed.street || $address.val() || '');
                const postalCode = $.trim($postal.val() || '');
                const city = $.trim($city.val() || '');
                const locality = $.trim(postalCode + ' ' + city);
                $address.val([street, locality].filter(Boolean).join('\n'));
            });
        });
    }

    function sectionByLegend($sections, labels) {
        let found = $();
        $sections.each(function() {
            const $section = $(this);
            const legend = $.trim($section.children('legend').first().text());
            if (labels.indexOf(legend) !== -1 && !found.length) found = $section;
        });
        return found;
    }

    function fieldHasValue(el) {
        const type = (el.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
            return $(el.form).find('[name="' + cssEscape(el.name) + '"]:checked').length > 0;
        }
        if (type === 'file') return !!(el.files && el.files.length);
        return $.trim($(el).val() || '') !== '';
    }

    /* ------------------------------------------------------------------
     * Validation
     * ------------------------------------------------------------------ */
    function initFormValidation($form) {
        $form.find('input[name="iban"]').on('blur.ufscClub', function() {
            const iban = $.trim($(this).val());
            if (iban && !isValidIBAN(iban)) showFieldError($(this), 'Format IBAN invalide');
            else clearFieldError($(this));
        });

        $form.on('blur.ufscClub', 'input[name="code_postal"], input[name$="_code_postal_ui"]', function() {
            const postalCode = $.trim($(this).val());
            if (postalCode && !/^\d{5}$/.test(postalCode)) showFieldError($(this), 'Le code postal doit contenir 5 chiffres');
            else clearFieldError($(this));
        });

        $form.find('input[type="email"]').on('blur.ufscClub', function() {
            const email = $.trim($(this).val());
            if (email && !isValidEmail(email)) showFieldError($(this), 'Format d\'email invalide');
            else clearFieldError($(this));
        });

        $form.find('input[type="file"]').on('change.ufscClub', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            const $input = $(this);
            const isLogo = $input.attr('name') === 'logo_upload';
            const maxSize = isLogo ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
            if (file.size > maxSize) {
                showFieldError($input, 'Le fichier est trop volumineux. Taille maximum : ' + (maxSize / 1024 / 1024) + ' MB');
                $input.val('');
            } else {
                clearFieldError($input);
            }
        });

        $form.on('submit.ufscValidation', function(e) {
            clearPaneErrors($form);
            let firstInvalid = null;
            $form.find(':input[required]:visible').each(function() {
                if (!fieldHasValue(this)) {
                    showFieldError($(this), 'Ce champ est requis');
                    if (!firstInvalid) firstInvalid = this;
                }
            });

            if (firstInvalid) {
                e.preventDefault();
                $form.trigger('ufsc:first-invalid', [firstInvalid]);
                window.setTimeout(function() {
                    firstInvalid.focus({preventScroll: true});
                    firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                }, 40);
                return false;
            }
        });
    }

    function clearPaneErrors($scope) {
        $scope.find('.ufsc-field-invalid').removeClass('ufsc-field-invalid');
        $scope.find('.ufsc-field-error').text('').removeAttr('role');
    }

    function showFieldError($field, message) {
        clearFieldError($field);
        let $error = $field.closest('.ufsc-field').find('.ufsc-field-error').first();
        if (!$error.length) {
            $error = $('<div class="ufsc-field-error" aria-live="polite"></div>');
            $field.after($error);
        }
        $field.addClass('ufsc-field-invalid').attr('aria-invalid', 'true');
        $error.attr('role', 'alert').text(message);
    }

    function clearFieldError($field) {
        $field.removeClass('ufsc-field-invalid').removeAttr('aria-invalid');
        $field.closest('.ufsc-field').find('.ufsc-field-error').first().text('').removeAttr('role');
    }

    /* ------------------------------------------------------------------
     * Enhancements
     * ------------------------------------------------------------------ */
    function initFormEnhancements($form) {
        $form.on('submit.ufscLoading', function(e) {
            if (e.isDefaultPrevented()) return;
            const $submitBtn = $form.find('button[type="submit"]').first();
            if (!$submitBtn.length) return;
            const originalText = $submitBtn.text();
            $submitBtn.data('ufsc-original-text', originalText).prop('disabled', true).text('Enregistrement…');
            window.setTimeout(function() {
                $submitBtn.prop('disabled', false).text($submitBtn.data('ufsc-original-text') || originalText);
            }, 30000);
        });

        $form.find('textarea[maxlength]').each(function() {
            const $textarea = $(this);
            if ($textarea.next('.ufsc-char-counter').length) return;
            const maxLength = parseInt($textarea.attr('maxlength'), 10);
            const $counter = $('<div class="ufsc-char-counter"></div>');
            $textarea.after($counter);
            $textarea.on('input.ufscClub', function() {
                const remaining = maxLength - ($(this).val() || '').length;
                $counter.text(remaining + ' caractères restants').toggleClass('ufsc-char-counter-warning', remaining < 50);
            }).trigger('input');
        });
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function isValidIBAN(iban) {
        iban = iban.replace(/\s+/g, '').toUpperCase();
        return /^[A-Z]{2}\d{2}[A-Z0-9]+$/.test(iban) && iban.length >= 15 && iban.length <= 34;
    }

})(jQuery);
