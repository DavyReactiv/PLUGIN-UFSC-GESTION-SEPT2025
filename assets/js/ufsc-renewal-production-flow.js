/* Production renewal UX controller: keeps the legacy server contract, but makes
 * the 3-step flow deterministic and quota-first for club users. */
(function () {
  'use strict';
  window.ufscProductionRenewalUxReady = true;

  function form() { return document.getElementById('ufsc-renewal-assistant-form'); }
  function wizard(f) { return f ? (f.closest('.ufsc-renewal-wizard') || f.parentNode) : null; }
  function ids(f) { return Array.prototype.map.call(f.querySelectorAll('.ufsc-renewal-checkbox:checked'), function (b) { return String(b.value || ''); }).filter(Boolean); }
  function source(f, id) { return f.querySelector('.ufsc-renewal-source-row[data-source-id="' + String(id).replace(/"/g, '') + '"]'); }
  function ready(row) { return !!row && row.getAttribute('data-complete') === '1'; }
  function blocked(row) { return !!row && row.getAttribute('data-blocked') === '1'; }
  function esc(v) { return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

  function intentFallback(f) {
    var input = f.querySelector('input[data-ufsc-renew-intent-fallback="1"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ufsc_renew_intent_fallback';
      input.setAttribute('data-ufsc-renew-intent-fallback', '1');
      input.value = '';
      f.appendChild(input);
    }
    return input;
  }

  function rememberIntent(f, value) {
    intentFallback(f).value = value || '';
  }

  function stepNumber(f) {
    var step = Number(f.getAttribute('data-current-step') || f.getAttribute('data-initial-step') || 1);
    return step === 2 || step === 3 ? step : 1;
  }

  function ensurePanels(f) {
    var w = wizard(f), wrap = f && f.querySelector('.ufsc-front-table-scroll');
    if (!w || !wrap) return;
    var insertion = wrap;
    Array.prototype.slice.call(f.querySelectorAll('tr.ufsc-renewal-profile-row')).forEach(function (row) {
      var cell = row.querySelector('td');
      if (!cell) return;
      var panel = document.createElement('section');
      panel.className = 'ufsc-renewal-profile-row ufsc-renewal-profile-panel';
      panel.setAttribute('data-profile-id', row.getAttribute('data-profile-id') || '');
      panel.hidden = true;
      panel.style.display = 'none';
      while (cell.firstChild) panel.appendChild(cell.firstChild);
      insertion.insertAdjacentElement('afterend', panel);
      insertion = panel;
      row.remove();
    });
    Array.prototype.slice.call(f.querySelectorAll('.ufsc-renewal-profile-panel')).forEach(function (panel) {
      panel.classList.add('ufsc-renewal-profile-row');
      if (!panel.hasAttribute('hidden')) panel.hidden = true;
    });
  }

  function quota(w) {
    var banner = w.querySelector('.ufsc-journey-renewal-quota');
    var text = banner ? (banner.textContent || '').replace(/\s+/g, ' ') : '';
    var rest = text.match(/(\d+)\s*restante?/i), usage = text.match(/(\d+)\s*\/\s*(\d+)\s*utilis/i);
    if (rest) return Math.max(0, Number(rest[1]));
    if (usage) return Math.max(0, Number(usage[2]) - Number(usage[1]));
    return 0;
  }

  function productReady(w, button) {
    var warnings = Array.prototype.slice.call(w.querySelectorAll('.ufsc-message.ufsc-warning, .ufsc-global-message[data-state="pending"]'));
    var unavailable = warnings.some(function (el) {
      var t = (el.textContent || '').toLowerCase();
      return (t.indexOf('produit licence ufsc') !== -1 || t.indexOf('woocommerce') !== -1) && (t.indexOf('indisponible') !== -1 || t.indexOf('introuvable') !== -1 || t.indexOf('non configur') !== -1);
    });
    return !unavailable && !!button && button.getAttribute('data-ufsc-product-ready') === '1';
  }

  function profileComplete(f, id) {
    var row = source(f, id);
    if (ready(row)) return true;

    var panel = f.querySelector('.ufsc-renewal-profile-row[data-profile-id="' + String(id).replace(/"/g, '') + '"]');
    if (!panel || panel.querySelector('[aria-invalid="true"]')) return false;

    var required = Array.prototype.slice.call(panel.querySelectorAll('input[required],select[required],textarea[required]')).filter(function (el) {
      return !el.disabled;
    });
    if (!required.length) return false;

    var valid = required.every(function (el) {
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return false;
      if (typeof el.checkValidity === 'function' && !el.checkValidity()) return false;
      return String(el.value == null ? '' : el.value).trim() !== '';
    });

    if (valid && row) {
      row.setAttribute('data-complete', '1');
      row.setAttribute('data-cart-eligible', '1');
      var completeness = panel.querySelector('[data-ufsc-completeness]');
      if (completeness) {
        completeness.classList.remove('ufsc-warning');
        completeness.classList.add('ufsc-success');
        var strong = completeness.querySelector('strong');
        if (strong) strong.textContent = 'Dossier complet';
      }
    }
    return valid;
  }

  function note(w, step, selected) {
    var n = w.querySelector('[data-ufsc-renewal-note]');
    if (!n) {
      n = document.createElement('div');
      n.className = 'ufsc-message ufsc-info ufsc-renewal-step-note';
      n.setAttribute('data-ufsc-renewal-note', '1');
      var steps = w.querySelector('.ufsc-renewal-steps');
      if (steps) steps.insertAdjacentElement('afterend', n); else w.insertBefore(n, w.firstChild);
    }
    if (step === 2) n.innerHTML = '<strong>2. Vérifier les informations</strong><br>Seuls les ' + selected + ' dossier(s) sélectionné(s) sont affichés. Complétez-les puis continuez.';
    else if (step === 3) n.innerHTML = '<strong>3. Finaliser</strong><br>Vérifiez le récapitulatif. Le quota inclus est utilisé en priorité ; seul le dépassement éventuel passe au panier.';
    else n.innerHTML = '<strong>1. Sélectionner les licences</strong><br>Cochez les licences à renouveler ou cliquez sur « Vérifier ce dossier ». Rien n’est renouvelé avant votre confirmation finale.';
  }

  function counts(f) {
    var selected = ids(f), r = 0, b = 0;
    selected.forEach(function (id) {
      var row = source(f, id);
      if (profileComplete(f, id)) r++;
      if (blocked(row)) b++;
    });
    var incomplete = Math.max(0, selected.length - r - b), out = f.querySelector('[data-ufsc-selection-count]');
    if (out) out.textContent = selected.length ? selected.length + ' sélectionnée(s) · ' + r + ' prête(s) · ' + incomplete + ' à compléter' + (b ? ' · ' + b + ' bloquée(s)' : '') : 'Aucune licence sélectionnée.';
    return {selected:selected.length, ready:r, incomplete:incomplete, blocked:b};
  }

  function canFinalSubmit(f) {
    var selected = ids(f);
    if (!selected.length) return false;
    for (var i = 0; i < selected.length; i++) {
      var row = source(f, selected[i]);
      if (blocked(row) || !profileComplete(f, selected[i])) return false;
    }
    return true;
  }

  function enforceFinalButton(f) {
    if (!f || stepNumber(f) !== 3) return;
    var button = f.querySelector('button[name="ufsc_renew_intent"][value="add_to_cart"]');
    if (!button) return;
    var allowed = canFinalSubmit(f);
    if (button.disabled === allowed) button.disabled = !allowed;
    button.setAttribute('aria-disabled', allowed ? 'false' : 'true');
  }

  function finalReview(f, w, c) {
    var step = stepNumber(f);
    if (step !== 3) return;
    var selected = ids(f), remaining = quota(w), included = Math.min(selected.length, remaining), paid = Math.max(0, selected.length - included);
    var button = f.querySelector('button[name="ufsc_renew_intent"][value="add_to_cart"]');
    var productIsReady = productReady(w, button);
    var canSubmit = selected.length > 0 && c.ready === selected.length && c.blocked === 0;
    var panel = f.querySelector('[data-ufsc-step-review="3"]'), title = panel && panel.querySelector('[data-ufsc-review-title]'), status = panel && panel.querySelector('[data-ufsc-review-status]'), list = panel && panel.querySelector('ul'), info = f.querySelector('#ufsc-cart-readiness');
    if (title) title.textContent = 'Vérification finale';
    if (status) status.textContent = c.ready !== selected.length ? 'Un ou plusieurs dossiers restent incomplets.' : (paid ? included + ' renouvellement(s) inclus + ' + paid + ' payant(s).' : included + ' renouvellement(s) inclus — aucun paiement.');
    if (list) {
      list.innerHTML = '';
      selected.forEach(function (id, i) {
        var row = source(f, id), identity = row && row.querySelector('td[data-label="Identité"],td:nth-child(2)'), name = identity ? (identity.textContent || '').replace(/\s+/g,' ').trim() : 'Licence #' + id, li = document.createElement('li');
        li.innerHTML = '<strong>' + esc(name) + '</strong> — ' + esc(i < included ? 'Incluse · 0 €' : 'Payante · panier après confirmation');
        list.appendChild(li);
      });
    }
    if (button) {
      button.disabled = !canSubmit;
      button.setAttribute('aria-disabled', canSubmit ? 'false' : 'true');
      button.textContent = paid ? 'Confirmer — ' + included + ' incluse(s), ' + paid + ' payante(s)' : 'Envoyer pour validation — inclus dans votre affiliation';
    }
    if (info) {
      if (c.ready !== selected.length) {
        info.textContent = 'Complétez tous les dossiers sélectionnés avant de confirmer.';
      } else if (paid) {
        info.textContent = productIsReady
          ? 'Le quota est utilisé d’abord. Seules ' + paid + ' licence(s) seront ajoutées au panier.'
          : 'La disponibilité du produit Licence UFSC sera vérifiée par le serveur à la confirmation.';
      } else {
        info.textContent = 'Aucun paiement : ' + included + ' place(s) du quota inclus seront utilisées.';
      }
    }
  }

  function ensureCanonicalIntent(f, value, reason) {
    var input = f.querySelector('input[data-ufsc-native-final-intent="1"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ufsc_renew_intent';
      input.setAttribute('data-ufsc-native-final-intent', '1');
      f.appendChild(input);
    }
    input.value = value;

    var trace = f.querySelector('input[data-ufsc-client-submit-trace="1"]');
    if (!trace) {
      trace = document.createElement('input');
      trace.type = 'hidden';
      trace.name = 'ufsc_client_submit_trace';
      trace.setAttribute('data-ufsc-client-submit-trace', '1');
      f.appendChild(trace);
    }
    trace.value = reason || 'native_fallback';
  }

  function nativeFinalSubmit(f, reason) {
    if (!f || f.getAttribute('data-ufsc-native-final-submit') === '1') return;
    var button = f.querySelector('button[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"]');
    if (stepNumber(f) !== 3 || !button || !ids(f).length || !canFinalSubmit(f)) return;

    f.setAttribute('data-ufsc-native-final-submit', '1');
    rememberIntent(f, 'add_to_cart');
    ensureCanonicalIntent(f, 'add_to_cart', reason);

    /*
     * The server is the authority for product availability, nonce, club, season,
     * completeness, quota and WooCommerce. The final fallback deliberately uses
     * native submit so stale browser validation or legacy JS cannot swallow the
     * request without any feedback.
     */
    f.noValidate = true;
    HTMLFormElement.prototype.submit.call(f);
  }

  function sync() {
    var f = form(), w = wizard(f); if (!f || !w) return;
    ensurePanels(f);
    var step = stepNumber(f);
    w.setAttribute('data-ufsc-current-step', String(step));
    var selected = ids(f);

    if (step !== 3) rememberIntent(f, '');

    Array.prototype.slice.call(w.querySelectorAll('.ufsc-renewal-filters,.ufsc-renewal-list-tools,.ufsc-renewal-pagination')).forEach(function (el) { el.style.display = step === 1 ? '' : 'none'; });
    var wrap = f.querySelector('.ufsc-front-table-scroll'); if (wrap) wrap.style.display = step === 1 ? '' : 'none';
    Array.prototype.slice.call(f.querySelectorAll('.ufsc-renewal-profile-panel')).forEach(function (panel) {
      var show = step === 2 && selected.indexOf(String(panel.getAttribute('data-profile-id') || '')) !== -1;
      panel.hidden = !show; panel.style.display = show ? '' : 'none'; panel.classList.toggle('ufsc-is-hidden', !show);
      if (show) { var d = panel.querySelector('details'); if (d) { d.open = true; d.setAttribute('aria-expanded','true'); } }
    });
    Array.prototype.slice.call(f.querySelectorAll('[data-ufsc-renew-one]')).forEach(function (el) { el.textContent = 'Vérifier ce dossier'; });
    var c = counts(f); note(w, step, c.selected); finalReview(f, w, c); enforceFinalButton(f);
  }

  function init() {
    var f = form(); if (!f || f.getAttribute('data-ufsc-renewal-overlay') === '1') return;
    f.setAttribute('data-ufsc-renewal-overlay','1'); ensurePanels(f); rememberIntent(f, ''); sync();

    f.addEventListener('click', function (e) {
      var submitter = e.target && e.target.closest ? e.target.closest('button[type="submit"][name="ufsc_renew_intent"],input[type="submit"][name="ufsc_renew_intent"]') : null;
      if (!submitter) return;

      rememberIntent(f, submitter.value || '');
      if (submitter.value !== 'add_to_cart' || stepNumber(f) !== 3) return;

      enforceFinalButton(f);
      if (!canFinalSubmit(f)) return;

      f._ufscFinalSubmitObserved = false;
      window.setTimeout(function () {
        if (!f._ufscFinalSubmitObserved && document.documentElement.contains(f)) {
          nativeFinalSubmit(f, 'click_without_submit');
        }
      }, 0);
    }, true);

    f.addEventListener('submit', function (e) {
      var submitter = e.submitter || null;
      var intent = submitter && submitter.name === 'ufsc_renew_intent' ? String(submitter.value || '') : '';
      if (!intent && stepNumber(f) === 3) {
        var finalButton = f.querySelector('button[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"]');
        if (finalButton && canFinalSubmit(f)) intent = 'add_to_cart';
      }
      if (intent) rememberIntent(f, intent);

      if (intent === 'add_to_cart' && stepNumber(f) === 3) {
        f._ufscFinalSubmitObserved = true;
        window.setTimeout(function () {
          if (e.defaultPrevented && document.documentElement.contains(f)) {
            nativeFinalSubmit(f, 'submit_prevented');
          }
        }, 0);
      }
    }, true);

    f.addEventListener('invalid', function () {
      if (stepNumber(f) === 3 && canFinalSubmit(f)) {
        window.setTimeout(function () {
          nativeFinalSubmit(f, 'native_validation_blocked');
        }, 0);
      }
    }, true);

    f.addEventListener('change', function () { window.setTimeout(sync,0); });
    f.addEventListener('input', function () { window.setTimeout(sync,0); });
    f.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('[data-ufsc-next-step],[data-ufsc-renew-one],[data-ufsc-select-all],[data-ufsc-select-none]')) window.setTimeout(sync,0); });

    var finalButton = f.querySelector('button[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"]');
    if (window.MutationObserver && finalButton) {
      new MutationObserver(function () {
        if (stepNumber(f) === 3) enforceFinalButton(f);
      }).observe(finalButton, {attributes:true, attributeFilter:['disabled','aria-disabled','data-ufsc-product-ready']});
    }
    if (window.MutationObserver) new MutationObserver(function () { window.setTimeout(sync,0); }).observe(f,{attributes:true,attributeFilter:['data-current-step']});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
