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

  /*
   * Browsers normally include the clicked submit button name/value in POST, but
   * the renewal page still has legacy listeners and DOM promotion around the
   * same form. On DEV we observed real requests containing the selected licence
   * but no ufsc_renew_intent. Keep a separate fallback field so the server can
   * restore the exact action without relying on event.submitter support.
   */
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
    selected.forEach(function (id) { var row = source(f, id); if (ready(row)) r++; if (blocked(row)) b++; });
    var incomplete = Math.max(0, selected.length - r - b), out = f.querySelector('[data-ufsc-selection-count]');
    if (out) out.textContent = selected.length ? selected.length + ' sélectionnée(s) · ' + r + ' prête(s) · ' + incomplete + ' à compléter' + (b ? ' · ' + b + ' bloquée(s)' : '') : 'Aucune licence sélectionnée.';
    return {selected:selected.length, ready:r, incomplete:incomplete, blocked:b};
  }

  function finalReview(f, w, c) {
    var step = stepNumber(f);
    if (step !== 3) return;
    var selected = ids(f), remaining = quota(w), included = Math.min(selected.length, remaining), paid = Math.max(0, selected.length - included);
    var button = f.querySelector('button[name="ufsc_renew_intent"][value="add_to_cart"]');
    var paidReady = paid === 0 || productReady(w, button), canSubmit = selected.length > 0 && c.ready === selected.length && c.blocked === 0 && paidReady;
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
    if (info) info.textContent = c.ready !== selected.length ? 'Complétez tous les dossiers sélectionnés avant de confirmer.' : (paid ? (paidReady ? 'Le quota est utilisé d’abord. Seules ' + paid + ' licence(s) seront ajoutées au panier.' : 'Le produit Licence UFSC est indisponible pour la partie payante.') : 'Aucun paiement : ' + included + ' place(s) du quota inclus seront utilisées.');
  }

  function sync() {
    var f = form(), w = wizard(f); if (!f || !w) return;
    ensurePanels(f);
    var step = stepNumber(f);
    w.setAttribute('data-ufsc-current-step', String(step));
    var selected = ids(f);

    // A previous final click must never leak into a later step-1/2 Enter submit.
    if (step !== 3) rememberIntent(f, '');

    Array.prototype.slice.call(w.querySelectorAll('.ufsc-renewal-filters,.ufsc-renewal-list-tools,.ufsc-renewal-pagination')).forEach(function (el) { el.style.display = step === 1 ? '' : 'none'; });
    var wrap = f.querySelector('.ufsc-front-table-scroll'); if (wrap) wrap.style.display = step === 1 ? '' : 'none';
    Array.prototype.slice.call(f.querySelectorAll('.ufsc-renewal-profile-panel')).forEach(function (panel) {
      var show = step === 2 && selected.indexOf(String(panel.getAttribute('data-profile-id') || '')) !== -1;
      panel.hidden = !show; panel.style.display = show ? '' : 'none'; panel.classList.toggle('ufsc-is-hidden', !show);
      if (show) { var d = panel.querySelector('details'); if (d) { d.open = true; d.setAttribute('aria-expanded','true'); } }
    });
    Array.prototype.slice.call(f.querySelectorAll('[data-ufsc-renew-one]')).forEach(function (el) { el.textContent = 'Vérifier ce dossier'; });
    var c = counts(f); note(w, step, c.selected); finalReview(f, w, c);
  }

  function init() {
    var f = form(); if (!f || f.getAttribute('data-ufsc-renewal-overlay') === '1') return;
    f.setAttribute('data-ufsc-renewal-overlay','1'); ensurePanels(f); rememberIntent(f, ''); sync();

    // Capture the clicked server action before older listeners can alter/disable
    // the submit control. The field uses a distinct name to avoid duplicate-key
    // ambiguity with the real submit button.
    f.addEventListener('click', function (e) {
      var submitter = e.target && e.target.closest ? e.target.closest('button[type="submit"][name="ufsc_renew_intent"],input[type="submit"][name="ufsc_renew_intent"]') : null;
      if (submitter) rememberIntent(f, submitter.value || '');
    }, true);

    // Keyboard submits and older browsers may expose no submitter at all. On the
    // final review only, an enabled final button makes the intent unambiguous.
    f.addEventListener('submit', function (e) {
      var submitter = e.submitter || null;
      var intent = submitter && submitter.name === 'ufsc_renew_intent' ? String(submitter.value || '') : '';
      if (!intent && stepNumber(f) === 3) {
        var finalButton = f.querySelector('button[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"]');
        if (finalButton && !finalButton.disabled) intent = 'add_to_cart';
      }
      if (intent) rememberIntent(f, intent);
    }, true);

    f.addEventListener('change', function () { window.setTimeout(sync,0); });
    f.addEventListener('input', function () { window.setTimeout(sync,0); });
    f.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('[data-ufsc-next-step],[data-ufsc-renew-one],[data-ufsc-select-all],[data-ufsc-select-none]')) window.setTimeout(sync,0); });
    if (window.MutationObserver) new MutationObserver(function () { window.setTimeout(sync,0); }).observe(f,{attributes:true,attributeFilter:['data-current-step']});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
