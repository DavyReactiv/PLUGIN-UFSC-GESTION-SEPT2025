(function () {
  'use strict';

  function config() { return window.ufscLicenceUx || {}; }
  function currentSection() { var p = new URLSearchParams(window.location.search || ''); return p.get('ufsc_section') || ''; }
  function currentSeason() { var p = new URLSearchParams(window.location.search || ''); return p.get('ufsc_season') || config().season || ''; }

  function redirectLegacyLicenceAnchor() {
    if (!config().current || currentSection()) return false;
    var hash = window.location.hash || '';
    if (hash === '#ufsc-club-licences' || hash === '#ufsc-current-licences') {
      window.location.replace(config().current);
      return true;
    }
    return false;
  }

  function link(label, href, key, active) {
    var a = document.createElement('a');
    a.className = 'ufsc-licence-shortcut' + (active === key ? ' is-active' : '');
    a.href = href || '#';
    a.textContent = label;
    a.setAttribute('data-ufsc-shortcut', key);
    return a;
  }

  function activeShortcut() {
    var section = currentSection(), season = currentSeason();
    if (section === 'licences-renouvellement') return 'renewal';
    if ((window.location.hash || '').indexOf('ufsc-section-add_licence') !== -1) return 'add';
    if (section === 'club-licences' && config().previousSeason && season === config().previousSeason) return 'previous';
    if (section === 'club-licences' || (window.location.hash || '').indexOf('ufsc-club-licences') !== -1) return 'current';
    return '';
  }

  function buildShortcuts() {
    if (!config().current) return null;
    var nav = document.createElement('nav'), active = activeShortcut();
    nav.className = 'ufsc-licence-shortcuts';
    nav.setAttribute('aria-label', 'Raccourcis licences UFSC');
    nav.appendChild(link('Mes licences ' + (config().season || ''), config().current, 'current', active));
    nav.appendChild(link('Renouveler des licences', config().renewal, 'renewal', active));
    nav.appendChild(link('Ajouter une licence', config().add, 'add', active));
    nav.appendChild(link('Saisons précédentes', config().previous, 'previous', active));
    nav.appendChild(link('Tableau de bord', config().dashboard, 'dashboard', active));
    return nav;
  }

  function licenceContainer() {
    var selectors = ['.ufsc-renewal-wizard','#ufsc-club-licences','#ufsc-current-licences','.ufsc-licence-detail','.ufsc-licence-view','#ufsc-section-add_licence','.ufsc-club-licences'];
    for (var i = 0; i < selectors.length; i++) { var node = document.querySelector(selectors[i]); if (node) return node; }
    return null;
  }

  function insertShortcuts() {
    if (document.querySelector('.ufsc-licence-shortcuts')) return;
    var target = licenceContainer(), nav = buildShortcuts();
    if (target && nav) target.insertBefore(nav, target.firstChild);
  }

  function repairMesLicencesLinks() {
    if (!config().current) return;
    document.querySelectorAll('a').forEach(function (a) {
      var text = (a.textContent || '').trim().toLowerCase(), href = a.getAttribute('href') || '';
      var isLicenceLink = text === 'mes licences ufsc' || text === 'mes licences' || href.indexOf('#ufsc-club-licences') !== -1 || href.indexOf('#ufsc-current-licences') !== -1;
      if (!isLicenceLink || href.indexOf('view_licence=') !== -1 || href.indexOf('edit_licence=') !== -1) return;
      a.setAttribute('href', config().current);
    });
  }

  function applyTableLabels() {
    document.querySelectorAll('.ufsc-licence-table, .ufsc-renewal-table').forEach(function (table) {
      var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return (th.textContent || '').trim(); });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.prototype.forEach.call(row.children, function (cell, index) { if (!cell.getAttribute('data-label') && headers[index]) cell.setAttribute('data-label', headers[index]); });
      });
    });
  }

  function escapeHtml(value) {
    return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  function showNotice(text, state) {
    if (!text) return;
    var target = licenceContainer() || document.querySelector('main') || document.body;
    var existing = document.querySelector('.ufsc-global-notice[data-runtime="1"]');
    if (existing) existing.remove();
    var notice = document.createElement('div');
    notice.className = 'ufsc-global-notice is-' + (state || 'info');
    notice.setAttribute('data-runtime','1');
    notice.setAttribute('role', state === 'error' ? 'alert' : 'status');
    notice.setAttribute('aria-live', state === 'error' ? 'assertive' : 'polite');
    var icon = state === 'success' ? '✓' : state === 'error' ? '×' : state === 'pending' ? '⏳' : 'i';
    notice.innerHTML = '<span class="ufsc-global-notice__icon" aria-hidden="true">' + icon + '</span><strong>' + escapeHtml(text) + '</strong>';
    target.insertBefore(notice, target.firstChild);
    notice.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function mapMessage(code) {
    var messages = {licence_included:'Licence envoyée pour validation. Aucun paiement n’est nécessaire.',renewal_included:'Renouvellement envoyé pour validation. Le quota inclus a été utilisé.',licence_saved:'Licence enregistrée.',club_saved:'Informations du club enregistrées.',affiliation_added:'Affiliation ajoutée au panier.',success:'Action effectuée avec succès.'};
    return messages[code] || '';
  }

  function insertQueryNotice() {
    var params = new URLSearchParams(window.location.search || ''), error = params.get('ufsc_error'), message = params.get('ufsc_message');
    if (error) showNotice(error, 'error');
    else if (message) showNotice(mapMessage(message) || message.replace(/[_-]+/g,' '), message.indexOf('pending') !== -1 || message.indexOf('waiting') !== -1 ? 'pending' : 'success');
  }

  function normalizeExistingMessages() {
    document.querySelectorAll('.ufsc-message').forEach(function (el) {
      el.classList.add('ufsc-global-message');
      if (el.classList.contains('ufsc-success')) el.setAttribute('data-state','success');
      else if (el.classList.contains('ufsc-error')) el.setAttribute('data-state','error');
      else if (el.classList.contains('ufsc-warning')) el.setAttribute('data-state','pending');
      else el.setAttribute('data-state','info');
    });
  }

  function bindValidationFeedback() {
    document.addEventListener('invalid', function (event) {
      var form = event.target && event.target.form;
      if (!form || (form.id !== 'ufsc-renewal-assistant-form' && !form.classList.contains('ufsc-licence-form'))) return;
      showNotice('Le dossier ne peut pas être finalisé : vérifiez les champs obligatoires signalés ci-dessous.', 'error');
    }, true);

    var renewal = document.getElementById('ufsc-renewal-assistant-form');
    if (renewal) {
      renewal.addEventListener('submit', function () {
        var selected = renewal.querySelectorAll('input[name="ufsc_renew_ids[]"]:checked');
        if (!selected.length) showNotice('Sélectionnez au moins une licence à renouveler.', 'error');
      });
    }
  }

  function init() {
    if (redirectLegacyLicenceAnchor()) return;
    repairMesLicencesLinks(); insertShortcuts(); applyTableLabels(); normalizeExistingMessages(); insertQueryNotice(); bindValidationFeedback();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
