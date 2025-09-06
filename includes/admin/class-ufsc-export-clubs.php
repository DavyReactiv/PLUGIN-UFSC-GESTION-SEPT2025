<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Export_Clubs extends UFSC_Export_Base {
    public static function init() {
        add_action( 'admin_post_ufsc_export_clubs', array( __CLASS__, 'handle_export' ) );
    }

    private static function allowed_columns() {
        return array(
            'ID'                => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id',
            'Nom'               => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'nom' ) : 'nom',
            'Email'             => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'email' ) : 'email',
            'Téléphone'         => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'telephone' ) : 'telephone',
            'Région'            => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'region' ) : 'region',
            'Ville'             => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'ville' ) : 'ville',
            'Code postal'       => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'code_postal' ) : 'code_postal',
            'Adresse'           => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'adresse' ) : 'adresse',
            'Statut'            => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'statut' ) : 'statut',
            'Validé'            => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'validated' ) : 'validated',
            'Date création'     => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'date_creation' ) : 'date_creation',
            'Responsable ID'    => function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'responsable_id' ) : 'responsable_id',
        );
    }

    public static function render_form() {
        $cols = self::allowed_columns();
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_export_clubs' );
        echo '<input type="hidden" name="action" value="ufsc_export_clubs" />';

        echo '<p>'; // filters
        echo '<label>' . esc_html__( 'Région', 'ufsc-clubs' ) . ' <input type="text" name="region" /></label> ';
        echo '<label>' . esc_html__( 'Statut', 'ufsc-clubs' ) . ' <input type="text" name="statut" /></label> ';
        echo '<label>' . esc_html__( 'Paid', 'ufsc-clubs' ) . ' <select name="paid"><option value="">--</option><option value="1">1</option><option value="0">0</option></select></label>';
        echo '</p>';

        echo '<div class="ufsc-export-cols" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">';
        foreach ( $cols as $label => $col ) {
            echo '<label><input type="checkbox" name="columns[]" value="' . esc_attr( $label ) . '" checked> ' . esc_html( $label ) . '</label>';
        }
        echo '</div>';
        submit_button( __( 'Exporter CSV', 'ufsc-clubs' ) );
        echo '</form>';
    }

    public static function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accès refusé' );
        }
        check_admin_referer( 'ufsc_export_clubs' );
        set_time_limit( 0 );

        $allowed    = self::allowed_columns();
        $selected   = isset( $_POST['columns'] ) ? array_map( 'sanitize_text_field', (array) $_POST['columns'] ) : array();
        $mapped     = array();
        foreach ( $selected as $label ) {
            if ( isset( $allowed[ $label ] ) ) {
                $mapped[ $label ] = $allowed[ $label ];
            }
        }
        if ( empty( $mapped ) ) {
            wp_die( __( 'Aucune colonne sélectionnée', 'ufsc-clubs' ) );
        }
        $headers = array_keys( $mapped );
        $cols    = array_values( $mapped );
        global $wpdb;
        $s     = UFSC_SQL::get_settings();
        $table = $s['table_clubs'];
        $where = array();
        $params = array();
        if ( isset( $_POST['region'] ) && $_POST['region'] !== '' ) {
            $where[]  = 'region = %s';
            $params[] = sanitize_text_field( $_POST['region'] );
        }
        if ( isset( $_POST['statut'] ) && $_POST['statut'] !== '' ) {
            $where[]  = 'statut = %s';
            $params[] = sanitize_text_field( $_POST['statut'] );
        }
        if ( isset( $_POST['paid'] ) && $_POST['paid'] !== '' ) {
            $where[]  = 'paid = %d';
            $params[] = (int) $_POST['paid'];
        }
        $sql = 'SELECT ' . implode( ',', array_map( function ( $c ) {
            return "`$c`";
        }, $cols ) ) . " FROM `$table`";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }
        $rows = $wpdb->get_results( $sql, ARRAY_A );

        $csv_rows = array();
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $csv_rows[] = array_map( fn( $c ) => $r[ $c ] ?? '', $cols );
            }
        }

        nocache_headers();
        $filename = 'clubs-' . current_time( 'Ymd' ) . '.csv';
        self::output_csv( $filename, $headers, $csv_rows );
        exit;

    }
}
