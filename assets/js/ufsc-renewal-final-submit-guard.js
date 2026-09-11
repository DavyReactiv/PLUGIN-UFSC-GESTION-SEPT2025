/* Final renewal submit guard.
 *
 * This listener lives on document capture so it runs before form-level legacy
 * handlers. It only owns the final add_to_cart click on the renewal assistant.
 * Server-side handlers remain authoritative for nonce, club, season, profile,
 * quota, product and WooCommerce validation.
 */
(function () {
  'use strict';

  function formFromButton(button) {
    return button && button.form && button.form.id === 'ufsc-renewal-assistant-form' ? button.form : null;
  }

  function stepNumber(form) {
    var step = Number(form.getAttribute('data-current-step') || form.getAttribute('data-initial-step') || 1);
    return step === 3 ? 3 : step;
  }

  function selectedCount(form) {
    return form.querySelectorAll('.ufsc-renewal-checkbox:checked').length;
  }

  function canSubmit(form) {
    if (!form || stepNumber(form) !== 3 || selectedCount(form) < 1) return false;
    var selected = form.querySelectorAll('.ufsc-renewal-checkbox:checked');
    for (var i = 0; i < selected.length; i++) {
      var id = String(selected[i].value || '').replace(/"/g, '');
      var row = form.querySelector('.ufsc-renewal-source-row[data-source-id="' + id + '"]');
      if (!row || row.getAttribute('data-blocked') === '1' || row.getAttribute('data-complete') !== '1') return false;
    }
    return true;
  }

  function hidden(form, name, marker) {
    var input = form.querySelector('input[' + marker + '="1"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.setAttribute(marker, '1');
      form.appendChild(input);
    }
    return input;
  }

  function status(form, message, kind) {
    var box = form.querySelector('[data-ufsc-final-submit-status="1"]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-ufsc-final-submit-status', '1');
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      var actions = form.querySelector('.ufsc-renewal-actions');
      if (actions && actions.parentNode) actions.parentNode.insertBefore(box, actions);
      else form.appendChild(box);
    }
    box.className = 'ufsc-message ' + (kind === 'error' ? 'ufsc-error' : 'ufsc-info');
    box.textContent = message;
  }

  document.addEventListener('click', function (event) {
    var target = event.target && event.target.closest ? event.target.closest('button[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"],input[type="submit"][name="ufsc_renew_intent"][value="add_to_cart"]') : null;
    if (!target) return;

    var form = formFromButton(target);
    if (!form || stepNumber(form) !== 3) return;

    /* Own the final click before legacy handlers can cancel or mutate it. */
    event.preventDefault();
    event.stopImmediatePropagation();

    if (form.getAttribute('data-ufsc-direct-final-submit') === '1') return;

    if (!canSubmit(form)) {
      status(form, 'Impossible de finaliser : un dossier sélectionné est incomplet ou bloqué. Revenez à l’étape de vérification.', 'error');
      return;
    }

    form.setAttribute('data-ufsc-direct-final-submit', '1');
    hidden(form, 'ufsc_renew_intent', 'data-ufsc-direct-final-intent').value = 'add_to_cart';
    hidden(form, 'ufsc_renew_intent_fallback', 'data-ufsc-direct-final-fallback').value = 'add_to_cart';
    hidden(form, 'ufsc_client_submit_trace', 'data-ufsc-direct-final-trace').value = 'direct_capture_submit';

    target.disabled = true;
    target.setAttribute('aria-disabled', 'true');
    status(form, 'Traitement du renouvellement en cours…', 'info');

    /* Native submit bypasses every legacy JS submit/click handler. */
    form.noValidate = true;
    HTMLFormElement.prototype.submit.call(form);
  }, true);
}());
