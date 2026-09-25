<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Réorganise uniquement la présentation de la fiche club admin.
 *
 * Cette classe ne crée aucun stockage et ne modifie aucune valeur : elle déplace
 * les contrôles déjà rendus par UFSC_SQL / les bridges FFST afin d'éviter que les
 * champs complémentaires soient mélangés dans « Autres informations ».
 */
final class UFSC_FFST_Club_Admin_Layout {
    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 140 );
    }

    public static function render() {
        if ( ! is_admin() ) { return; }

        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-sql-clubs' !== $page || ! in_array( $action, array( 'new', 'edit', 'view' ), true ) ) { return; }
        ?>
        <style>
            .ufsc-ffst-admin-layout{margin-top:16px}
            .ufsc-ffst-admin-layout__intro{margin:0 0 14px;color:#50575e}
            .ufsc-ffst-admin-layout__status{margin:12px 0 16px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#f7f9fb}
            .ufsc-ffst-admin-layout__status .ufsc-ffst-progress{margin-top:0}
            .ufsc-ffst-leaders{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:12px}
            .ufsc-ffst-leader{border:1px solid #dcdcde;border-radius:12px;background:#fbfbfc;padding:16px;min-width:0;box-sizing:border-box}
            .ufsc-ffst-leader__title{display:flex;align-items:center;gap:8px;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #e5e7eb;color:#0b4a78;font-size:15px;font-weight:700}
            .ufsc-ffst-leader__grid,.ufsc-ffst-admin-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 16px}
            .ufsc-ffst-leader__group{grid-column:1/-1;margin:4px 0 -2px;padding-top:10px;border-top:1px solid #e5e7eb;color:#50575e;font-size:11px;font-weight:700;letter-spacing:.035em;text-transform:uppercase}
            .ufsc-ffst-leader__group:first-child{margin-top:0;padding-top:0;border-top:0}
            .ufsc-ffst-admin-grid{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:14px}
            .ufsc-ffst-admin-group{grid-column:1/-1;margin:8px 0 0;padding-top:12px;border-top:1px solid #e5e7eb;font-size:13px;font-weight:700;color:#1d2327}
            .ufsc-ffst-leader .ufsc-field,.ufsc-ffst-leader .ufsc-admin-field,.ufsc-ffst-leader .ufsc-form-field,.ufsc-ffst-leader .form-field,.ufsc-ffst-leader .field,
            .ufsc-ffst-admin-grid .ufsc-field,.ufsc-ffst-admin-grid .ufsc-admin-field,.ufsc-ffst-admin-grid .ufsc-form-field,.ufsc-ffst-admin-grid .form-field,.ufsc-ffst-admin-grid .field{min-width:0;margin:0!important}
            .ufsc-ffst-leader input,.ufsc-ffst-leader select,.ufsc-ffst-leader textarea,.ufsc-ffst-admin-grid input,.ufsc-ffst-admin-grid select,.ufsc-ffst-admin-grid textarea{width:100%;max-width:100%;box-sizing:border-box}
            .ufsc-ffst-foreign-parent{display:block!important}
            .ufsc-ffst-foreign-parent.is-foreign-required label:after{content:' · requis si naissance à l’étranger';font-size:10px;font-weight:600;color:#8a5a00}
            .ufsc-ffst-empty-grid{display:none!important}
            @media(max-width:1500px){.ufsc-ffst-leaders{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:1100px){.ufsc-ffst-leaders{grid-template-columns:1fr}.ufsc-ffst-admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:782px){.ufsc-ffst-leader__grid,.ufsc-ffst-admin-grid{grid-template-columns:1fr}.ufsc-ffst-leader{padding:14px}}
        </style>
        <script>
        (function(){
            var leaderLabels={president:'Président',secretaire:'Secrétaire',tresorier:'Trésorier',entraineur:'Entraîneur / instructeur'};
            var ffstGroups=[
                {title:'Références fédérales',fields:['numero_affiliation_ffst','disciplines_ffst','codes_disciplines_ffst','numero_agrement_js','date_agrement_js']},
                {title:'Lieu principal d’entraînement',fields:['adresse_salle','complement_adresse_salle','code_postal_salle','ville_salle']},
                {title:'Correspondant FFST',fields:['correspondant_nom','correspondant_prenom','correspondant_tel','correspondant_email']},
                {title:'Signataire du dossier',fields:['signataire_nom','signataire_prenom','signataire_qualite']}
            ];

            function norm(value){
                return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
            }
            function wrap(input){
                if(!input)return null;
                return input.closest('.ufsc-field,.ufsc-admin-field,.ufsc-form-field,.form-field,.field') || input.parentElement;
            }
            function sectionByTitle(title){
                var wanted=norm(title);
                var sections=document.querySelectorAll('.ufsc-admin-section,.ufsc-admin-card');
                for(var i=0;i<sections.length;i++){
                    var header=sections[i].querySelector('h2,h3,.ufsc-admin-card-header');
                    if(header && norm(header.textContent).indexOf(wanted)!==-1)return sections[i];
                }
                return null;
            }
            function field(name){return document.querySelector('[name="'+name+'"]');}
            function move(name,target,extraClass){
                var input=field(name),box=wrap(input);
                if(!box||!target)return false;
                if(extraClass)box.classList.add(extraClass);
                target.appendChild(box);
                return true;
            }
            function heading(text){
                var el=document.createElement('div');el.className='ufsc-ffst-admin-group';el.textContent=text;return el;
            }
            function cleanEmptyContainers(section){
                if(!section)return;
                var nodes=section.querySelectorAll('.ufsc-admin-grid,.ufsc-grid,.fields-grid,.form-grid');
                nodes.forEach(function(node){if(!node.querySelector('input,select,textarea'))node.classList.add('ufsc-ffst-empty-grid');});
            }
            function toggleParents(prefix){
                var country=field(prefix+'_pays_naissance');
                var father=wrap(field(prefix+'_pere_nom_prenom'));
                var mother=wrap(field(prefix+'_mere_nom_prenom'));
                [father,mother].forEach(function(node){if(node)node.classList.add('ufsc-ffst-foreign-parent');});
                function update(){
                    var value=norm(country?country.value:'');
                    var foreign=!!value && !['france','fr','f','francaise','francais'].includes(value);
                    [father,mother].forEach(function(node){if(node)node.classList.toggle('is-foreign-required',foreign);});
                }
                if(country){country.addEventListener('input',update);country.addEventListener('change',update);}update();
            }
            function leaderGroups(prefix){
                return [
                    {title:'Identité & contact',fields:[prefix+'_prenom',prefix+'_nom',prefix+'_poste',prefix+'_tel',prefix+'_telephone',prefix+'_email']},
                    {title:'Naissance',fields:[prefix+'_date_naissance',prefix+'_ville_naissance',prefix+'_departement_naissance',prefix+'_pays_naissance']},
                    {title:'Adresse',fields:[prefix+'_adresse',prefix+'_complement_adresse',prefix+'_code_postal',prefix+'_ville']},
                    {title:'Filiation (naissance à l’étranger)',fields:[prefix+'_pere_nom_prenom',prefix+'_mere_nom_prenom']}
                ];
            }
            function appendLeaderGroup(grid,group){
                var existing=group.fields.filter(function(name){return !!field(name);});
                if(!existing.length)return 0;
                var groupTitle=document.createElement('div');
                groupTitle.className='ufsc-ffst-leader__group';
                groupTitle.textContent=group.title;
                grid.appendChild(groupTitle);
                existing.forEach(function(name){
                    move(name,grid,(name.indexOf('_pere_nom_prenom')>0||name.indexOf('_mere_nom_prenom')>0)?'ufsc-ffst-foreign-parent':'');
                });
                return existing.length;
            }
            function buildLeaders(){
                var section=sectionByTitle('Dirigeants');if(!section)return false;
                if(section.querySelector('.ufsc-ffst-leaders'))return true;
                var leaders=document.createElement('div');leaders.className='ufsc-ffst-leaders';
                Object.keys(leaderLabels).forEach(function(prefix){
                    var card=document.createElement('article');card.className='ufsc-ffst-leader';
                    var title=document.createElement('h4');title.className='ufsc-ffst-leader__title';title.textContent=leaderLabels[prefix];card.appendChild(title);
                    var grid=document.createElement('div');grid.className='ufsc-ffst-leader__grid';card.appendChild(grid);
                    leaderGroups(prefix).forEach(function(group){appendLeaderGroup(grid,group);});
                    if(grid.querySelector('input,select,textarea'))leaders.appendChild(card);
                });
                section.appendChild(leaders);
                Object.keys(leaderLabels).forEach(toggleParents);
                cleanEmptyContainers(section);
                return true;
            }
            function buildFfst(){
                var form=field('nom');form=form?form.closest('form'):document.querySelector('form');if(!form)return false;
                if(form.querySelector('.ufsc-ffst-admin-layout'))return true;
                var other=sectionByTitle('Autres informations');
                var leaders=sectionByTitle('Dirigeants');
                if(!other && !leaders)return false;

                var section=document.createElement('section');section.className='ufsc-admin-card ufsc-admin-section ufsc-ffst-admin-layout';
                var header=document.createElement('header');header.className='ufsc-admin-card-header';
                var h=document.createElement('h3');h.textContent='Affiliation & dossier FFST';header.appendChild(h);section.appendChild(header);
                var intro=document.createElement('p');intro.className='ufsc-ffst-admin-layout__intro';intro.textContent='Informations utilisées pour préparer et préremplir les documents officiels FFST. Les champs restent non bloquants.';section.appendChild(intro);

                var legacy=document.querySelector('.ufsc-ffst-profile-box');
                if(legacy){
                    var status=document.createElement('div');status.className='ufsc-ffst-admin-layout__status';
                    ['.ufsc-ffst-progress','.ufsc-ffst-summary','details'].forEach(function(selector){var node=legacy.querySelector(selector);if(node)status.appendChild(node);});
                    if(status.children.length)section.appendChild(status);
                    legacy.remove();
                }

                var grid=document.createElement('div');grid.className='ufsc-ffst-admin-grid';section.appendChild(grid);
                ffstGroups.forEach(function(group){
                    grid.appendChild(heading(group.title));
                    group.fields.forEach(function(name){move(name,grid,'');});
                });
                if(other && other.parentNode){other.parentNode.insertBefore(section,other);}else if(leaders && leaders.parentNode){leaders.parentNode.insertBefore(section,leaders.nextSibling);}else{form.appendChild(section);}
                cleanEmptyContainers(other);
                return true;
            }
            function build(){var a=buildLeaders(),b=buildFfst();return a&&b;}
            function boot(){
                if(build())return;
                var tries=0,timer=setInterval(function(){tries++;if(build()||tries>35)clearInterval(timer);},150);
            }
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
        })();
        </script>
        <?php
    }
}
