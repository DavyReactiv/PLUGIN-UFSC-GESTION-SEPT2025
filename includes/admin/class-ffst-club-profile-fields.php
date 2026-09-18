<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Complète le profil club avec les données nécessaires aux documents FFST.
 *
 * Principes de sécurité :
 * - migration strictement additive et idempotente ;
 * - aucun champ existant n'est supprimé/renommé/écrasé ;
 * - aucun nouveau champ n'est bloquant pour les clubs existants ;
 * - le même stockage canonique alimente le profil club et les exports FFST.
 */
final class UFSC_FFST_Club_Profile_Fields {
    const SCHEMA_OPTION = 'ufsc_ffst_club_profile_schema_v1';

    private static $leader_prefixes = array( 'president', 'secretaire', 'tresorier', 'entraineur' );

    public static function init() {
        add_action( 'init', array( __CLASS__, 'ensure_schema' ), 5 );
        add_filter( 'ufsc_club_fields', array( __CLASS__, 'register_fields' ), 25 );

        add_action( 'ufsc_club_saved', array( __CLASS__, 'save_from_hook' ), 20, 3 );
        add_action( 'ufsc_club_updated', array( __CLASS__, 'save_from_hook' ), 20, 1 );
        add_action( 'ufsc_club_created', array( __CLASS__, 'save_from_hook' ), 20, 2 );

        add_action( 'admin_footer', array( __CLASS__, 'render_admin' ), 110 );
        // Le front Compte Club rend désormais le dossier FFST nativement dans
        // UFSC_Frontend_Shortcodes afin d'éviter toute injection dans un autre formulaire.
    }

