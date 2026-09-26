(function () {
    'use strict';

    var cfg = window.UFSCAddressAssist || {};
    if (!cfg.ajaxUrl) return;

    var timers = new WeakMap();

    function normalizedCountry(value) {
        return String(value || '').trim().toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function isFrance(value) {
        var v = normalizedCountry(value);
        return !v || v === 'france' || v === 'fr' || v === 'f';
    }

    function fieldByName(form, name) {
        if (!form || !name) return null;
        var nodes = form.querySelectorAll('[name]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].name === name) return nodes[i];
        }
        return null;
    }

    function deriveNames(postalName) {
        if (!postalName) return null;

        var bracket = postalName.match(/^(.*\[)code_postal(\])$/);
        if (bracket) {
            return {
                city: bracket[1] + 'ville' + bracket[2],
                country: bracket[1] + 'pays' + bracket[2]
            };
        }

        if (postalName === 'code_postal') {
            return { city: 'ville', country: 'pays' };
        }

        if (/_code_postal$/.test(postalName)) {
            return {
                city: postalName.replace(/_code_postal$/, '_ville'),
                country: postalName.replace(/_code_postal$/, '_pays')
            };
        }

        if (/^code_postal_/.test(postalName)) {
            return {
                city: postalName.replace(/^code_postal_/, 'ville_'),
                country: postalName.replace(/^code_postal_/, 'pays_')
            };
        }

        return null;
    }

    function buildCountrySelect(input) {
        if (!input || input.tagName === 'SELECT' || input.dataset.ufscCountryEnhanced === '1') return input;

        var select = document.createElement('select');
        Array.prototype.slice.call(input.attributes).forEach(function (attr) {
            if (attr.name !== 'type' && attr.name !== 'value') select.setAttribute(attr.name, attr.value);
        });

        var current = String(input.value || '').trim();
        var values = [];
        var countries = cfg.countries || {};

        Object.keys(countries).forEach(function (code) {
            var label = String(countries[code] || '').trim();
            if (!label || values.indexOf(label) !== -1) return;
            values.push(label);
            var option = document.createElement('option');
            option.value = label;
            option.textContent = label;
            select.appendChild(option);
        });

        if (current && values.indexOf(current) === -1) {
            var legacy = document.createElement('option');
            legacy.value = current;
            legacy.textContent = current;
            select.insertBefore(legacy, select.firstChild);
        }

        if (!current) current = cfg.defaultCountry || 'France';
        select.value = current;
        select.dataset.ufscCountryEnhanced = '1';
        input.parentNode.replaceChild(select, input);

        return select;
    }

    function createCitySuggestions(cityInput) {
        var wrapper = cityInput.parentElement;
        var existing = wrapper && wrapper.querySelector('[data-ufsc-city-suggestions]');
        if (existing) return existing;

        var box = document.createElement('div');
        box.setAttribute('data-ufsc-city-suggestions', '1');
        box.style.display = 'none';
        box.style.marginTop = '6px';
        box.style.gap = '6px';
        box.style.flexWrap = 'wrap';
        box.style.alignItems = 'center';

        cityInput.insertAdjacentElement('afterend', box);
        return box;
    }

    function renderCities(cityInput, cities) {
        var box = createCitySuggestions(cityInput);
        box.innerHTML = '';

        if (!cities || !cities.length) {
            box.style.display = 'none';
            return;
        }

        var label = document.createElement('span');
        label.textContent = (cfg.strings && cfg.strings.citySuggestion) || 'Ville proposée :';
        label.style.fontSize = '12px';
        box.appendChild(label);

        cities.forEach(function (city) {
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = city;
            button.className = 'button button-secondary ufsc-city-suggestion';
            button.style.minHeight = '30px';
            button.addEventListener('click', function () {
                cityInput.value = city;
                cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                cityInput.dispatchEvent(new Event('change', { bubbles: true }));
                box.style.display = 'none';
            });
            box.appendChild(button);
        });

        box.style.display = 'flex';
    }

    function renderLookupError(cityInput) {
        var box = createCitySuggestions(cityInput);
        box.innerHTML = '';
        var note = document.createElement('span');
        note.textContent = (cfg.strings && cfg.strings.lookupFailed) || 'Suggestion indisponible. La saisie manuelle reste possible.';
        note.style.fontSize = '12px';
        box.appendChild(note);
        box.style.display = 'flex';
    }

    function lookupPostalCode(postalInput, cityInput, countryInput) {
        var postal = String(postalInput.value || '').trim();
        if (!/^\d{5}$/.test(postal)) {
            renderCities(cityInput, []);
            return;
        }

        if (countryInput && !isFrance(countryInput.value)) {
            renderCities(cityInput, []);
            return;
        }

        var body = new URLSearchParams();
        body.set('action', 'ufsc_address_lookup_postal_code');
        body.set('nonce', cfg.nonce || '');
        body.set('postal_code', postal);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (response) {
                if (!response.ok) throw new Error('lookup');
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data) throw new Error('lookup');
                renderCities(cityInput, payload.data.cities || []);
            })
            .catch(function () {
                renderLookupError(cityInput);
            });
    }

    function enhanceCountryField(input) {
        if (!input || input.dataset.ufscCountryEnhanced === '1') return;
        var select = buildCountrySelect(input);
        if (select) {
            select.dataset.ufscCountryEnhanced = '1';
            select.setAttribute('autocomplete', 'country-name');
        }
    }

    function enhancePostal(postalInput) {
        if (!postalInput || postalInput.dataset.ufscAddressEnhanced === '1') return;

        var form = postalInput.form || postalInput.closest('form') || document;
        var names = deriveNames(postalInput.name);
        if (!names) return;

        var cityInput = fieldByName(form, names.city);
        if (!cityInput) return;

        var countryInput = fieldByName(form, names.country);
        if (countryInput) {
            countryInput = buildCountrySelect(countryInput);
        }

        postalInput.dataset.ufscAddressEnhanced = '1';
        postalInput.setAttribute('autocomplete', 'postal-code');
        cityInput.setAttribute('autocomplete', 'address-level2');

        function applyCountryMode() {
            if (!countryInput || isFrance(countryInput.value)) {
                postalInput.setAttribute('inputmode', 'numeric');
                postalInput.setAttribute('pattern', '\\d{5}');
                postalInput.setAttribute('maxlength', '5');
            } else {
                postalInput.removeAttribute('pattern');
                postalInput.removeAttribute('maxlength');
                postalInput.setAttribute('inputmode', 'text');
                renderCities(cityInput, []);
            }
        }

        if (countryInput) {
            countryInput.setAttribute('autocomplete', 'country-name');
        }
        applyCountryMode();

        function schedule() {
            if (timers.has(postalInput)) clearTimeout(timers.get(postalInput));
            var timer = window.setTimeout(function () {
                lookupPostalCode(postalInput, cityInput, countryInput);
            }, Number(cfg.lookupDelay || 350));
            timers.set(postalInput, timer);
        }

        postalInput.addEventListener('input', schedule);
        postalInput.addEventListener('change', schedule);
        if (countryInput) countryInput.addEventListener('change', function () {
            applyCountryMode();
            schedule();
        });
    }

    function init(root) {
        root = root || document;

        var countryFields = root.querySelectorAll(
            'input[name="pays"], input[name$="_pays_naissance"], input[name$="[pays]"], input[name$="[pays_naissance]"]'
        );
        Array.prototype.forEach.call(countryFields, enhanceCountryField);

        var fields = root.querySelectorAll(
            'input[name="code_postal"], input[name$="_code_postal"], input[name^="code_postal_"], input[name$="[code_postal]"]'
        );
        Array.prototype.forEach.call(fields, enhancePostal);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }

    if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node && node.nodeType === 1) init(node);
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
}());
