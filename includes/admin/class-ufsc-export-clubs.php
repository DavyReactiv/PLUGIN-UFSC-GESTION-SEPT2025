<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Export_Clubs extends UFSC_Export_Base {
    public static function init() {
        add_action( 'admin_post_ufsc_export_clubs', array( __CLASS__, 'handle_export' ) );
    }

    private static function sensitive_columns() {
        return array( 'password', 'pass', 'secret', 'token', 'activation_key', 'user_pass' );
    }

    private static function get_columns() {
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $table = $s['table_clubs'];
        $cols = $wpdb->get_col( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        ) );
        if ( ! $cols ) {
            return array();
        }
        return array_values( array_diff( $cols, self::sensitive_columns() ) );
    }

    public static function render_form() {
        $cols = self::get_columns();
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'ufsc_export_clubs' );
        echo '<input type="hidden" name="action" value="ufsc_export_clubs" />';

        echo '<p>'; // filters
        echo '<label>' . esc_html__( 'Région', 'ufsc-clubs' ) . ' <input type="text" name="region" /></label> ';
        echo '<label>' . esc_html__( 'Statut', 'ufsc-clubs' ) . ' <input type="text" name="statut" /></label> ';
        echo '<label>' . esc_html__( 'Paid', 'ufsc-clubs' ) . ' <select name="paid"><option value="">--</option><option value="1">1</option><option value="0">0</option></select></label>';
        echo '</p>';

        echo '<div class="ufsc-export-cols" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;">';
        foreach ( $cols as $c ) {
            echo '<label><input type="checkbox" name="columns[]" value="' . esc_attr( $c ) . '" checked> ' . esc_html( $c ) . '</label>';
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

        $allowed_cols   = self::get_columns();
        $selected_cols  = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) $_POST['columns'] ) : array();
        $cols           = array_values( array_intersect( $allowed_cols, $selected_cols ) );
        if ( empty( $cols ) ) {
            wp_die( __( 'Aucune colonne sélectionnée', 'ufsc-clubs' ) );
        }
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
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        nocache_headers();
        self::output_csv( 'clubs.csv', $cols, $rows );
    }
}
