<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Harmonise l'UX du rôle licence dans l'admin UFSC et le wizard club réel.
 *
 * Une seule source de vérité est utilisée : la colonne licence `role` déjà
 * existante. Ce module ne crée aucune donnée et ne modifie aucune licence.
 *
 * - le rôle est placé en tête des informations personnelles ;
 * - les champs FFST de lieu de naissance restent masqués pour les adhérents ;
 * - ils sont affichés pour les dirigeants / encadrants concernés ;
 * - les valeurs historiques de lieu de naissance sont relues sans être vidées ;
 * - la liste admin affiche le rôle via une seule lecture groupée, sans requête
 *   par ligne et sans modifier la requête principale de la page Licences.
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
        add_action( 'wp_footer', array( __CLASS__, 'render_front_wizard_script' ), 60 );
        add_action( 'wp_ajax_ufsc_admin_licence_roles', array( __CLASS__, 'ajax_roles' ) );
    }

    private static function role_labels() {
        return array(
            'adherent'              => __( 'Adhérent / pratiquant', 'ufsc-clubs' ),
            'president'             => __( 'Président', 'ufsc-clubs' ),
            'secretaire'            => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier'             => __( 'Trésorier', 'ufsc-clubs' ),
            'dirigeant'             => __( 'Dirigeant', 'ufsc-clubs' ),
            'entraineur'            => __( 'Entraîneur', 'ufsc-clubs' ),
            'encadrant'             => __( 'Encadrant', 'ufsc-clubs' ),
            'responsable_technique' => __( 'Responsable technique', 'ufsc-clubs' ),
            'instructeur'           => __( 'Instructeur', 'ufsc-clubs' ),
            'coach'                 => __( 'Coach', 'ufsc-clubs' ),
            'educateur'             => __( 'Éducateur', 'ufsc-clubs' ),
            'enseignant'            => __( 'Enseignant', 'ufsc-clubs' ),
        );
    }

    private static function leader_roles() {
        return array(
            'president', 'secretaire', 'tresorier', 'dirigeant', 'entraineur',
            'encadrant', 'responsable_technique', 'instructeur', 'coach',
            'educateur', 'enseignant',
        );
    }

    private static function normalize_role( $role ) {
        if ( function_exists( 'ufsc_normalize_club_role' ) ) {
            return sanitize_key( (string) ufsc_normalize_club_role( $role ) );
        }
        $role = sanitize_key( (string) $role );
        return '' !== $role ? $role : 'adherent';
    }

    private static function role_label( $role ) {
        $role   = self::normalize_role( $role );
        $labels = self::role_labels();
        if ( isset( $labels[ $role ] ) ) {
            return $labels[ $role ];
        }
        return ucfirst( str_replace( '_', ' ', $role ) );
    }

    private static function is_licence_page() {
        if ( ! is_admin() ) { return false; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
            ? sanitize_key( wp_unslash( $_GET['page'] ) )
            : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return in_array( $page, self::$pages, true );
    }

    /**
     * Return the existing birthplace values for a licence without modifying it.
     *
     * @param int $licence_id Licence id.
     * @return array<string,string>
     */
    private static function get_licence_birthplace_payload( $licence_id ) {
        $payload = array(
            'ville_naissance'       => '',
            'departement_naissance' => '',
            'pays_naissance'        => '',
        );
        $licence_id = absint( $licence_id );
        if ( ! $licence_id || ! class_exists( 'UFSC_SQL' ) ) { return $payload; }

        global $wpdb;
        $settings = (array) UFSC_SQL::get_settings();
        $table    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
        $pk       = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['pk_licence'] ?? 'id' ) );
        $columns  = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        if ( '' === $table || '' === $pk || empty( $columns ) ) { return $payload; }

        $select = array();
        foreach ( array_keys( $payload ) as $field ) {
            if ( in_array( $field, $columns, true ) ) { $select[] = '`' . $field . '`'; }
        }
        if ( empty( $select ) ) { return $payload; }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT ' . implode( ',', $select ) . " FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1",
                $licence_id
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

        if ( ! is_array( $row ) ) { return $payload; }
        foreach ( array_keys( $payload ) as $field ) {
            if ( array_key_exists( $field, $row ) ) { $payload[ $field ] = (string) $row[ $field ]; }
        }
        return $payload;
    }

    private static function requested_admin_licence_id() {
        foreach ( array( 'id', 'licence_id' ) as $key ) {
            if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $id = absint( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $id ) { return $id; }
            }
        }
        return 0;
    }

    private static function requested_front_licence_id() {
        foreach ( array( 'edit_licence', 'licence_id' ) as $key ) {
            if ( isset( $_GET[ $key ] ) && ! is_array( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $id = absint( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $id ) { return $id; }
            }
        }
        return 0;
    }

    /**
     * Return roles for the visible licences with one read-only query.
     */
    public static function ajax_roles() {
        if ( ! current_user_can( 'read' ) ) {
            wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
        }
        check_ajax_referer( 'ufsc_admin_licence_roles', 'nonce' );

        $ids = isset( $_POST['ids'] )
            ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) ) ) )
            : array();
        $ids = array_slice( $ids, 0, 100 );
        if ( empty( $ids ) ) {
            wp_send_json_success( array() );
        }

        global $wpdb;
        $settings = class_exists( 'UFSC_SQL' ) ? (array) UFSC_SQL::get_settings() : array();
        $table    = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['table_licences'] ?? '' ) );
        $pk       = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $settings['pk_licence'] ?? 'id' ) );
        $columns  = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();

        if ( '' === $table || '' === $pk || ! in_array( 'role', $columns, true ) ) {
            wp_send_json_success( array() );
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql          = $wpdb->prepare(
            "SELECT `{$pk}` AS licence_id, role FROM `{$table}` WHERE `{$pk}` IN ({$placeholders})",
            $ids
        );
        $rows         = (array) $wpdb->get_results( $sql );
        $result       = array();

        foreach ( $rows as $row ) {
            $id   = absint( $row->licence_id ?? 0 );
            $role = self::normalize_role( $row->role ?? 'adherent' );
            if ( ! $id ) { continue; }
            $result[ (string) $id ] = array(
                'role'  => $role,
                'label' => self::role_label( $role ),
            );
        }

        wp_send_json_success( $result );
    }

    public static function render() {
        if ( ! self::is_licence_page() ) { return; }

        $action = isset( $_GET['action'] ) && ! is_array( $_GET['action'] )
            ? sanitize_key( wp_unslash( $_GET['action'] ) )
            : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( in_array( $action, array( 'new', 'edit', 'renew' ), true ) ) {
            self::render_form_script( $action );
            return;
        }

        if ( '' === $action ) {
            self::render_list_script();
        }
    }

    private static function render_form_script( $action ) {
        $role_options = self::role_labels();
        $leader_roles = self::leader_roles();
        $birthplace   = self::get_licence_birthplace_payload( self::requested_admin_licence_id() );
        ?>
        <script>
        (function(){
            var leaderRoles=<?php echo wp_json_encode( array_values( $leader_roles ) ); ?>;
            var roleOptions=<?php echo wp_json_encode( $role_options ); ?>;
            var birthplaceData=<?php echo wp_json_encode( $birthplace ); ?>;
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            function wrap(el){return el && (el.closest('.ufsc-field,.form-field,.field,.ufsc-admin-field,.ufsc-form-field,tr')||el.parentNode);}
            function addBirthplaceField(name,label,anchor){
                var existing=document.querySelector('[name="'+name+'"]');
                if(existing)return existing;
                if(!anchor || !anchor.parentNode)return null;
                var field=document.createElement('div');
                field.className='ufsc-field ufsc-ffst-birthplace-field';
                var lab=document.createElement('label');
                lab.setAttribute('for',name);
                lab.textContent=label;
                var input=document.createElement('input');
                input.type='text';input.id=name;input.name=name;input.autocomplete='off';
                input.value=birthplaceData[name]||'';
                field.appendChild(lab);field.appendChild(input);
                anchor.parentNode.insertBefore(field,anchor.nextSibling);
                return input;
            }
            ready(function(){
                var role=document.querySelector('select[name="role"]');
                if(!role)return;

                Object.keys(roleOptions).forEach(function(value){
                    var existing=role.querySelector('option[value="'+value+'"]');
                    if(!existing){
                        var option=document.createElement('option'); option.value=value; option.textContent=roleOptions[value]; role.appendChild(option);
                    }else{
                        existing.textContent=roleOptions[value];
                    }
                });

                <?php if ( 'new' === $action ) : ?>
                if(!role.value){role.value='adherent';}
                <?php endif; ?>
                role.required=true;

                /* Le rôle pilote les champs suivants : il doit donc être visible en premier. */
                var roleWrap=wrap(role);
                var birthDate=document.querySelector('[name="date_naissance"]');
                var dateWrap=wrap(birthDate);
                var identityGrid=dateWrap && dateWrap.parentNode ? dateWrap.parentNode : (roleWrap ? roleWrap.parentNode : null);
                if(roleWrap && identityGrid && identityGrid.firstElementChild!==roleWrap){
                    identityGrid.insertBefore(roleWrap,identityGrid.firstElementChild);
                }

                /* Le formulaire SQL admin ne rendait pas ces champs : on les ajoute une seule fois. */
                var anchor=dateWrap||roleWrap;
                var ville=addBirthplaceField('ville_naissance','Ville de naissance',anchor);
                if(ville)anchor=wrap(ville)||anchor;
                var departement=addBirthplaceField('departement_naissance','Département de naissance',anchor);
                if(departement)anchor=wrap(departement)||anchor;
                addBirthplaceField('pays_naissance','Pays de naissance',anchor);

                var birthplaceInputs=['ville_naissance','departement_naissance','pays_naissance'].map(function(name){return document.querySelector('[name="'+name+'"]');}).filter(Boolean);
                birthplaceInputs.forEach(function(input){var field=wrap(input);if(field)field.classList.add('ufsc-ffst-birthplace-field');});

                function refresh(){
                    var required=leaderRoles.indexOf((role.value||'').toLowerCase())!==-1;
                    birthplaceInputs.forEach(function(input){
                        var field=wrap(input);
                        if(field){field.style.display=required?'':'none';field.setAttribute('aria-hidden',required?'false':'true');}
                        /* On ne vide jamais une valeur historique quand le rôle change. */
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

    /**
     * Keep the real six-step club wizard aligned with the admin form without
     * duplicating the licence business logic.
     */
    public static function render_front_wizard_script() {
        if ( is_admin() || ! is_user_logged_in() ) { return; }
        $role_options = self::role_labels();
        $leader_roles = self::leader_roles();
        $birthplace   = self::get_licence_birthplace_payload( self::requested_front_licence_id() );
        ?>
        <script>
        (function(){
            var leaderRoles=<?php echo wp_json_encode( array_values( $leader_roles ) ); ?>;
            var roleOptions=<?php echo wp_json_encode( $role_options ); ?>;
            var birthplaceData=<?php echo wp_json_encode( $birthplace ); ?>;
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            function fieldWrap(el){return el && (el.closest('.ufsc-field,.form-field,.field,.ufsc-form-field')||el.parentNode);}
            function addField(name,label,anchor){
                var existing=document.querySelector('.ufsc-licence-wizard-progress ~ * [name="'+name+'"]')||document.querySelector('[name="'+name+'"]');
                if(existing)return existing;
                if(!anchor || !anchor.parentNode)return null;
                var field=document.createElement('div');field.className='ufsc-field ufsc-ffst-birthplace-field';
                var lab=document.createElement('label');lab.setAttribute('for',name);lab.textContent=label+' *';
                var input=document.createElement('input');input.type='text';input.id=name;input.name=name;input.autocomplete='off';input.value=birthplaceData[name]||'';
                field.appendChild(lab);field.appendChild(input);anchor.parentNode.insertBefore(field,anchor.nextSibling);return input;
            }
            ready(function(){
                var wizard=document.querySelector('.ufsc-licence-wizard-progress');
                if(!wizard)return;
                var form=wizard.closest('form');
                if(!form)return;
                var role=form.querySelector('select[name="role"]');
                var nom=form.querySelector('[name="nom"]');
                var birthDate=form.querySelector('[name="date_naissance"]');
                if(!role || !nom || !birthDate)return;

                Object.keys(roleOptions).forEach(function(value){
                    var existing=role.querySelector('option[value="'+value+'"]');
                    if(!existing){var option=document.createElement('option');option.value=value;option.textContent=roleOptions[value];role.appendChild(option);}else{existing.textContent=roleOptions[value];}
                });
                if(!role.value){role.value='adherent';}
                role.required=true;

                var roleWrap=fieldWrap(role);
                var nomWrap=fieldWrap(nom);
                var identityCard=nomWrap ? nomWrap.closest('.ufsc-card.ufsc-form-section') : null;
                if(roleWrap && identityCard && roleWrap.parentNode!==identityCard){identityCard.insertBefore(roleWrap,nomWrap||identityCard.firstElementChild);}
                else if(roleWrap && identityCard && roleWrap!==identityCard.firstElementChild){identityCard.insertBefore(roleWrap,nomWrap||identityCard.firstElementChild);}

                var anchor=fieldWrap(birthDate)||roleWrap;
                var ville=addField('ville_naissance','Ville de naissance',anchor);
                if(ville)anchor=fieldWrap(ville)||anchor;
                var departement=addField('departement_naissance','Département de naissance',anchor);
                if(departement)anchor=fieldWrap(departement)||anchor;
                addField('pays_naissance','Pays de naissance',anchor);

                var inputs=['ville_naissance','departement_naissance','pays_naissance'].map(function(name){return form.querySelector('[name="'+name+'"]');}).filter(Boolean);
                function refresh(){
                    var required=leaderRoles.indexOf((role.value||'').toLowerCase())!==-1;
                    inputs.forEach(function(input){
                        var field=fieldWrap(input);
                        if(field){field.style.display=required?'':'none';field.setAttribute('aria-hidden',required?'false':'true');}
                        input.required=required;
                    });
                }
                role.addEventListener('change',refresh);refresh();
            });
        })();
        </script>
        <?php
    }

    private static function render_list_script() {
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'ufsc_admin_licence_roles' );
        ?>
        <style>
            .ufsc-admin-licences-table .ufsc-role-badge{display:inline-flex;align-items:center;white-space:nowrap;padding:3px 7px;border-radius:999px;background:#f0f6fc;border:1px solid #c7d7ea;color:#174a7c;font-size:11px;font-weight:600;line-height:1.2}
            .ufsc-admin-licences-table .ufsc-role-badge[data-role="adherent"]{background:#f6f7f7;border-color:#dcdcde;color:#50575e;font-weight:500}
        </style>
        <script>
        (function(){
            'use strict';
            function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
            ready(function(){
                var table=document.querySelector('.ufsc-admin-licences-table');
                if(!table)return;
                var headerRow=table.querySelector('thead tr');
                if(!headerRow)return;
                var headers=Array.prototype.slice.call(headerRow.children);
                var clubIndex=headers.findIndex(function(cell){return /^Club$/i.test((cell.textContent||'').trim());});
                if(clubIndex<0)return;

                if(!headerRow.querySelector('.column-ufsc-role')){
                    var roleHeader=document.createElement('th');
                    roleHeader.className='column-ufsc-role';
                    roleHeader.textContent='Rôle au club';
                    headers[clubIndex].insertAdjacentElement('afterend',roleHeader);
                }

                var rows=Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
                var ids=[];
                var rowsById={};
                rows.forEach(function(row){
                    var cells=row.children;
                    if(!cells.length || !cells[clubIndex])return;
                    var roleCell=document.createElement('td');
                    roleCell.className='column-ufsc-role';
                    roleCell.textContent='—';
                    cells[clubIndex].insertAdjacentElement('afterend',roleCell);
                    var idNode=row.querySelector('[data-ufsc-licence-id]') || row.querySelector('input[name="licence_ids[]"]');
                    var rawId=idNode ? (idNode.getAttribute('data-ufsc-licence-id') || idNode.value || '') : '';
                    var id=(String(rawId).match(/\d+/)||[])[0];
                    if(!id)return;
                    ids.push(id);rowsById[id]=roleCell;
                });
                if(!ids.length)return;

                var body=new URLSearchParams();
                body.append('action','ufsc_admin_licence_roles');
                body.append('nonce',<?php echo wp_json_encode( $nonce ); ?>);
                ids.forEach(function(id){body.append('ids[]',id);});
                fetch(<?php echo wp_json_encode( $ajax_url ); ?>,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
                    .then(function(response){return response.json();})
                    .then(function(payload){
                        if(!payload || !payload.success || !payload.data)return;
                        Object.keys(rowsById).forEach(function(id){
                            var item=payload.data[id]||{role:'adherent',label:'Adhérent / pratiquant'};
                            var cell=rowsById[id];
                            cell.textContent='';
                            var badge=document.createElement('span');
                            badge.className='ufsc-role-badge';
                            badge.setAttribute('data-role',item.role||'adherent');
                            badge.textContent=item.label||'Adhérent / pratiquant';
                            cell.appendChild(badge);
                        });
                    }).catch(function(){ /* La liste reste utilisable si la lecture du rôle échoue. */ });
            });
        })();
        </script>
        <?php
    }
}
