<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nominative WooCommerce payment trace for UFSC licences.
 *
 * One row is displayed per licence/person. This page is read-only and does not
 * modify licence, club or WooCommerce data.
 */
class UFSC_Licence_Payment_Trace_Admin {
    private static $registered = false;

    public static function init() {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 35 );
    }

    public static function register_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) ) {
            return;
        }

        add_submenu_page(
            'ufsc-dashboard',
            __( 'Règlements des licences', 'ufsc-clubs' ),
            __( 'Règlements licences', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_LICENCES_READ,
            'ufsc-licence-payment-trace',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_LICENCES_READ ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return;
        }

        $settings       = UFSC_SQL::get_settings();
        $licences_table = isset( $settings['table_licences'] ) ? (string) $settings['table_licences'] : '';
        $clubs_table    = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
        if ( '' === $licences_table ) {
            return;
        }

        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $licences_table ) : array();
        $season_column = self::first_column( $columns, array( 'paid_season', 'season', 'saison' ) );
        $order_column  = self::first_column( $columns, array( 'order_id', 'wc_order_id' ) );
        $item_column   = self::first_column( $columns, array( 'order_item_id', 'wc_order_item_id' ) );
        $lineage_column = self::first_column( $columns, array( 'previous_licence_id', 'renewed_from_licence_id' ) );

        $season_filter = isset( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : '';
        $payment_filter = isset( $_GET['payment'] ) ? sanitize_key( wp_unslash( $_GET['payment'] ) ) : '';
        $where = array();
        if ( $season_filter && $season_column ) {
            $where[] = $wpdb->prepare( "REPLACE(l.`{$season_column}`, '/', '-') = %s", str_replace( '/', '-', $season_filter ) );
        }
        if ( $payment_filter && in_array( 'payment_status', $columns, true ) ) {
            $where[] = $wpdb->prepare( 'l.payment_status = %s', $payment_filter );
        }
        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $club_join = '';
        $club_select = "'' AS club_name";
        if ( $clubs_table ) {
            $club_join   = "LEFT JOIN `{$clubs_table}` c ON c.id = l.club_id";
            $club_select = 'c.nom AS club_name';
        }

        $rows = $wpdb->get_results(
            "SELECT l.*, {$club_select}
             FROM `{$licences_table}` l
             {$club_join}
             {$where_sql}
             ORDER BY l.id DESC
             LIMIT 500",
            ARRAY_A
        );

        $seasons = array();
        if ( $season_column ) {
            $seasons = $wpdb->get_col( "SELECT DISTINCT `{$season_column}` FROM `{$licences_table}` WHERE `{$season_column}` IS NOT NULL AND `{$season_column}` <> '' ORDER BY `{$season_column}` DESC" );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Traçabilité nominative des règlements', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Une ligne correspond à une personne et à une licence. La commande et la ligne WooCommerce permettent de rapprocher chaque règlement.', 'ufsc-clubs' ) . '</p>';

        echo '<form method="get" style="margin:16px 0">';
        echo '<input type="hidden" name="page" value="ufsc-licence-payment-trace">';
        echo '<select name="season"><option value="">' . esc_html__( 'Toutes les saisons', 'ufsc-clubs' ) . '</option>';
        foreach ( (array) $seasons as $season ) {
            echo '<option value="' . esc_attr( $season ) . '"' . selected( $season_filter, $season, false ) . '>' . esc_html( $season ) . '</option>';
        }
        echo '</select> ';
        echo '<select name="payment">';
        echo '<option value="">' . esc_html__( 'Tous les paiements', 'ufsc-clubs' ) . '</option>';
        foreach ( array( 'paid' => 'Payé', 'pending' => 'En attente', 'unpaid' => 'Non payé', 'exempt' => 'Exonéré' ) as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $payment_filter, $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'secondary', '', false );
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Licencié', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Type', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Paiement licence', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Commande WooCommerce', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Ligne nominative', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Statut WooCommerce', 'ufsc-clubs' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $rows ) ) {
            echo '<tr><td colspan="8">' . esc_html__( 'Aucune licence trouvée.', 'ufsc-clubs' ) . '</td></tr>';
        }

        foreach ( (array) $rows as $row ) {
            $licence_id = absint( isset( $row['id'] ) ? $row['id'] : 0 );
            $first_name = isset( $row['prenom'] ) ? (string) $row['prenom'] : '';
            $last_name  = isset( $row['nom_licence'] ) ? (string) $row['nom_licence'] : ( isset( $row['nom'] ) ? (string) $row['nom'] : '' );
            $identity   = trim( $first_name . ' ' . $last_name );
            $season     = $season_column && isset( $row[ $season_column ] ) ? (string) $row[ $season_column ] : '';
            $order_id   = $order_column && isset( $row[ $order_column ] ) ? absint( $row[ $order_column ] ) : 0;
            $item_id    = $item_column && isset( $row[ $item_column ] ) ? absint( $row[ $item_column ] ) : 0;
            $is_renewal = $lineage_column && ! empty( $row[ $lineage_column ] );
            $payment    = isset( $row['payment_status'] ) ? (string) $row['payment_status'] : '';

            $order = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
            $order_status = $order ? wc_get_order_status_name( $order->get_status() ) : '';
            $order_url = $order ? $order->get_edit_order_url() : '';

            $item_label = '';
            if ( $order && $item_id ) {
                $item = $order->get_item( $item_id );
                if ( $item ) {
                    $meta_identity = trim( (string) $item->get_meta( 'ufsc_licence_name', true ) );
                    $item_label = $meta_identity ? $meta_identity : $item->get_name();
                }
            }

            $licence_url = add_query_arg( array( 'page' => 'ufsc-sql-licences', 'action' => 'view', 'id' => $licence_id ), admin_url( 'admin.php' ) );
            echo '<tr>';
            echo '<td><a href="' . esc_url( $licence_url ) . '"><strong>' . esc_html( $identity ?: sprintf( __( 'Licence #%d', 'ufsc-clubs' ), $licence_id ) ) . '</strong></a><br><small>#' . esc_html( $licence_id ) . '</small></td>';
            echo '<td>' . esc_html( ! empty( $row['club_name'] ) ? $row['club_name'] : sprintf( __( 'Club #%d', 'ufsc-clubs' ), absint( isset( $row['club_id'] ) ? $row['club_id'] : 0 ) ) ) . '</td>';
            echo '<td>' . esc_html( $is_renewal ? __( 'Renouvellement', 'ufsc-clubs' ) : __( 'Nouvelle licence', 'ufsc-clubs' ) ) . '</td>';
            echo '<td>' . esc_html( $season ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $payment ?: '—' ) . '</td>';
            echo '<td>' . ( $order_url ? '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $order_id ) . '</a>' : '<span style="color:#b32d2e">' . esc_html__( 'Non reliée', 'ufsc-clubs' ) . '</span>' ) . '</td>';
            echo '<td>' . ( $item_id ? '#' . esc_html( $item_id ) . ( $item_label ? '<br><small>' . esc_html( $item_label ) . '</small>' : '' ) : '<span style="color:#b32d2e">' . esc_html__( 'Non identifiée', 'ufsc-clubs' ) . '</span>' ) . '</td>';
            echo '<td>' . esc_html( $order_status ?: '—' ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__( 'Une licence payée sans commande ou sans ligne WooCommerce nominative doit être régularisée avant validation définitive.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
    }

    private static function first_column( $columns, $candidates ) {
        foreach ( $candidates as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                return $candidate;
            }
        }
        return '';
    }
}

UFSC_Licence_Payment_Trace_Admin::init();
