/* UFSC Plugin - Admin JavaScript */

jQuery(document).ready(function($) {

    // Action-oriented dashboard: reuse the existing server KPIs instead of
    // duplicating business counts in JavaScript. New administrators immediately
    // see what needs attention and can jump to the canonical admin screens.
    (function buildAdminPriorityCenter() {
        var $dashboard = $('.ufsc-admin-dashboard');
        if (!$dashboard.length || $dashboard.find('.ufsc-admin-priority-center').length) return;
        var priorities = [];
        $('.ufsc-dashboard-card').each(function() {
            var $card = $(this);
            var label = $.trim($card.find('.card-label').text());
            var value = parseInt($.trim($card.find('.card-value').text()), 10);
            if (!isFinite(value) || value <= 0) return;
            var target = '', tone = 'info', action = 'Consulter';
            if (/affiliations en attente/i.test(label)) { target = 'admin.php?page=ufsc-clubs'; tone = 'urgent'; action = 'Traiter les affiliations'; }
            else if (/clubs à renouveler/i.test(label)) { target = 'admin.php?page=ufsc-clubs'; tone = 'warning'; action = 'Voir les clubs'; }
            else if (/licences.*attente|licences.*brouillon|paiement/i.test(label)) { target = 'admin.php?page=ufsc_lc_licences'; tone = 'warning'; action = 'Voir les licences'; }
            if (target) priorities.push({label: label, value: value, target: target, tone: tone, action: action});
        });
        if (!priorities.length) return;
        var $center = $('<section class="ufsc-admin-priority-center" aria-labelledby="ufsc-admin-priority-title"><div class="ufsc-admin-priority-heading"><div><span class="ufsc-admin-priority-eyebrow">À traiter</span><h2 id="ufsc-admin-priority-title">Actions prioritaires</h2><p>Commencez ici : chaque carte correspond à une action administrative concrète.</p></div></div><div class="ufsc-admin-priority-grid"></div></section>');
        var $grid = $center.find('.ufsc-admin-priority-grid');
        priorities.forEach(function(item) {
            var $item = $('<article class="ufsc-admin-priority-card"><div><span class="ufsc-admin-priority-count"></span><h3></h3></div><a class="button button-primary"></a></article>');
            $item.addClass('is-' + item.tone);
            $item.find('.ufsc-admin-priority-count').text(item.value);
            $item.find('h3').text(item.label);
            $item.find('a').attr('href', item.target).text(item.action);
            $grid.append($item);
        });
        var $anchor = $dashboard.find('.ufsc-dashboard-cards').first();
        if ($anchor.length) $center.insertBefore($anchor); else $dashboard.prepend($center);
    }());

    $('.ufsc-alert.success').delay(5000).fadeOut();

    $('.button-link-delete').on('click', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) { e.preventDefault(); return false; }
    });

    $('form').on('submit', function(e) {
        var hasErrors = false;
        $(this).find('input[required], select[required]').each(function() {
            if ($(this).val() === '') { $(this).addClass('error'); hasErrors = true; } else { $(this).removeClass('error'); }
        });
        $(this).find('input[type="email"]').each(function() {
            var email = $(this).val();
            if (email && !isValidEmail(email)) { $(this).addClass('error'); hasErrors = true; } else { $(this).removeClass('error'); }
        });
        if (hasErrors) { e.preventDefault(); alert('Veuillez corriger les erreurs dans le formulaire.'); return false; }
    });

    $('input[type="email"]').on('blur', function() { var email = $(this).val(); if (email && !isValidEmail(email)) $(this).addClass('error'); else $(this).removeClass('error'); });
    function isValidEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }

    if ($('.ufsc-alert.error').length) $('html, body').animate({scrollTop: $('.ufsc-alert.error').offset().top - 100}, 500);
    $('.ufsc-card').hover(function(){$(this).addClass('hover');},function(){$(this).removeClass('hover');});
    $('form').on('submit', function() { var $submitBtn=$(this).find('button[type="submit"], input[type="submit"]'); $submitBtn.prop('disabled',true).text('Enregistrement...'); });

    $('#ufsc-club-selector').on('change', function() { var selectedOption=$(this).find('option:selected'); var region=selectedOption.data('region')||''; $('#ufsc-auto-region').val(region); $('input[name="region"], select[name="region"]').val(region); });
    if ($('#ufsc-club-selector').val()) $('#ufsc-club-selector').trigger('change');
    $('#select-all-licences').on('change', function() { $('input[name="licence_ids[]"]').prop('checked',$(this).prop('checked')); });
    $('input[name="licence_ids[]"]').on('change', function() { var total=$('input[name="licence_ids[]"]').length,checked=$('input[name="licence_ids[]"]:checked').length; $('#select-all-licences').prop('checked',total===checked); });

    $('.ufsc-quick-status').on('change', function() {
        var $select=$(this),licenceId=$select.data('licence-id'),newStatus=$select.val(),originalStatus=$select.data('original-status'); $select.prop('disabled',true);
        $.ajax({url:ajaxurl,type:'POST',data:{action:'ufsc_update_licence_status',licence_id:licenceId,status:newStatus,nonce:$('#ufsc-ajax-nonce').val()},success:function(response){if(response.success){var $badge=$select.closest('tr').find('.ufsc-badge');$badge.removeClass().addClass('ufsc-badge ufsc-badge-'+response.data.badge_class).text(response.data.status_label);showToast('Statut mis à jour','success');$select.data('original-status',newStatus);}else{$select.val(originalStatus);showToast('Erreur lors de la mise à jour','error');}},error:function(){$select.val(originalStatus);showToast('Erreur de communication','error');},complete:function(){$select.prop('disabled',false);}});
    });

    $('.ufsc-send-to-payment').on('click', function(e) {
        e.preventDefault(); var $btn=$(this),selectedLicences=[]; $('input[name="licence_ids[]"]:checked').each(function(){selectedLicences.push($(this).val());});
        if(!selectedLicences.length){alert('Veuillez sélectionner au moins une licence.');return;} if(!confirm('Envoyer '+selectedLicences.length+' licence(s) au paiement ?'))return;
        $btn.prop('disabled',true).text('Envoi en cours...');
        $.ajax({url:ajaxurl,type:'POST',data:{action:'ufsc_send_to_payment',licence_ids:selectedLicences,nonce:$('#ufsc-ajax-nonce').val()},success:function(response){if(response.success){showToast('Commande créée avec succès','success');if(response.data.payment_url)window.open(response.data.payment_url,'_blank');}else showToast(response.data.message||'Erreur lors de la création de la commande','error');},error:function(){showToast('Erreur de communication','error');},complete:function(){$btn.prop('disabled',false).text('Envoyer au paiement');}});
    });

    function showToast(message,type){var toastClass=type==='success'?'notice-success':'notice-error';var $toast=$('<div class="notice '+toastClass+' is-dismissible ufsc-toast">').append('<p>'+message+'</p>').append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>');if($('.wrap h1').length)$toast.insertAfter('.wrap h1');else $('.wrap').prepend($toast);setTimeout(function(){$toast.fadeOut(function(){$(this).remove();});},3000);$toast.find('.notice-dismiss').on('click',function(){$toast.fadeOut(function(){$(this).remove();});});}

    $('.ufsc-gestion_page_ufsc-licences #bulk-actions-form').on('submit', function() { var selectedAction=$('#bulk-action-selector').val(),selectedCount=$('input[name="licence_ids[]"]:checked').length;if(selectedCount===0){alert('Veuillez sélectionner au moins un élément à supprimer.');return false;}if(selectedAction==='delete'&&!confirm('Êtes-vous sûr de vouloir supprimer '+selectedCount+' élément(s) ? Cette action est irréversible.'))return false; });
});

