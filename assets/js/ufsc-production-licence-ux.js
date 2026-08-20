(function () {
  'use strict';

  function config() {
    return window.ufscLicenceUx || {};
  }

  function currentSection() {
    var params = new URLSearchParams(window.location.search || '');
    return params.get('ufsc_section') || '';
  }

  function currentSeason() {
    var params = new URLSearchParams(window.location.search || '');
    return params.get('ufsc_season') || config().season || '';
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
    var section = currentSection();
    var season = currentSeason();
    if (section === 'licences-renouvellement') return 'renewal';
    if ((window.location.hash || '').indexOf('ufsc-section-add_licence') !== -1) return 'add';
    if (section === 'club-licences' && config().previousSeason && season === config().previousSeason) return 'previous';
    if (section === 'club-licences' || (window.location.hash || '').indexOf('ufsc-club-licences') !== -1) return 'current';
    return '';
  }

  function buildShortcuts() {
    if (!config().current) return null;
    var nav = document.createElement('nav');
    nav.className = 'ufsc-licence-shortcuts';
    nav.setAttribute('aria-label', 'Raccourcis licences UFSC');
    var active = activeShortcut();
    nav.appendChild(link('Mes licences ' + (config().season || ''), config().current, 'current', active));
    nav.appendChild(link('Renouveler des licences', config().renewal, 'renewal', active));
    nav.appendChild(link('Ajouter une licence', config().add, 'add', active));
    nav.appendChild(link('Saisons précédentes', config().previous, 'previous', active));
    nav.appendChild(link('Tableau de bord', config().dashboard, 'dashboard', active));
    return nav;
  }

  function licenceContainer() {
    var selectors = [
      '.ufsc-renewal-wizard',
      '#ufsc-club-licences',
      '#ufsc-current-licences',
      '.ufsc-licence-detail',
      '.ufsc-licence-view',
      '#ufsc-section-add_licence',
      '.ufsc-club-licences'
    ];
    for (var i = 0; i < selectors.length; i++) {
      var node = document.querySelector(selectors[i]);
      if (node) return node;
    }
    return null;
  }

  function insertShortcuts() {
    if (document.querySelector('.ufsc-licence-shortcuts')) return;
    var target = licenceContainer();
    if (!target) return;
    var nav = buildShortcuts();
    if (!nav) return;
    target.insertBefore(nav, target.firstChild);
  }

  function repairMesLicencesLinks() {
    if (!config().current) return;
    document.querySelectorAll('a').forEach(function (a) {
      var text = (a.textContent || '').trim().toLowerCase();
      var href = a.getAttribute('href') || '';
      var isLicenceLink = text === 'mes licences ufsc' || text === 'mes licences' || href.indexOf('#ufsc-club-licences') !== -1 || href.indexOf('#ufsc-current-licences') !== -1;
      if (!isLicenceLink) return;
      if (href.indexOf('view_licence=') !== -1 || href.indexOf('edit_licence=') !== -1) return;
      a.setAttribute('href', config().current);
    });
  }

  function applyTableLabels() {
    document.querySelectorAll('.ufsc-licence-table, .ufsc-renewal-table').forEach(function (table) {
      var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
        return (th.textContent || '').trim();
      });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.prototype.forEach.call(row.children, function (cell, index) {
          if (!cell.getAttribute('data-label') && headers[index]) {
            cell.setAttribute('data-label', headers[index]);
          }
        });
      });
    });
  }

  function mapMessage(code) {
    var messages = {
      licence_included: 'Licence envoyée pour validation. Aucun paiement n’est nécessaire.',
      renewal_included: 'Renouvellement envoyé pour validation. Le quota inclus a été utilisé.',
      licence_saved: 'Licence enregistrée.',
      club_saved: 'Informations du club enregistrées.',
      affiliation_added: 'Affiliation ajoutée au panier.',
      success: 'Action effectuée avec succès.'
    };
    return messages[code] || '';
  }

  function visibleNoticeExists(type) {
    if (type === 'error') return !!document.querySelector('.ufsc-message.ufsc-error, .ufsc-global-notice.is-error');
    return !!document.querySelector('.ufsc-global-notice');
  }

  function insertQueryNotice() {
    var params = new URLSearchParams(window.location.search || '');
    var error = params.get('ufsc_error');
    var message = params.get('ufsc_message');
    var text = '';
    var state = 'info';

    if (error) {
      text = error;
      state = 'error';
    } else if (message) {
      text = mapMessage(message) || message.replace(/[_-]+/g, ' ');
      state = message.indexOf('pending') !== -1 || message.indexOf('waiting') !== -1 ? 'pending' : 'success';
    }
    if (!text || visibleNoticeExists(state)) return;

    var target = licenceContainer() || document.querySelector('main') || document.body;
    var notice = document.createElement('div');
    notice.className = 'ufsc-global-notice is-' + state;
    notice.setAttribute('role', state === 'error' ? 'alert' : 'status');
    notice.setAttribute('aria-live', state === 'error' ? 'assertive' : 'polite');
    var icon = state === 'success' ? '✓' : state === 'error' ? '×' : state === 'pending' ? '⏳' : 'i';
    notice.innerHTML = '<span class="ufsc-global-notice__icon" aria-hidden="true">' + icon + '</span><strong>' + escapeHtml(text) + '</strong>';
    target.insertBefore(notice, target.firstChild);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizeExistingMessages() {
    document.querySelectorAll('.ufsc-message').forEach(function (el) {
      el.classList.add('ufsc-global-message');
      if (el.classList.contains('ufsc-success')) el.setAttribute('data-state', 'success');
      else if (el.classList.contains('ufsc-error')) el.setAttribute('data-state', 'error');
      else if (el.classList.contains('ufsc-warning')) el.setAttribute('data-state', 'pending');
      else el.setAttribute('data-state', 'info');
    });
  }

  function init() {
    repairMesLicencesLinks();
    insertShortcuts();
    applyTableLabels();
    normalizeExistingMessages();
    insertQueryNotice();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
