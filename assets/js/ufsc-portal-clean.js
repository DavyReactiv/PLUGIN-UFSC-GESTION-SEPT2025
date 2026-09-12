(function () {
  'use strict';

  function txt(el) {
    return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim();
  }

  function normalizeClubNavigation() {
    var navs = Array.prototype.slice.call(document.querySelectorAll('.ufsc-club-account__nav'));
    var seen = {};
    navs.forEach(function (nav) {
      var signature = Array.prototype.map.call(nav.querySelectorAll('a'), function (a) {
        return txt(a) + '|' + (a.getAttribute('href') || '');
      }).join('||');
      if (!signature) return;
      if (seen[signature]) {
        if (!nav.hidden) nav.hidden = true;
        if (nav.getAttribute('aria-hidden') !== 'true') nav.setAttribute('aria-hidden', 'true');
      } else {
        seen[signature] = true;
      }
    });
  }

  function simplifyLogo() {
    document.querySelectorAll('.ufsc-logo-editor').forEach(function (editor) {
      var primary = editor.querySelector('label.ufsc-btn[for="ufsc-club-logo-file"], .ufsc-logo-editor__upload label.ufsc-btn');
      if (primary && txt(primary) !== 'Modifier le logo') primary.textContent = 'Modifier le logo';
      editor.querySelectorAll('.ufsc-logo-editor__remove, .ufsc-btn-danger').forEach(function (el) {
        if (!el.hidden) el.hidden = true;
        if (el.getAttribute('aria-hidden') !== 'true') el.setAttribute('aria-hidden', 'true');
      });
    });
  }

  function normalizeKpis() {
    var cards = Array.prototype.slice.call(document.querySelectorAll('.ufsc-kpi-tile'));
    var validated = cards.find(function (card) {
      var label = card.querySelector('.ufsc-kpi-tile-label');
      return label && /licences validées/i.test(txt(label));
    });
    if (!validated) return;
    var validatedValue = validated.querySelector('.ufsc-kpi-tile-value');
    var seasonCard = cards.find(function (card) {
      var label = card.querySelector('.ufsc-kpi-tile-label');
      return label && /^licences\s+20\d{2}-20\d{2}$/i.test(txt(label));
    });
    if (!seasonCard || !validatedValue) return;
    var seasonLabel = seasonCard.querySelector('.ufsc-kpi-tile-label');
    var seasonValue = seasonCard.querySelector('.ufsc-kpi-tile-value');
    var season = txt(seasonLabel).replace(/^licences\s+/i, '');
    var activeLabel = 'Licences actives ' + season;
    if (txt(seasonLabel) !== activeLabel) seasonLabel.textContent = activeLabel;
    if (txt(seasonValue) !== txt(validatedValue)) seasonValue.textContent = txt(validatedValue);
    seasonCard.setAttribute('aria-label', activeLabel + ' — ' + txt(validatedValue));
    if (!validated.hidden) validated.hidden = true;
    if (validated.getAttribute('aria-hidden') !== 'true') validated.setAttribute('aria-hidden', 'true');
  }

  function normalizeActionTargets() {
    document.querySelectorAll('a.ufsc-btn, a.ufsc-action').forEach(function (link) {
      var label = txt(link);
      var href = link.getAttribute('href') || '';
      if (/ajouter une licence/i.test(label) && href.indexOf('#') === -1) link.href = href + '#ufsc-section-add_licence';
      if (/renouveler des licences|renouveler/i.test(label) && href.indexOf('licences-renouvellement') !== -1 && href.indexOf('#') === -1) link.href = href + '#ufsc-renouvellement';
      if (/consulter les documents/i.test(label) && href.indexOf('#') === -1) link.href = href + '#ufsc-club-documents';
      if (/mettre à jour le club|mon club/i.test(label) && href.indexOf('#') === -1 && /compte-club/.test(href)) link.href = href + '#ufsc-club-information';
    });
  }

  function isAccountAnchor(id) {
    return ['ufsc-club-information', 'ufsc-club-officers', 'ufsc-club-documents'].indexOf(id) !== -1;
  }

  function alignAccountAnchor() {
    if (!window.location.hash) return;
    var id = decodeURIComponent(window.location.hash.slice(1));
    if (!isAccountAnchor(id)) return;
    var target = document.getElementById(id);
    if (!target) return;
    var align = function () {
      if (!document.documentElement.contains(target)) return;
      target.scrollIntoView({ block: 'start', behavior: 'auto' });
      if (target.setAttribute) target.setAttribute('tabindex', '-1');
    };
    [0, 80, 240, 600].forEach(function (delay) { window.setTimeout(align, delay); });
  }

  function formatBirthDate(value) {
    var m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : String(value || '');
  }

  function enhanceLicenceListMetadata() {
    var metaMap = window.ufscLicenceUx && window.ufscLicenceUx.licenceMeta ? window.ufscLicenceUx.licenceMeta : {};
    document.querySelectorAll('.ufsc-licence-table--current tbody tr').forEach(function (row) {
      var identity = row.querySelector('td[data-label="Identité"]');
      if (!identity || identity.querySelector('.ufsc-licence-person-meta')) return;
      var action = row.querySelector('a[href*="view_licence="]');
      if (!action) return;
      var match = (action.getAttribute('href') || '').match(/[?&]view_licence=(\d+)/);
      if (!match) return;
      var meta = metaMap[match[1]] || metaMap[Number(match[1])];
      if (!meta) return;
      var parts = [];
      if (meta.birthDate) parts.push('Né(e) le ' + formatBirthDate(meta.birthDate));
      if (meta.ageCategory) parts.push(meta.ageCategory);
      if (meta.practice) parts.push(meta.practice);
      if (meta.level) parts.push(String(meta.level).replace(/_/g, ' '));
      if (!parts.length) return;
      var info = document.createElement('span');
      info.className = 'ufsc-licence-person-meta';
      info.textContent = parts.join(' · ');
      identity.appendChild(info);
    });
  }

  function ageFromBirth(value) {
    if (!value) return null;
    var date = new Date(value + 'T00:00:00');
    if (isNaN(date.getTime())) return null;
    var now = new Date();
    var age = now.getFullYear() - date.getFullYear();
    if (now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate())) age--;
    return age;
  }

  function enforceMinorHonorability() {
    var directBirth = document.querySelector('#date_naissance, .ufsc-licence-form [name="date_naissance"]');
    var directHonorability = document.getElementById('ufsc-honorability');
    if (directBirth && directHonorability) {
      var directMinor = ageFromBirth(directBirth.value) !== null && ageFromBirth(directBirth.value) < 18;
      if (directMinor) {
        directHonorability.hidden = true;
        directHonorability.querySelectorAll(':scope input, :scope select, :scope textarea, :scope button').forEach(function (input) { input.disabled = true; });
      }
    }

    document.querySelectorAll('input[name*="renewal_profiles"][name$="[date_naissance]"]').forEach(function (birth) {
      var age = ageFromBirth(birth.value);
      if (age === null || age >= 18) return;
      var scope = birth.closest('.ufsc-renewal-profile-panel, .ufsc-renewal-profile, tr');
      if (!scope) return;
      scope.querySelectorAll('input[name$="[honorability_confirmed]"]').forEach(function (checkbox) {
        checkbox.checked = false;
        checkbox.disabled = true;
        var container = checkbox.closest('label, fieldset, .ufsc-field');
        if (container) container.hidden = true;
      });
    });
  }

  function bindMinorHonorability() {
    document.addEventListener('input', function (event) {
      if (event.target && (event.target.id === 'date_naissance' || /\[date_naissance\]$/.test(event.target.name || ''))) enforceMinorHonorability();
    });
    document.addEventListener('change', function (event) {
      if (event.target && (event.target.id === 'date_naissance' || /\[date_naissance\]$/.test(event.target.name || ''))) enforceMinorHonorability();
    });
  }

  function quotaIncludedAvailable() {
    var banner = document.querySelector('.ufsc-journey-renewal-quota');
    if (!banner) return false;
    var content = txt(banner);
    var m = content.match(/(\d+)\s+restante\(s\)|(?:—|-)\s*(\d+)\s+restante/i);
    if (m) return Number(m[1] || m[2] || 0) > 0;
    return /renouvellements inclus disponibles/i.test(content);
  }

  function normalizeRenewalReview() {
    var form = document.querySelector('.ufsc-renewal-wizard');
    if (!form || !quotaIncludedAvailable()) return;
    var reviewTitle = form.querySelector('[data-ufsc-review-title]');
    var reviewStatus = form.querySelector('[data-ufsc-review-status]');
    var finalButton = form.querySelector('button[name="ufsc_renew_intent"][value="add_to_cart"]');
    var readiness = form.querySelector('#ufsc-cart-readiness');
    if (reviewTitle && txt(reviewTitle) !== 'Dossiers prêts pour validation') reviewTitle.textContent = 'Dossiers prêts pour validation';
    if (reviewStatus && /panier|quantité/i.test(txt(reviewStatus))) {
      var count = form.querySelectorAll('.ufsc-renewal-checkbox:checked').length;
      reviewStatus.textContent = count + ' dossier(s) sélectionné(s). Le quota inclus sera utilisé en priorité.';
    }
    if (finalButton) {
      if (txt(finalButton) !== 'Envoyer pour validation — inclus dans votre affiliation') finalButton.textContent = 'Envoyer pour validation — inclus dans votre affiliation';
      if (finalButton.disabled) finalButton.disabled = false;
      if (finalButton.getAttribute('aria-disabled') !== 'false') finalButton.setAttribute('aria-disabled', 'false');
      if (finalButton.getAttribute('data-ufsc-product-ready') !== '1') finalButton.setAttribute('data-ufsc-product-ready', '1');
    }
    if (readiness && txt(readiness) !== 'Aucun paiement n’est nécessaire tant que votre quota inclus n’est pas atteint.') readiness.textContent = 'Aucun paiement n’est nécessaire tant que votre quota inclus n’est pas atteint.';
    form.querySelectorAll('.ufsc-message.ufsc-warning').forEach(function (warning) {
      if (/produit licence ufsc|woocommerce|panier/i.test(txt(warning)) && !warning.hidden) warning.hidden = true;
    });
    form.querySelectorAll('[data-ufsc-step-indicator="3"]').forEach(function (step) {
      if (!/^3\s+finaliser$/i.test(txt(step))) {
        var strong = step.querySelector('strong'); step.textContent = ''; if (strong) step.appendChild(strong); step.appendChild(document.createTextNode(' Finaliser'));
      }
    });
  }

  function watchRenewal() {
    var form = document.querySelector('.ufsc-renewal-wizard');
    if (!form || !window.MutationObserver) return;
    var scheduled = false;
    var observer = new MutationObserver(function () {
      if (scheduled) return; scheduled = true;
      window.requestAnimationFrame(function () { scheduled = false; normalizeRenewalReview(); enforceMinorHonorability(); });
    });
    observer.observe(form, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'disabled', 'aria-disabled'] });
  }

  function init() {
    normalizeClubNavigation(); simplifyLogo(); normalizeKpis(); normalizeActionTargets();
    enhanceLicenceListMetadata(); enforceMinorHonorability(); bindMinorHonorability();
    normalizeRenewalReview(); watchRenewal(); alignAccountAnchor();
  }

  window.addEventListener('hashchange', alignAccountAnchor);
  window.addEventListener('load', function () { alignAccountAnchor(); enhanceLicenceListMetadata(); enforceMinorHonorability(); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
