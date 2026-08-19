(function () {
  'use strict';

  function txt(el) {
    return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim();
  }

  function matchesText(el, re) {
    return re.test(txt(el));
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
        nav.hidden = true;
        nav.setAttribute('aria-hidden', 'true');
      } else {
        seen[signature] = true;
      }
    });
  }

  function simplifyLogo() {
    document.querySelectorAll('.ufsc-logo-editor').forEach(function (editor) {
      var primary = editor.querySelector('label.ufsc-btn[for="ufsc-club-logo-file"], .ufsc-logo-editor__upload label.ufsc-btn');
      if (primary) primary.textContent = 'Modifier le logo';
      editor.querySelectorAll('.ufsc-logo-editor__remove, .ufsc-btn-danger').forEach(function (el) {
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
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
    seasonLabel.textContent = 'Licences actives ' + season;
    seasonValue.textContent = txt(validatedValue);
    seasonCard.setAttribute('aria-label', 'Licences actives ' + season + ' — ' + txt(validatedValue));

    // Avoid presenting the same business count twice.
    validated.hidden = true;
    validated.setAttribute('aria-hidden', 'true');
  }

  function normalizeActionTargets() {
    document.querySelectorAll('a.ufsc-btn, a.ufsc-action').forEach(function (link) {
      var label = txt(link);
      var href = link.getAttribute('href') || '';
      if (/ajouter une licence/i.test(label) && href.indexOf('#') === -1) {
        link.href = href + '#ufsc-section-add_licence';
      }
      if (/renouveler des licences|renouveler/i.test(label) && href.indexOf('licences-renouvellement') !== -1 && href.indexOf('#') === -1) {
        link.href = href + '#ufsc-renouvellement';
      }
      if (/consulter les documents/i.test(label) && href.indexOf('#') === -1) {
        link.href = href + '#ufsc-club-documents';
      }
      if (/mettre à jour le club|mon club/i.test(label) && href.indexOf('#') === -1 && /compte-club/.test(href)) {
        link.href = href + '#ufsc-club-information';
      }
    });
  }

  function scrollToUsefulTarget() {
    if (!window.location.hash) return;
    var id = decodeURIComponent(window.location.hash.slice(1));
    var target = document.getElementById(id);
    if (!target) return;
    window.setTimeout(function () {
      target.scrollIntoView({ block: 'start', behavior: 'auto' });
    }, 80);
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

    if (reviewTitle && /panier|vérification finale/i.test(txt(reviewTitle))) {
      reviewTitle.textContent = 'Dossiers prêts pour validation';
    }
    if (reviewStatus && /panier|quantité/i.test(txt(reviewStatus))) {
      var count = form.querySelectorAll('.ufsc-renewal-checkbox:checked').length;
      reviewStatus.textContent = count + ' dossier(s) sélectionné(s). Le quota inclus sera utilisé en priorité.';
    }
    if (finalButton) {
      finalButton.textContent = 'Envoyer pour validation — inclus dans votre affiliation';
      finalButton.disabled = false;
      finalButton.setAttribute('aria-disabled', 'false');
      finalButton.setAttribute('data-ufsc-product-ready', '1');
    }
    if (readiness) {
      readiness.textContent = 'Aucun paiement n’est nécessaire tant que votre quota inclus n’est pas atteint.';
    }

    form.querySelectorAll('.ufsc-message.ufsc-warning').forEach(function (warning) {
      if (/produit licence ufsc|woocommerce|panier/i.test(txt(warning))) {
        warning.hidden = true;
      }
    });

    form.querySelectorAll('[data-ufsc-step-indicator="3"]').forEach(function (step) {
      var strong = step.querySelector('strong');
      step.textContent = '';
      if (strong) step.appendChild(strong);
      step.appendChild(document.createTextNode(' Finaliser'));
    });
  }

  function watchRenewal() {
    var form = document.querySelector('.ufsc-renewal-wizard');
    if (!form || !window.MutationObserver) return;
    var scheduled = false;
    var observer = new MutationObserver(function () {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(function () {
        scheduled = false;
        normalizeRenewalReview();
      });
    });
    observer.observe(form, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'disabled', 'aria-disabled'] });
  }

  function init() {
    normalizeClubNavigation();
    simplifyLogo();
    normalizeKpis();
    normalizeActionTargets();
    normalizeRenewalReview();
    watchRenewal();
    scrollToUsefulTarget();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