    public static function ensure_schema() {
        if ( '1' === get_option( self::SCHEMA_OPTION, '' ) || ! class_exists( 'UFSC_SQL' ) ) { return; }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
        if ( '' === $table ) { return; }

        $known = function_exists( 'ufsc_table_columns' )
            ? (array) ufsc_table_columns( $table )
            : (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! $known ) { return; }

        $definitions = self::schema_definitions();
        $ok = true;
        foreach ( $definitions as $column => $definition ) {
            if ( in_array( $column, $known, true ) ) { continue; }
            $result = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( false === $result ) { $ok = false; }
        }

        if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) { ufsc_flush_table_columns_cache(); }
        if ( $ok ) { update_option( self::SCHEMA_OPTION, '1', false ); }
    }

    private static function schema_definitions() {
        $defs = array(
            'numero_affiliation_ffst'     => "varchar(100) NULL DEFAULT NULL",
            'date_agrement_js'            => "date NULL DEFAULT NULL",
            'numero_agrement_js'          => "varchar(120) NULL DEFAULT NULL",
            'adresse_salle'               => "varchar(255) NULL DEFAULT NULL",
            'complement_adresse_salle'    => "varchar(255) NULL DEFAULT NULL",
            'code_postal_salle'           => "varchar(20) NULL DEFAULT NULL",
            'ville_salle'                 => "varchar(120) NULL DEFAULT NULL",
            'disciplines_ffst'            => "text NULL",
            'codes_disciplines_ffst'      => "varchar(255) NULL DEFAULT NULL",
            'correspondant_nom'           => "varchar(120) NULL DEFAULT NULL",
            'correspondant_prenom'        => "varchar(120) NULL DEFAULT NULL",
            'correspondant_tel'           => "varchar(60) NULL DEFAULT NULL",
            'correspondant_email'         => "varchar(190) NULL DEFAULT NULL",
            'signataire_nom'              => "varchar(120) NULL DEFAULT NULL",
            'signataire_prenom'           => "varchar(120) NULL DEFAULT NULL",
            'signataire_qualite'          => "varchar(120) NULL DEFAULT NULL",
        );

        foreach ( self::$leader_prefixes as $prefix ) {
            if ( 'entraineur' === $prefix ) {
                $defs[ $prefix . '_adresse' ] = "varchar(255) NULL DEFAULT NULL";
            }
            $defs[ $prefix . '_complement_adresse' ] = "varchar(255) NULL DEFAULT NULL";
            $defs[ $prefix . '_code_postal' ] = "varchar(20) NULL DEFAULT NULL";
            $defs[ $prefix . '_ville' ] = "varchar(120) NULL DEFAULT NULL";
            $defs[ $prefix . '_pere_nom_prenom' ] = "varchar(255) NULL DEFAULT NULL";
            $defs[ $prefix . '_mere_nom_prenom' ] = "varchar(255) NULL DEFAULT NULL";
        }
        return $defs;
    }

    public static function register_fields( $fields ) {
        $fields = is_array( $fields ) ? $fields : array();
        foreach ( self::field_definitions() as $name => $definition ) {
            if ( ! isset( $fields[ $name ] ) ) { $fields[ $name ] = $definition; }
        }
        return $fields;
    }

    private static function field_definitions() {
        $fields = array(
            'numero_affiliation_ffst'  => array( 'N° affiliation FFST', 'text' ),
            'date_agrement_js'         => array( 'Date agrément Jeunesse et Sports', 'date' ),
            'numero_agrement_js'       => array( 'N° agrément Jeunesse et Sports', 'text' ),
            'adresse_salle'            => array( 'Salle – Adresse', 'text' ),
            'complement_adresse_salle' => array( 'Salle – Complément d’adresse', 'text' ),
            'code_postal_salle'        => array( 'Salle – Code postal', 'text' ),
            'ville_salle'              => array( 'Salle – Ville', 'text' ),
            'disciplines_ffst'         => array( 'Disciplines FFST pratiquées', 'text' ),
            'codes_disciplines_ffst'   => array( 'Codes disciplines FFST', 'text' ),
            'correspondant_nom'        => array( 'Correspondant FFST – Nom', 'text' ),
            'correspondant_prenom'     => array( 'Correspondant FFST – Prénom', 'text' ),
            'correspondant_tel'        => array( 'Correspondant FFST – Téléphone', 'text' ),
            'correspondant_email'      => array( 'Correspondant FFST – E-mail', 'text' ),
            'signataire_nom'           => array( 'Signataire – Nom', 'text' ),
            'signataire_prenom'        => array( 'Signataire – Prénom', 'text' ),
            'signataire_qualite'       => array( 'Signataire – Qualité', 'text' ),
        );

        $labels = array(
            'president' => 'Président',
            'secretaire' => 'Secrétaire',
            'tresorier' => 'Trésorier',
            'entraineur' => 'Entraîneur / instructeur',
        );
        foreach ( $labels as $prefix => $label ) {
            // L'adresse principale existe déjà pour les dirigeants historiques ;
            // la déclarer ici permet au même hook canonique de la sauvegarder sans
            // créer de stockage parallèle. Le filtre n'écrase jamais un champ existant.
            $fields[ $prefix . '_adresse' ] = array( $label . ' – Adresse', 'text' );
            $fields[ $prefix . '_complement_adresse' ] = array( $label . ' – Complément d’adresse', 'text' );
            $fields[ $prefix . '_code_postal' ] = array( $label . ' – Code postal', 'text' );
            $fields[ $prefix . '_ville' ] = array( $label . ' – Ville', 'text' );
            $fields[ $prefix . '_pere_nom_prenom' ] = array( $label . ' – Père (si naissance à l’étranger)', 'text' );
            $fields[ $prefix . '_mere_nom_prenom' ] = array( $label . ' – Mère (si naissance à l’étranger)', 'text' );
        }
        return $fields;
    }

    public static function save_from_hook( $club_id ) {
        $club_id = absint( $club_id );
        if ( ! $club_id || empty( $_POST ) || ! class_exists( 'UFSC_SQL' ) ) { return; }

        // Sécurité de contexte : ces champs ne doivent être écrits que lors d'une
        // véritable sauvegarde de club. Une licence ou un autre formulaire ne peut
        // donc jamais modifier les données FFST du club.
        $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
            ? sanitize_key( wp_unslash( $_POST['action'] ) )
            : '';
        if ( 'ufsc_save_club' !== $action ) { return; }
        if (
            ! isset( $_POST['ufsc_club_nonce'] ) ||
            is_array( $_POST['ufsc_club_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ufsc_club_nonce'] ) ), 'ufsc_save_club' )
        ) { return; }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
        if ( '' === $table ) { return; }

        $columns = function_exists( 'ufsc_table_columns' )
            ? (array) ufsc_table_columns( $table )
            : (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $data = array();
        $date_fields = array( 'date_agrement_js' );
        foreach ( array_keys( self::field_definitions() ) as $field ) {
            if ( ! in_array( $field, $columns, true ) || ! array_key_exists( $field, $_POST ) || is_array( $_POST[ $field ] ) ) { continue; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( in_array( $field, $date_fields, true ) && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) { continue; }
            $data[ $field ] = $value;
        }

        if ( $data ) {
            $wpdb->update( $table, $data, array( 'id' => $club_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
    }

    public static function render_admin() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-sql-clubs' !== $page || ! in_array( $action, array( 'new', 'edit', 'view' ), true ) ) { return; }
        $club_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        self::render_script( $club_id, 'view' === $action );
    }

    public static function render_front() {
        if ( is_admin() || ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) { return; }
        $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
        if ( ! $club_id ) { return; }
        self::render_script( $club_id, false );
    }

    private static function get_row( $club_id ) {
        if ( ! $club_id || ! class_exists( 'UFSC_SQL' ) ) { return array(); }
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
        if ( '' === $table ) { return array(); }
        return (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d LIMIT 1", $club_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function completeness( array $row ) {
        $requirements = array(
            'nom' => 'Nom du club',
            'adresse' => 'Adresse du siège',
            'code_postal' => 'Code postal du siège',
            'ville' => 'Ville du siège',
            'email' => 'E-mail du club',
            'telephone' => 'Téléphone du club',
            'num_declaration' => 'N° de déclaration / RNA',
            'date_declaration' => 'Date de déclaration',
            'adresse_salle' => 'Adresse de la salle',
            'code_postal_salle' => 'Code postal de la salle',
            'ville_salle' => 'Ville de la salle',
            'disciplines_ffst' => 'Discipline(s) FFST',
            'president_nom' => 'Nom du président',
            'president_prenom' => 'Prénom du président',
            'president_date_naissance' => 'Date de naissance du président',
            'president_adresse' => 'Adresse du président',
            'president_code_postal' => 'Code postal du président',
            'president_ville' => 'Ville du président',
            'president_email' => 'E-mail du président',
            'president_tel' => 'Téléphone du président',
            'secretaire_nom' => 'Nom du secrétaire',
            'secretaire_prenom' => 'Prénom du secrétaire',
            'secretaire_date_naissance' => 'Date de naissance du secrétaire',
            'secretaire_adresse' => 'Adresse du secrétaire',
            'secretaire_code_postal' => 'Code postal du secrétaire',
            'secretaire_ville' => 'Ville du secrétaire',
            'tresorier_nom' => 'Nom du trésorier',
            'tresorier_prenom' => 'Prénom du trésorier',
            'tresorier_date_naissance' => 'Date de naissance du trésorier',
            'tresorier_adresse' => 'Adresse du trésorier',
            'tresorier_code_postal' => 'Code postal du trésorier',
            'tresorier_ville' => 'Ville du trésorier',
        );

        $missing = array();
        foreach ( $requirements as $field => $label ) {
            $value = isset( $row[ $field ] ) ? trim( (string) $row[ $field ] ) : '';
            if ( '' === $value ) {
                if ( 'num_declaration' === $field && ! empty( $row['rna_number'] ) ) { continue; }
                $missing[ $field ] = $label;
            }
        }
        $total = count( $requirements );
        $done = $total - count( $missing );
        return array(
            'total' => $total,
            'done' => $done,
            'percent' => $total ? (int) round( ( $done / $total ) * 100 ) : 100,
            'missing' => $missing,
        );
    }

    private static function render_script( $club_id, $readonly ) {
        $row = self::get_row( $club_id );
        $payload = array();
        foreach ( array_keys( self::field_definitions() ) as $field ) {
            $payload[ $field ] = isset( $row[ $field ] ) ? (string) $row[ $field ] : '';
        }
        $status = self::completeness( $row );
        ?>
        <style>
            .ufsc-ffst-profile-box{margin:18px 0;padding:18px;border:1px solid #dcdcde;border-radius:10px;background:#fff;box-sizing:border-box;clear:both}
            .ufsc-ffst-profile-box h3{margin:0 0 6px;font-size:18px}.ufsc-ffst-profile-box p{margin:0 0 12px;color:#50575e}
            .ufsc-ffst-progress{height:9px;background:#e2e4e7;border-radius:999px;overflow:hidden;margin:10px 0 8px}.ufsc-ffst-progress span{display:block;height:100%;background:#2271b1;border-radius:999px}
            .ufsc-ffst-summary{font-weight:600;margin-bottom:12px!important}.ufsc-ffst-missing{margin:8px 0 0;padding-left:20px;columns:2;column-gap:28px}.ufsc-ffst-missing li{break-inside:avoid;margin:3px 0;color:#646970}
            .ufsc-ffst-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px;margin-top:14px}.ufsc-ffst-field{min-width:0}.ufsc-ffst-field label{display:block;font-weight:600;margin-bottom:5px}.ufsc-ffst-field input{width:100%;max-width:100%;box-sizing:border-box}
            .ufsc-ffst-field small{display:block;margin-top:4px;color:#646970}.ufsc-ffst-subtitle{grid-column:1/-1;margin:12px 0 0;padding-top:12px;border-top:1px solid #eee;font-size:15px;font-weight:700}
            .ufsc-ffst-foreign-only{display:none}.ufsc-ffst-foreign-only.is-visible{display:block}
            @media(max-width:782px){.ufsc-ffst-grid{grid-template-columns:1fr}.ufsc-ffst-missing{columns:1}}
        </style>
        <script>
        (function(){
            var values=<?php echo wp_json_encode( $payload ); ?>;
            var status=<?php echo wp_json_encode( $status ); ?>;
            var readonly=<?php echo $readonly ? 'true' : 'false'; ?>;
            var labels={president:'Président',secretaire:'Secrétaire',tresorier:'Trésorier',entraineur:'Entraîneur / instructeur'};

            function makeField(name,label,type,help,klass){
                var wrap=document.createElement('div');wrap.className='ufsc-ffst-field'+(klass?' '+klass:'');
                var lab=document.createElement('label');lab.htmlFor=name;lab.textContent=label;
                var input=document.createElement('input');input.id=name;input.name=name;input.type=type||'text';input.value=values[name]||'';input.autocomplete='off';
                if(readonly){input.readOnly=true;input.setAttribute('aria-readonly','true');}
                wrap.appendChild(lab);wrap.appendChild(input);
                if(help){var small=document.createElement('small');small.textContent=help;wrap.appendChild(small);}
                return wrap;
            }
            function subtitle(text){var el=document.createElement('div');el.className='ufsc-ffst-subtitle';el.textContent=text;return el;}
            function findForm(){
                var action=document.querySelector('form input[name="action"][value="ufsc_save_club"]');
                if(!action)return null;
                var form=action.closest('form');
                if(!form)return null;
                if(!form.querySelector('[name="club_id"]')&&!form.classList.contains('ufsc-club-form'))return null;
                return form;
            }
            function existingOrAppend(grid,name,label,type,help,klass){
                var existing=document.querySelector('[name="'+name+'"]');
                if(existing)return existing;
                var field=makeField(name,label,type,help,klass);grid.appendChild(field);return field.querySelector('input');
            }
            function foreignToggle(prefix){
                var country=document.querySelector('[name="'+prefix+'_pays_naissance"]');
                var nodes=document.querySelectorAll('.ufsc-ffst-foreign-'+prefix);
                function update(){
                    var value=country?String(country.value||'').trim().toLowerCase():'';
                    var foreign=value && value!=='france' && value!=='fr' && value!=='f';
                    nodes.forEach(function(node){node.classList.toggle('is-visible',!!foreign);});
                }
                if(country){country.addEventListener('input',update);country.addEventListener('change',update);}update();
            }
            function build(){
                var form=findForm();if(!form||form.querySelector('.ufsc-ffst-profile-box'))return false;
                var box=document.createElement('section');box.className='ufsc-ffst-profile-box';box.setAttribute('aria-label','Complément affiliation FFST');
                var h=document.createElement('h3');h.textContent='Dossier affiliation FFST';box.appendChild(h);
                var intro=document.createElement('p');intro.textContent='Ces informations complètent votre compte club et servent à préremplir les documents FFST. Elles ne bloquent pas l’utilisation du compte si elles sont encore incomplètes.';box.appendChild(intro);
                var progress=document.createElement('div');progress.className='ufsc-ffst-progress';progress.setAttribute('role','progressbar');progress.setAttribute('aria-valuemin','0');progress.setAttribute('aria-valuemax','100');progress.setAttribute('aria-valuenow',String(status.percent||0));var bar=document.createElement('span');bar.style.width=String(status.percent||0)+'%';progress.appendChild(bar);box.appendChild(progress);
                var summary=document.createElement('p');summary.className='ufsc-ffst-summary';summary.textContent='Complétude FFST : '+String(status.percent||0)+' % — '+String(status.done||0)+' / '+String(status.total||0)+' informations de référence.';box.appendChild(summary);
                if(status.missing&&Object.keys(status.missing).length){var details=document.createElement('details');var ds=document.createElement('summary');ds.textContent='Voir les informations encore à compléter ('+Object.keys(status.missing).length+')';details.appendChild(ds);var ul=document.createElement('ul');ul.className='ufsc-ffst-missing';Object.keys(status.missing).forEach(function(key){var li=document.createElement('li');li.textContent=status.missing[key];ul.appendChild(li);});details.appendChild(ul);box.appendChild(details);}

                var grid=document.createElement('div');grid.className='ufsc-ffst-grid';box.appendChild(grid);
                grid.appendChild(subtitle('Références et activité FFST'));
                existingOrAppend(grid,'numero_affiliation_ffst','N° affiliation FFST','text','À renseigner dès attribution par la FFST.');
                existingOrAppend(grid,'disciplines_ffst','Discipline(s) FFST pratiquée(s)','text','Séparer plusieurs disciplines par une virgule.');
                existingOrAppend(grid,'codes_disciplines_ffst','Code(s) discipline FFST','text','Codes correspondant aux disciplines du bordereau FFST.');
                existingOrAppend(grid,'numero_agrement_js','N° agrément Jeunesse et Sports','text','Laisser vide si le club ne dispose pas d’agrément.');
                existingOrAppend(grid,'date_agrement_js','Date agrément Jeunesse et Sports','date','Laisser vide si non applicable.');

                grid.appendChild(subtitle('Lieu principal d’entraînement'));
                existingOrAppend(grid,'adresse_salle','Adresse de la salle','text','Adresse utilisée sur le dossier FFST.');
                existingOrAppend(grid,'complement_adresse_salle','Complément d’adresse de la salle','text','Optionnel.');
                existingOrAppend(grid,'code_postal_salle','Code postal de la salle','text','');
                existingOrAppend(grid,'ville_salle','Ville de la salle','text','');

                grid.appendChild(subtitle('Correspondant et signataire FFST'));
                existingOrAppend(grid,'correspondant_nom','Correspondant – Nom','text','');
                existingOrAppend(grid,'correspondant_prenom','Correspondant – Prénom','text','');
                existingOrAppend(grid,'correspondant_tel','Correspondant – Téléphone','tel','');
                existingOrAppend(grid,'correspondant_email','Correspondant – E-mail','email','');
                existingOrAppend(grid,'signataire_nom','Signataire – Nom','text','');
                existingOrAppend(grid,'signataire_prenom','Signataire – Prénom','text','');
                existingOrAppend(grid,'signataire_qualite','Signataire – Qualité','text','Ex. Président, secrétaire général.');

                Object.keys(labels).forEach(function(prefix){
                    grid.appendChild(subtitle(labels[prefix]+' — adresse détaillée FFST'));
                    if(prefix==='entraineur'){existingOrAppend(grid,prefix+'_adresse',labels[prefix]+' – Adresse','text','');}
                    existingOrAppend(grid,prefix+'_complement_adresse',labels[prefix]+' – Complément d’adresse','text','Optionnel.');
                    existingOrAppend(grid,prefix+'_code_postal',labels[prefix]+' – Code postal','text','');
                    existingOrAppend(grid,prefix+'_ville',labels[prefix]+' – Ville','text','');
                    existingOrAppend(grid,prefix+'_pere_nom_prenom',labels[prefix]+' – Père : nom et prénom','text','Demandé par le formulaire FFST en cas de naissance à l’étranger.','ufsc-ffst-foreign-only ufsc-ffst-foreign-'+prefix);
                    existingOrAppend(grid,prefix+'_mere_nom_prenom',labels[prefix]+' – Mère : nom et prénom','text','Demandé par le formulaire FFST en cas de naissance à l’étranger.','ufsc-ffst-foreign-only ufsc-ffst-foreign-'+prefix);
                });

                var submit=form.querySelector('[type="submit"]');
                if(submit){var holder=submit.closest('p,.ufsc-actions,.form-actions,.submit')||submit;holder.parentNode.insertBefore(box,holder);}else{form.appendChild(box);}
                Object.keys(labels).forEach(foreignToggle);
                return true;
            }
            function boot(){if(build())return;var tries=0;var timer=setInterval(function(){tries++;if(build()||tries>30)clearInterval(timer);},150);}
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',boot);}else{boot();}
        })();
        </script>
        <?php
    }
}
