<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Champs FFST nécessaires aux dirigeants : date et lieu de naissance.
 *
 * Migration strictement additive et idempotente. Aucune donnée existante n'est
 * supprimée, renommée ou réécrite.
 */
final class UFSC_FFST_Birthplace_Fields {
    const SCHEMA_OPTION = 'ufsc_ffst_birthplace_schema_v1';

    private static $leader_roles = array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'coach', 'educateur', 'enseignant' );

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'ensure_schema' ), 5 );
        add_filter( 'ufsc_club_fields', array( __CLASS__, 'register_club_fields' ), 20 );
        add_filter( 'ufsc_licence_fields', array( __CLASS__, 'register_licence_fields' ), 20 );

        // Sauvegarde complémentaire des champs licence, sans modifier le flux panier/commande.
        add_action( 'ufsc_licence_created', array( __CLASS__, 'save_created_licence_fields' ), 10, 2 );
        add_action( 'ufsc_licence_updated', array( __CLASS__, 'save_updated_licence_fields' ), 10, 1 );

        // L'écran SQL des clubs a une liste de champs historique figée : on ajoute
        // visuellement les champs FFST sans remplacer le renderer existant.
        add_action( 'admin_footer', array( __CLASS__, 'render_admin_club_fields' ) );
    }

    public static function ensure_schema() {
        if ( '1' === get_option( self::SCHEMA_OPTION, '' ) ) { return; }
        if ( ! class_exists( 'UFSC_SQL' ) ) { return; }

        global $wpdb;
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
            'tresorier_ville_naissance'       => "varchar(120) NULL DEFAULT NULL",
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
        foreach ( array(
            'president'  => 'Président',
            'secretaire' => 'Secrétaire',
            'tresorier'  => 'Trésorier',
            'entraineur' => 'Entraîneur / instructeur',
        ) as $prefix => $label ) {
            if ( 'entraineur' === $prefix && ! isset( $fields[ $prefix . '_date_naissance' ] ) ) {
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

    public static function save_created_licence_fields( $licence_id, $club_id ) {
        self::save_licence_birthplace( absint( $licence_id ) );
    }

    public static function save_updated_licence_fields( $club_id ) {
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core handler already verified its nonce before emitting this hook.
        if ( ! in_array( $action, array( 'ufsc_save_licence', 'ufsc_update_licence', 'ufsc_add_licence' ), true ) ) { return; }
        $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( $licence_id ) { self::save_licence_birthplace( $licence_id ); }
    }

    private static function save_licence_birthplace( $licence_id ) {
        if ( ! $licence_id || ! class_exists( 'UFSC_SQL' ) ) { return; }
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! in_array( $action, array( 'ufsc_save_licence', 'ufsc_update_licence', 'ufsc_add_licence' ), true ) ) { return; }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_licences'];
        $columns = self::columns( $table );
        $data = array();
        foreach ( array( 'ville_naissance', 'departement_naissance', 'pays_naissance' ) as $field ) {
            if ( ! in_array( $field, $columns, true ) || ! array_key_exists( $field, $_POST ) ) { continue; } // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $data[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        if ( $data ) {
            $wpdb->update( $table, $data, array( 'id' => $licence_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        }
    }

    public static function render_admin_club_fields() {
        if ( ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $club_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : ( isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-sql-clubs' !== $page || 'edit' !== $action || ! $club_id || ! class_exists( 'UFSC_SQL' ) ) { return; }

        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d LIMIT 1", $club_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! is_array( $row ) ) { return; }

        $payload = array();
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur' ) as $prefix ) {
            $payload[ $prefix ] = array(
                'date' => isset( $row[ $prefix . '_date_naissance' ] ) ? (string) $row[ $prefix . '_date_naissance' ] : '',
                'ville' => isset( $row[ $prefix . '_ville_naissance' ] ) ? (string) $row[ $prefix . '_ville_naissance' ] : '',
                'departement' => isset( $row[ $prefix . '_departement_naissance' ] ) ? (string) $row[ $prefix . '_departement_naissance' ] : '',
                'pays' => isset( $row[ $prefix . '_pays_naissance' ] ) ? (string) $row[ $prefix . '_pays_naissance' ] : '',
            );
        }
        ?>
        <script>
        (function(){
            var data = <?php echo wp_json_encode( $payload ); ?>;
            function fieldWrap(input){ return input.closest('.ufsc-field, .form-field, .field, .ufsc-admin-field, td') || input.parentNode; }
            function addText(after, name, label, value, type){
                if (document.querySelector('[name="'+name+'"]')) return;
                var wrap=document.createElement('div'); wrap.className='ufsc-field ufsc-ffst-birth-field';
                var lab=document.createElement('label'); lab.setAttribute('for',name); lab.textContent=label;
                var inp=document.createElement('input'); inp.type=type||'text'; inp.id=name; inp.name=name; inp.value=value||'';
                wrap.appendChild(lab); wrap.appendChild(inp);
                after.parentNode.insertBefore(wrap, after.nextSibling);
                return wrap;
            }
            ['president','secretaire','tresorier','entraineur'].forEach(function(prefix){
                var anchor=document.querySelector('[name="'+prefix+'_date_naissance"]') || document.querySelector('[name="'+prefix+'_email"]');
                if(!anchor) return;
                var pos=fieldWrap(anchor);
                if(prefix==='entraineur' && !document.querySelector('[name="entraineur_date_naissance"]')) {
                    pos=addText(pos,'entraineur_date_naissance','Entraîneur / instructeur – Date de naissance',data[prefix].date,'date') || pos;
                }
                pos=addText(pos,prefix+'_ville_naissance','Ville de naissance',data[prefix].ville) || pos;
                pos=addText(pos,prefix+'_departement_naissance','Département de naissance',data[prefix].departement) || pos;
                addText(pos,prefix+'_pays_naissance','Pays de naissance',data[prefix].pays);
            });
        })();
        </script>
        <?php
    }
}
