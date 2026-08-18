(function () {
  'use strict';

  function text(el) { return (el && el.textContent ? el.textContent : '').trim(); }

  function normalizeMainLicenceLinks() {
    var cfg = window.ufscStructuralPortal || {};
    if (!cfg.licencesUrl) return;
    document.querySelectorAll('a[href*="#ufsc-club-licences"], a[href$="#ufsc-current-licences"]').forEach(function (link) {
      if (/mes licences/i.test(text(link)) || /licences/i.test(text(link))) link.href = cfg.licencesUrl;
    });
  }

  function preserveArchiveForms() {
    document.querySelectorAll('form.ufsc-archive-filter-form').forEach(function (form) {
      if (!form.querySelector('input[name="ufsc_section"]')) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'ufsc_section'; hidden.value = 'licences-archives';
        form.appendChild(hidden);
      }
    });
  }

  function completeStatusFilter() {
    document.querySelectorAll('select[name="ufsc_status"]').forEach(function (select) {
      var wanted = [
        ['brouillon', 'Brouillon'],
        ['en_attente', 'En attente de validation'],
        ['valide', 'Validée'],
        ['a_regler', 'À régler'],
        ['refuse', 'Refusée']
      ];
      wanted.forEach(function (item) {
        if (!select.querySelector('option[value="' + item[0] + '"]')) {
          var option = document.createElement('option'); option.value = item[0]; option.textContent = item[1]; select.appendChild(option);
        }
      });
      var bad = select.querySelector('option[value="validee"]');
      if (bad) bad.value = 'valide';
    });
  }

  function dedupeClubNavigation() {
    var navs = Array.prototype.slice.call(document.querySelectorAll('.ufsc-club-account__nav'));
    if (navs.length < 2) return;
    var seen = {};
    navs.forEach(function (nav) {
      var signature = Array.prototype.map.call(nav.querySelectorAll('a'), function (a) { return text(a) + '|' + a.getAttribute('href'); }).join('||');
      if (!signature) return;
      if (seen[signature]) {
        nav.setAttribute('hidden', 'hidden');
        nav.setAttribute('aria-hidden', 'true');
      } else {
        seen[signature] = true;
      }
    });
  }

  function simplifyLogoEditor() {
    document.querySelectorAll('.ufsc-logo-editor').forEach(function (editor) {
      var primary = editor.querySelector('label.ufsc-btn[for="ufsc-club-logo-file"]');
      if (primary) primary.textContent = 'Modifier le logo';
      var remove = editor.querySelector('.ufsc-logo-editor__remove');
      if (remove) remove.hidden = true;
    });
  }

  function routeArchiveRenewalsThroughAssistant() {
    var cfg = window.ufscStructuralPortal || {};
    if (!cfg.renewalUrl) return;
    document.querySelectorAll('form.ufsc-inline-renew-form').forEach(function (form) {
      var source = form.querySelector('input[name="ufsc_renew_from_licence_id"]');
      var season = form.querySelector('input[name="ufsc_target_season"]');
      if (!source || !source.value) return;
      var url;
      try {
        url = new URL(cfg.renewalUrl, window.location.origin);
        url.searchParams.set('ufsc_section', 'licences-renouvellement');
        url.searchParams.set('renew_source_id', source.value);
        if (season && season.value) url.searchParams.set('target_season', season.value);
        url.hash = 'ufsc-renouvellement';
      } catch (e) { return; }
      var a = document.createElement('a');
      a.className = 'ufsc-action'; a.href = url.toString(); a.textContent = season && season.value ? 'Renouveler pour ' + season.value : 'Renouveler';
      form.replaceWith(a);
    });
  }

  function hideAdminLikePaymentOnDraftsInFront() {
    document.querySelectorAll('.ufsc-licence-table tr').forEach(function (row) {
      if (!/brouillon/i.test(text(row))) return;
      row.querySelectorAll('a,button').forEach(function (action) {
        if (/paiement|ajouter au panier/i.test(text(action))) action.hidden = true;
      });
    });
  }

  function init() {
    normalizeMainLicenceLinks();
    preserveArchiveForms();
    completeStatusFilter();
    dedupeClubNavigation();
    simplifyLogoEditor();
    routeArchiveRenewalsThroughAssistant();
    hideAdminLikePaymentOnDraftsInFront();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
