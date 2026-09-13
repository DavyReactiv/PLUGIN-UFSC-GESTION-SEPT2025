<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Harmonise l'UX du rôle licence dans l'admin UFSC.
 *
 * Le rôle est placé avec les premières informations d'identité et les champs
 * FFST de lieu de naissance ne sont affichés / requis que pour les dirigeants
 * et encadrants concernés. Aucune donnée existante n'est supprimée.
 */
final class UFSC_FFST_Licence_Role_UI {
    private static $pages = array(
        'ufsc_lc_licences',
        'ufsc-gestion-licences',
        'ufsc-licences',
        'ufsc-sql-licences',
        'ufsc-sql-licenses',
    );

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render' ), 60 );
    }

    public static function render() {
        if ( ! is_admin() ) { return; }
        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $page, self::$pages, true ) || ! in_array( $action, array( 'new', 'edit', 'renew' ), true ) ) { return; }
        ?>
        <script>
        (function(){
            var leaderRoles=['president','secretaire','tresorier','entraineur','instructeur','coach','educateur','enseignant'];
            var roleOptions={
                adherent:'Adhérent / pratiquant',
                president:'Président',
                secretaire:'Secrétaire',
                tresorier:'Trésorier',
                entraineur:'Entraîneur',
                instructeur:'Instructeur',
                coach:'Coach',
                educateur:'Éducateur',
                enseignant:'Enseignant'
            };
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            function wrap(el){return el && (el.closest('.ufsc-field,.form-field,.field,.ufsc-admin-field,.ufsc-form-field,tr')||el.parentNode);}
            ready(function(){
                var role=document.querySelector('select[name="role"]');
                if(!role)return;

                Object.keys(roleOptions).forEach(function(value){
                    if(!role.querySelector('option[value="'+value+'"]')){
                        var option=document.createElement('option'); option.value=value; option.textContent=roleOptions[value]; role.appendChild(option);
                    }else if(value==='adherent'){
                        role.querySelector('option[value="adherent"]').textContent=roleOptions[value];
                    }
                });

                <?php if ( 'new' === $action ) : ?>
                if(!role.value){role.value='adherent';}
                <?php endif; ?>
                role.required=true;

                var roleWrap=wrap(role);
                var birthDate=document.querySelector('[name="date_naissance"]');
                var dateWrap=wrap(birthDate);
                if(roleWrap && dateWrap && dateWrap.parentNode){
                    if(dateWrap.nextSibling){dateWrap.parentNode.insertBefore(roleWrap,dateWrap.nextSibling);}else{dateWrap.parentNode.appendChild(roleWrap);}
                }

                var birthplaceInputs=['ville_naissance','departement_naissance','pays_naissance'].map(function(name){return document.querySelector('[name="'+name+'"]');}).filter(Boolean);
                birthplaceInputs.forEach(function(input){var field=wrap(input);if(field)field.classList.add('ufsc-ffst-birthplace-field');});

                function refresh(){
                    var required=leaderRoles.indexOf((role.value||'').toLowerCase())!==-1;
                    birthplaceInputs.forEach(function(input){
                        var field=wrap(input); if(field)field.style.display=required?'':'none';
                        input.required=required;
                    });
                }
                role.addEventListener('change',refresh);
                refresh();
            });
        })();
        </script>
        <?php
    }
}
