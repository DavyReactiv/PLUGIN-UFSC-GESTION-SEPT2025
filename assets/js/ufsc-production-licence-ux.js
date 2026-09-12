(function () {
  'use strict';

  function config() { return window.ufscLicenceUx || {}; }
  function currentSection() { var p = new URLSearchParams(window.location.search || ''); return p.get('ufsc_section') || ''; }
  function currentSeason() { var p = new URLSearchParams(window.location.search || ''); return p.get('ufsc_season') || config().season || ''; }

  function redirectLegacyLicenceAnchor() {
    if (!config().current || currentSection()) return false;
    var hash = window.location.hash || '';
    if (hash === '#licences' || hash === '#ufsc-club-licences' || hash === '#ufsc-current-licences') {
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
    var target = licenceContainer();
    // Detail already has its own contextual action bar. Do not stack a second
    // licence navigation above it.
    if (!target || target.classList.contains('ufsc-licence-detail') || target.classList.contains('ufsc-licence-view')) return;
    var nav = buildShortcuts();
    if (nav) target.insertBefore(nav, target.firstChild);
  }

  function repairMesLicencesLinks() {
    if (!config().current) return;
    document.querySelectorAll('a').forEach(function (a) {
      var text = (a.textContent || '').trim().toLowerCase(), href = a.getAttribute('href') || '';
      var isLicenceLink = text === 'mes licences ufsc' || text === 'mes licences' || href === '#licences' || href.indexOf('#ufsc-club-licences') !== -1 || href.indexOf('#ufsc-current-licences') !== -1;
      if (!isLicenceLink || href.indexOf('view_licence=') !== -1 || href.indexOf('edit_licence=') !== -1) return;
      a.setAttribute('href', config().current);
    });
  }

  function bindCanonicalDashboardLicenceButton() {
    if (!config().current) return;
    document.addEventListener('click', function (event) {
      var target = event.target && event.target.closest ? event.target.closest('.ufsc-nav-btn[data-section="licences"]') : null;
      if (!target) return;
      event.preventDefault();
      event.stopPropagation();
      if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
      window.location.assign(config().current);
    }, true);
  }

  function applyTableLabels() {
    document.querySelectorAll('.ufsc-licence-table, .ufsc-renewal-table').forEach(function (table) {
      var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return (th.textContent || '').trim(); });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.prototype.forEach.call(row.children, function (cell, index) { if (!cell.getAttribute('data-label') && headers[index]) cell.setAttribute('data-label', headers[index]); });
      });
    });
  }

  function option(value, label, selected) {
    var o = document.createElement('option');
    o.value = value; o.textContent = label; o.selected = selected === value; return o;
  }

  function enhanceCurrentLicenceFilters() {
    var form = document.querySelector('.ufsc-current-licence-filters');
    if (!form || form.querySelector('[data-ufsc-category-filter]')) return;
    var params = new URLSearchParams(window.location.search || '');
    var before = form.querySelector('#ufsc-per-page-filter') ? form.querySelector('#ufsc-per-page-filter').closest('label') : form.querySelector('.ufsc-filter-actions');

    var ageLabel = document.createElement('label');
    ageLabel.setAttribute('data-ufsc-category-filter','age');
    ageLabel.textContent = 'Catégorie d’âge';
    var age = document.createElement('select');
    age.name = 'ufsc_age'; age.id = 'ufsc-age-filter';
    ['', 'minor', 'adult'].forEach(function (value, index) { age.appendChild(option(value, ['Toutes','Mineurs','Majeurs'][index], params.get('ufsc_age') || '')); });
    ageLabel.appendChild(age);

    var practiceLabel = document.createElement('label');
    practiceLabel.setAttribute('data-ufsc-category-filter','practice');
    practiceLabel.textContent = 'Catégorie / pratique';
    var practice = document.createElement('select');
    practice.name = 'ufsc_practice'; practice.id = 'ufsc-practice-filter';
    ['', 'leisure', 'competition'].forEach(function (value, index) { practice.appendChild(option(value, ['Toutes','Loisir','Compétition'][index], params.get('ufsc_practice') || '')); });
    practiceLabel.appendChild(practice);

    if (before) { form.insertBefore(ageLabel, before); form.insertBefore(practiceLabel, before); }
    else { form.appendChild(ageLabel); form.appendChild(practiceLabel); }
  }

  function enhanceCurrentLicenceRows() {
    document.querySelectorAll('.ufsc-licence-table--current tbody tr').forEach(function (row) {
      var identity = row.querySelector('td[data-label="Identité"]');
      if (!identity || identity.querySelector('.ufsc-licence-person-name')) return;

      // Metadata can already have been appended by the portal clean-up script.
      // Detach it before reading the name so "NomNé(e) le..." can never be
      // created by two presentation enhancers running in a different order.
      var meta = identity.querySelector('.ufsc-licence-person-meta');
      if (meta) meta.remove();
      var raw = (identity.textContent || '').trim();

      identity.textContent = '';
      var strong = document.createElement('strong');
      strong.className = 'ufsc-licence-person-name';
      strong.textContent = raw || 'Identité non renseignée';
      identity.appendChild(strong);
      if (meta) identity.appendChild(meta);
      identity.classList.add('ufsc-licence-identity-cell');
    });
  }

  function detailGroupFor(label) {
    var key = String(label || '').toLowerCase();
    if (/nom|prénom|sexe|naissance|honorabil|statut|saison|n°|numero|numéro/.test(key)) return 'Identité & dossier';
    if (/email|adresse|ville|postal|pays|téléphone|telephone|contact/.test(key)) return 'Coordonnées';
    if (/poids|niveau|pratique|compét|categorie|catégorie|discipline/.test(key)) return 'Informations sportives';
    if (/assurance|diffusion|image|questionnaire|consent|partenaire|réduction|reduction/.test(key)) return 'Assurances & autorisations';
    return 'Informations administratives';
  }

  function enhanceLicenceDetail() {
    var detail = document.querySelector('.ufsc-licence-detail');
    if (!detail || detail.getAttribute('data-ufsc-premium-detail') === '1') return;
    var table = detail.querySelector('table.ufsc-licence-info');
    if (!table) return;
    detail.setAttribute('data-ufsc-premium-detail','1');

    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
    if (!rows.length) return;
    var values = {};
    rows.forEach(function (row) {
      var cells = row.querySelectorAll('th,td'); if (cells.length < 2) return;
      values[(cells[0].textContent || '').trim()] = (cells[1].textContent || '').trim();
    });

    var heading = detail.querySelector('.ufsc-section-header');
    var hero = document.createElement('section'); hero.className = 'ufsc-licence-detail-hero';
    var name = [values['Prénom'] || '', values['Nom'] || ''].join(' ').trim() || 'Fiche licencié';
    hero.innerHTML = '<div><span class="ufsc-licence-detail-eyebrow">Fiche licencié</span><h2></h2><p class="ufsc-licence-detail-meta"></p></div>';
    hero.querySelector('h2').textContent = name;
    hero.querySelector('.ufsc-licence-detail-meta').textContent = [values['N° UFSC'] || values['Numéro UFSC'] || '', values['Saison'] || '', values['Statut'] || ''].filter(Boolean).join(' · ');
    if (heading) heading.insertAdjacentElement('afterend', hero); else detail.insertBefore(hero, table);

    var grid = document.createElement('div'); grid.className = 'ufsc-licence-detail-grid';
    var groups = {};
    rows.forEach(function (row) {
      var cells = row.querySelectorAll('th,td'); if (cells.length < 2) return;
      var label = (cells[0].textContent || '').trim(), value = (cells[1].textContent || '').trim() || 'Non renseigné';
      var groupName = detailGroupFor(label);
      if (!groups[groupName]) {
        var card = document.createElement('section'); card.className = 'ufsc-licence-detail-card';
        var title = document.createElement('h3'); title.textContent = groupName; card.appendChild(title);
        var dl = document.createElement('dl'); card.appendChild(dl); grid.appendChild(card); groups[groupName] = dl;
      }
      var item = document.createElement('div'); var dt = document.createElement('dt'), dd = document.createElement('dd');
      dt.textContent = label; dd.textContent = value; item.appendChild(dt); item.appendChild(dd); groups[groupName].appendChild(item);
    });
    table.insertAdjacentElement('beforebegin', grid);
    table.classList.add('ufsc-visually-preserved-table'); table.setAttribute('aria-hidden','true');
  }

  function promoteVisibleRenewalProfiles() {
    var wizard = document.querySelector('.ufsc-renewal-wizard'); if (!wizard) return;
    var tableWrap = wizard.querySelector('.ufsc-front-table-scroll'); if (!tableWrap) return;
    var insertionPoint = tableWrap;
    Array.prototype.slice.call(wizard.querySelectorAll('tr.ufsc-renewal-profile-row:not([hidden])')).forEach(function (row) {
      var cell = row.querySelector('td'); if (!cell) return;
      var panel = document.createElement('section'); panel.className = 'ufsc-renewal-profile-panel'; panel.setAttribute('data-profile-id', row.getAttribute('data-profile-id') || '');
      while (cell.firstChild) panel.appendChild(cell.firstChild);
      insertionPoint.insertAdjacentElement('afterend', panel); insertionPoint = panel; row.remove();
    });
  }

  function watchRenewalProfiles() {
    var wizard = document.querySelector('.ufsc-renewal-wizard'); if (!wizard || !window.MutationObserver) return;
    var scheduled = false;
    var observer = new MutationObserver(function () { if (scheduled) return; scheduled = true; window.requestAnimationFrame(function () { scheduled = false; promoteVisibleRenewalProfiles(); }); });
    observer.observe(wizard, {subtree:true,attributes:true,attributeFilter:['hidden']});
  }

  function escapeHtml(value) { return String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
  function showNotice(text,state) {
    if (!text) return; var target = licenceContainer() || document.querySelector('main') || document.body;
    var existing = document.querySelector('.ufsc-global-notice[data-runtime="1"]'); if (existing) existing.remove();
    var notice = document.createElement('div'); notice.className='ufsc-global-notice is-'+(state||'info'); notice.setAttribute('data-runtime','1'); notice.setAttribute('role',state==='error'?'alert':'status'); notice.setAttribute('aria-live',state==='error'?'assertive':'polite');
    var icon=state==='success'?'✓':state==='error'?'×':state==='pending'?'⏳':'i'; notice.innerHTML='<span class="ufsc-global-notice__icon" aria-hidden="true">'+icon+'</span><strong>'+escapeHtml(text)+'</strong>'; target.insertBefore(notice,target.firstChild); notice.scrollIntoView({behavior:'smooth',block:'start'});
  }
  function mapMessage(code) { var messages={licence_included:'Licence envoyée pour validation. Aucun paiement n’est nécessaire.',renewal_included:'Renouvellement envoyé pour validation. Le quota inclus a été utilisé.',licence_saved:'Licence enregistrée.',club_saved:'Informations du club enregistrées.',affiliation_added:'Affiliation ajoutée au panier.',success:'Action effectuée avec succès.'}; return messages[code]||''; }
  function insertQueryNotice() { var params=new URLSearchParams(window.location.search||''),error=params.get('ufsc_error'),message=params.get('ufsc_message'); if(error)showNotice(error,'error');else if(message)showNotice(mapMessage(message)||message.replace(/[_-]+/g,' '),message.indexOf('pending')!==-1||message.indexOf('waiting')!==-1?'pending':'success'); }
  function normalizeExistingMessages() { document.querySelectorAll('.ufsc-message').forEach(function(el){el.classList.add('ufsc-global-message');if(el.classList.contains('ufsc-success'))el.setAttribute('data-state','success');else if(el.classList.contains('ufsc-error'))el.setAttribute('data-state','error');else if(el.classList.contains('ufsc-warning'))el.setAttribute('data-state','pending');else el.setAttribute('data-state','info');}); }

  function canonicalRenewalCounts(){var counts=config().renewalCounts;if(!counts||typeof counts!=='object')return null;return{renewable:Math.max(0,Number(counts.renewable)||0),renewed:Math.max(0,Number(counts.renewed)||0),pending:Math.max(0,Number(counts.pending)||0),payable:Math.max(0,Number(counts.payable)||0),blocked:Math.max(0,Number(counts.blocked)||0),total:Math.max(0,Number(counts.total)||0)};}
  function applyCanonicalRenewalSummary(){var counts=canonicalRenewalCounts(),summary=document.querySelector('.ufsc-renewal-summary');if(!counts||!summary)return;var selection=summary.querySelector('[data-ufsc-selection-count]'),selectionText=selection?(selection.textContent||'').trim():'Aucune licence sélectionnée.';summary.innerHTML='';var title=document.createElement('strong');title.textContent='Résumé global';summary.appendChild(title);summary.appendChild(document.createTextNode(' — '));var global=document.createElement('span');global.setAttribute('data-ufsc-global-renewal-counts','1');global.textContent=counts.renewable+' à renouveler · '+counts.renewed+' déjà renouvelée(s) · '+counts.pending+' demande(s) en cours · '+counts.payable+' paiement(s) à finaliser · '+counts.blocked+' bloquée(s).';summary.appendChild(global);summary.appendChild(document.createTextNode(' '));var scope=document.createElement('span');scope.className='ufsc-renewal-selection-scope';scope.appendChild(document.createTextNode('Sélection courante — '));selection=document.createElement('span');selection.setAttribute('data-ufsc-selection-count','');selection.setAttribute('data-ufsc-selection-scope','current');selection.textContent=selectionText||'Aucune licence sélectionnée.';scope.appendChild(selection);summary.appendChild(scope);}

  function bindLicenceSubmitIntent(){document.querySelectorAll('form.ufsc-licence-form').forEach(function(form){var hidden=form.querySelector('#ufsc_submit_action');if(!hidden)return;hidden.setAttribute('name','ufsc_submit_action');form.addEventListener('click',function(event){var button=event.target&&event.target.closest?event.target.closest('[name="ufsc_submit_action"]'):null;if(!button||button.form!==form)return;hidden.value=button.value||'continue';},true);form.addEventListener('submit',function(event){var submitter=event.submitter||(event.originalEvent&&event.originalEvent.submitter);if(submitter&&submitter.name==='ufsc_submit_action')hidden.value=submitter.value||'continue';},true);});}
  function bindValidationFeedback(){document.addEventListener('invalid',function(event){var form=event.target&&event.target.form;if(!form||(form.id!=='ufsc-renewal-assistant-form'&&!form.classList.contains('ufsc-licence-form')))return;showNotice('Le dossier ne peut pas être finalisé : vérifiez les champs obligatoires signalés ci-dessous.','error');},true);var renewal=document.getElementById('ufsc-renewal-assistant-form');if(renewal){renewal.addEventListener('submit',function(){var selected=renewal.querySelectorAll('input[name="ufsc_renew_ids[]"]:checked');if(!selected.length)showNotice('Sélectionnez au moins une licence à renouveler.','error');});}}

  function init(){if(redirectLegacyLicenceAnchor())return;repairMesLicencesLinks();insertShortcuts();applyTableLabels();enhanceCurrentLicenceFilters();enhanceCurrentLicenceRows();enhanceLicenceDetail();promoteVisibleRenewalProfiles();watchRenewalProfiles();normalizeExistingMessages();insertQueryNotice();applyCanonicalRenewalSummary();bindLicenceSubmitIntent();bindValidationFeedback();}

  bindCanonicalDashboardLicenceButton(); window.addEventListener('hashchange',redirectLegacyLicenceAnchor); if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
