<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Champs FFST nécessaires aux dirigeants : date et lieu de naissance.
 * Migration strictement additive et idempotente.
 */
final class UFSC_FFST_Birthplace_Fields {
    const SCHEMA_OPTION = 'ufsc_ffst_birthplace_schema_v1';

    private static $leader_roles = array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'coach', 'educateur', 'enseignant' );
    private static $club_prefixes = array( 'president', 'secretaire', 'tresorier', 'entraineur' );

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'ensure_schema' ), 5 );
        add_filter( 'ufsc_club_fields', array( __CLASS__, 'register_club_fields' ), 20 );
        add_filter( 'ufsc_licence_fields', array( __CLASS__, 'register_licence_fields' ), 20 );

        add_action( 'ufsc_licence_created', array( __CLASS__, 'save_created_licence_fields' ), 10, 2 );
        add_action( 'ufsc_licence_updated', array( __CLASS__, 'save_updated_licence_fields' ), 10, 1 );

        // Les différents parcours club existants émettent l'un de ces hooks.
        add_action( 'ufsc_club_saved', array( __CLASS__, 'save_club_birthplace_from_saved' ), 10, 3 );
        add_action( 'ufsc_club_updated', array( __CLASS__, 'save_club_birthplace_from_updated' ), 10, 1 );
        add_action( 'ufsc_club_created', array( __CLASS__, 'save_club_birthplace_from_created' ), 10, 2 );

        // Ajout non destructif dans les formulaires admin existants, création ET modification.
        add_action( 'admin_footer', array( __CLASS__, 'render_admin_club_fields' ) );
        // Même bloc dans le compte club en front lorsque le formulaire expose les dirigeants.
        add_action( 'wp_footer', array( __CLASS__, 'render_front_club_fields' ), 30 );
    }

    public static function ensure_schema() {
        if ( '1' === get_option( self::SCHEMA_OPTION, '' ) ) { return; }
        if ( ! class_exists( 'UFSC_SQL' ) ) { return; }

        $settings = UFSC_SQL::get_settings();
        $clubs = isset( $settings['table_clubs'] ) ? $settings['table_clubs'] : '';
        $licences = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( ! $clubs || ! $licences ) { return; }

        $club_columns = self::columns( $clubs );
        $licence_columns = self::columns( $licences );
        if ( ! $club_columns || ! $licence_columns ) { return; }

        $club_additions = array(
            'president_ville_naissance'       => "varchar(120) NULL DEFAULT NULL",
            'president_departement_naissance' => "varchar(120) NULL DEFAULT NULL",
            'president_pays_naissance'        => "varchar(120) NULL DEFAULT NULL",
            'secretaire_ville_naissance'       => "varchar(120) NULL DEFAULT NULL",
            'secretaire_departement_naissance' => "varchar(120) NULL DEFAULT NULL",
            'secretaire_pays_naissance'        => "varchar(120) NULL DEFAULT NULL",
            'tresorier_ville_naissance'        => "varchar(120) NULL DEFAULT NULL",
            'tresorier_departement_naissance' => "varchar(120) NULL DEFAULT NULL",
            'tresorier_pays_naissance'        => "varchar(120) NULL DEFAULT NULL",
            'entraineur_date_naissance'       => "date NULL DEFAULT NULL",
            'entraineur_ville_naissance'       => "varchar(120) NULL DEFAULT NULL",
            'entraineur_departement_naissance' => "varchar(120) NULL DEFAULT NULL",
            'entraineur_pays_naissance'        => "varchar(120) NULL DEFAULT NULL",
        );
        $licence_additions = array(
            'ville_naissance'       => "varchar(120) NULL DEFAULT NULL",
            'departement_naissance' => "varchar(120) NULL DEFAULT NULL",
            'pays_naissance'        => "varchar(120) NULL DEFAULT NULL",
        );

        $ok = self::add_missing_columns( $clubs, $club_columns, $club_additions );
        $ok = self::add_missing_columns( $licences, $licence_columns, $licence_additions ) && $ok;
        if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) { ufsc_flush_table_columns_cache(); }
        if ( $ok ) { update_option( self::SCHEMA_OPTION, '1', false ); }
    }

    private static function columns( $table ) {
        global $wpdb;
        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $table );
            if ( $columns ) { return $columns; }
        }
        return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function add_missing_columns( $table, array $known, array $definitions ) {
        global $wpdb;
        $ok = true;
        foreach ( $definitions as $column => $definition ) {
            if ( in_array( $column, $known, true ) ) { continue; }
            $result = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( false === $result ) { $ok = false; }
        }
        return $ok;
    }

    public static function register_club_fields( $fields ) {
        $fields = is_array( $fields ) ? $fields : array();
        foreach ( array( 'president' => 'Président', 'secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier', 'entraineur' => 'Entraîneur / instructeur' ) as $prefix => $label ) {
            if ( ! isset( $fields[ $prefix . '_date_naissance' ] ) ) {
                $fields[ $prefix . '_date_naissance' ] = array( $label . ' – Date de naissance', 'date' );
            }
            $fields[ $prefix . '_ville_naissance' ] = array( $label . ' – Ville de naissance', 'text' );
            $fields[ $prefix . '_departement_naissance' ] = array( $label . ' – Département de naissance', 'text' );
            $fields[ $prefix . '_pays_naissance' ] = array( $label . ' – Pays de naissance', 'text' );
        }
        return $fields;
    }

    public static function register_licence_fields( $fields ) {
        $fields = is_array( $fields ) ? $fields : array();
        $fields['ville_naissance'] = array( 'Ville de naissance', 'text' );
        $fields['departement_naissance'] = array( 'Département de naissance', 'text' );
        $fields['pays_naissance'] = array( 'Pays de naissance', 'text' );
        return $fields;
    }

    public static function save_created_licence_fields( $licence_id, $club_id ) { self::save_licence_birthplace( absint( $licence_id ) ); }
    public static function save_updated_licence_fields( $club_id ) {
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! in_array( $action, array( 'ufsc_save_licence', 'ufsc_update_licence', 'ufsc_add_licence' ), true ) ) { return; }
        $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( $licence_id ) { self::save_licence_birthplace( $licence_id ); }
    }

    private static function save_licence_birthplace( $licence_id ) {
        if ( ! $licence_id || ! class_exists( 'UFSC_SQL' ) ) { return; }
        global $wpdb;
        $table = UFSC_SQL::get_settings()['table_licences'];
        $columns = self::columns( $table );
        $data = array();
        foreach ( array( 'ville_naissance', 'departement_naissance', 'pays_naissance' ) as $field ) {
            if ( in_array( $field, $columns, true ) && array_key_exists( $field, $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            }
        }
        if ( $data ) { $wpdb->update( $table, $data, array( 'id' => $licence_id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    public static function save_club_birthplace_from_saved( $club_id, $affiliation = null, $is_edit = null ) { self::save_club_birthplace( absint( $club_id ) ); }
    public static function save_club_birthplace_from_updated( $club_id ) { self::save_club_birthplace( absint( $club_id ) ); }
    public static function save_club_birthplace_from_created( $club_id, $club_data = array() ) { self::save_club_birthplace( absint( $club_id ) ); }

    private static function save_club_birthplace( $club_id ) {
        if ( ! $club_id || ! class_exists( 'UFSC_SQL' ) || empty( $_POST ) ) { return; }
        global $wpdb;
        $table = UFSC_SQL::get_settings()['table_clubs'];
        $columns = self::columns( $table );
        $data = array();
        foreach ( self::$club_prefixes as $prefix ) {
            foreach ( array( 'date_naissance', 'ville_naissance', 'departement_naissance', 'pays_naissance' ) as $suffix ) {
                $field = $prefix . '_' . $suffix;
                if ( ! in_array( $field, $columns, true ) || ! array_key_exists( $field, $_POST ) ) { continue; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( 'date_naissance' === $suffix && '' !== $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) { continue; }
                $data[ $field ] = $value;
            }
        }
        if ( $data ) { $wpdb->update( $table, $data, array( 'id' => $club_id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    private static function get_club_payload( $club_id ) {
        $payload = array();
        $row = array();
        if ( $club_id && class_exists( 'UFSC_SQL' ) ) {
            global $wpdb;
            $table = UFSC_SQL::get_settings()['table_clubs'];
            $row = (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d LIMIT 1", $club_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        foreach ( self::$club_prefixes as $prefix ) {
            $payload[ $prefix ] = array(
                'date' => (string) ( $row[ $prefix . '_date_naissance' ] ?? '' ),
                'ville' => (string) ( $row[ $prefix . '_ville_naissance' ] ?? '' ),
                'departement' => (string) ( $row[ $prefix . '_departement_naissance' ] ?? '' ),
                'pays' => (string) ( $row[ $prefix . '_pays_naissance' ] ?? '' ),
            );
        }
        return $payload;
    }

    private static function render_fields_script( array $payload ) {
        ?>
        <script>
        (function(){
            var data=<?php echo wp_json_encode( $payload ); ?>;
            function fieldWrap(input){return input.closest('.ufsc-field,.form-field,.field,.ufsc-admin-field,.ufsc-form-field,td')||input.parentNode;}
            function addField(after,name,label,value,type){
                if(document.querySelector('[name="'+name+'"]')) return after;
                var wrap=document.createElement('div');wrap.className='ufsc-field ufsc-ffst-birth-field';
                var lab=document.createElement('label');lab.setAttribute('for',name);lab.textContent=label;
                var inp=document.createElement('input');inp.type=type||'text';inp.id=name;inp.name=name;inp.value=value||'';inp.autocomplete='off';
                wrap.appendChild(lab);wrap.appendChild(inp);after.parentNode.insertBefore(wrap,after.nextSibling);return wrap;
            }
            function enhance(){
                ['president','secretaire','tresorier','entraineur'].forEach(function(prefix){
                    var anchor=document.querySelector('[name="'+prefix+'_date_naissance"]')||document.querySelector('[name="'+prefix+'_email"]')||document.querySelector('[name="'+prefix+'_nom"]');
                    if(!anchor)return;
                    var pos=fieldWrap(anchor),label=prefix==='entraineur'?'Entraîneur / instructeur':prefix.charAt(0).toUpperCase()+prefix.slice(1);
                    if(!document.querySelector('[name="'+prefix+'_date_naissance"]')) pos=addField(pos,prefix+'_date_naissance',label+' – Date de naissance',data[prefix].date,'date');
                    pos=addField(pos,prefix+'_ville_naissance',label+' – Ville de naissance',data[prefix].ville,'text');
                    pos=addField(pos,prefix+'_departement_naissance',label+' – Département de naissance',data[prefix].departement,'text');
                    addField(pos,prefix+'_pays_naissance',label+' – Pays de naissance',data[prefix].pays||'France','text');
                });
            }
            if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',enhance);}else{enhance();}
        })();
        </script>
        <?php
    }

    public static function render_admin_club_fields() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-sql-clubs' !== $page || ! in_array( $action, array( 'new', 'edit' ), true ) ) { return; }
        $club_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : ( isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        self::render_fields_script( self::get_club_payload( $club_id ) );
    }

    public static function render_front_club_fields() {
        if ( is_admin() || ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) { return; }
        $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
        if ( ! $club_id ) { return; }
        self::render_fields_script( self::get_club_payload( $club_id ) );
    }
}