var additionalCSS = `
.ufsc-field input.error,.ufsc-field select.error,.ufsc-field textarea.error{border-color:#dc3232!important;box-shadow:0 0 0 2px rgba(220,50,50,.1)!important}.ufsc-card.hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.2)}button:disabled{opacity:.6;cursor:not-allowed}.ufsc-readonly-field{background-color:#f7f7f7!important;color:#666!important}.ufsc-club-selector{min-width:300px}.ufsc-quick-status{min-width:120px}.ufsc-toast{position:relative;margin:10px 0}.ufsc-toast.notice{border-left:4px solid;padding:12px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.1)}.ufsc-send-to-payment{background:#f56e28;border-color:#f56e28;color:white}.ufsc-send-to-payment:hover{background:#e85d1a;border-color:#e85d1a}
.ufsc-admin-priority-center{margin:18px 0 24px;padding:20px;border:1px solid #dce5ee;border-radius:16px;background:#fff;box-shadow:0 8px 28px rgba(17,52,86,.07)}.ufsc-admin-priority-eyebrow{display:block;color:#8a5a00;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.ufsc-admin-priority-heading h2{margin:4px 0 3px;color:#173f5f}.ufsc-admin-priority-heading p{margin:0 0 15px;color:#596b7a}.ufsc-admin-priority-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.ufsc-admin-priority-card{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:15px;border:1px solid #dce5ee;border-left:5px solid #2b6f99;border-radius:12px;background:#f9fcfe}.ufsc-admin-priority-card.is-urgent{border-left-color:#c92a2a;background:#fff7f7}.ufsc-admin-priority-card.is-warning{border-left-color:#d39b12;background:#fffbef}.ufsc-admin-priority-count{display:block;color:#173f5f;font-size:26px;font-weight:850;line-height:1}.ufsc-admin-priority-card h3{margin:4px 0 0;font-size:14px;color:#263746}@media(max-width:782px){.ufsc-admin-priority-card{align-items:stretch;flex-direction:column}.ufsc-admin-priority-card .button{width:100%;text-align:center}}
`;
if(!document.getElementById('ufsc-dynamic-css')){var style=document.createElement('style');style.id='ufsc-dynamic-css';style.textContent=additionalCSS;document.head.appendChild(style);}
