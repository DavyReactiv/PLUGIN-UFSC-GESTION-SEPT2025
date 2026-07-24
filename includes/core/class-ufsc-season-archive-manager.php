<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Annual affiliation archive helpers.
 *
 * Schema ownership remains in UFSC_DB_Migrations. This class only reads and
 * writes annual affiliation rows and never changes the existing clubs or
 * licences tables used by UFSC Gestion and Licence & Compétition.
 */
class UFSC_Season_Archive_Manager {

    /** @var bool */
    private static $hooks_registered = false;

    /**
     * Return the annual affiliations table name.
     *
     * @return string
     */
    public static function get_affiliations_table() {
        global $wpdb;
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    /**
     * Ensure archive storage exists and register runtime hooks once.
     *
     * @return void
     */
    public static function maybe_migrate() {
        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'ensure_season_archive_tables' ) ) {
            UFSC_DB_Migrations::ensure_season_archive_tables();
        }

        self::register_hooks();
    }

    /**
     * Register WooCommerce persistence and the read-only admin page.
     *
     * @return void
     */
    private static function register_hooks() {
        if ( self::$hooks_registered ) {
            return;
        }
        self::$hooks_registered = true;

        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 30 );
    }

    /**
     * Persist paid affiliation renewals in the annual table.
     *
     * The unique (club_id, season) key makes this idempotent across the three
     * WooCommerce payment/status hooks. Existing ASPTT numbers are preserved.
     *
     * @param int $order_id WooCommerce order ID.
     * @return void
     */
    public static function process_paid_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order = wc_get_order( absint( $order_id ) );
        if ( ! $order || ! is_a( $order, 'WC_Order' ) || ! $order->is_paid() ) {
            return;
        }

        foreach ( $order->get_items() as $item ) {
            $action = (string) $item->get_meta( 'ufsc_action', true );
            if ( '' === $action ) {
                $action = (string) $item->get_meta( '_ufsc_action', true );
            }
            if ( 'renew_affiliation' !== $action ) {
                continue;
            }

            $club_id = absint( $item->get_meta( 'ufsc_club_id', true ) );
            if ( ! $club_id ) {
                $club_id = absint( $item->get_meta( '_ufsc_club_id', true ) );
            }

            $season = (string) $item->get_meta( 'ufsc_target_season', true );
            if ( '' === $season ) {
                $season = (string) $item->get_meta( '_ufsc_target_season', true );
            }
            $season = self::normalize_season( $season );

            if ( $club_id <= 0 || '' === $season ) {
                self::log_error( sprintf( 'Affiliation annuelle non enregistrée pour la commande %d : club ou saison invalide.', $order->get_id() ) );
                continue;
            }

            self::upsert_affiliation(
                $club_id,
                $season,
                array(
                    'status'         => 'active',
                    'payment_status' => 'paid',
                    'wc_order_id'    => (int) $order->get_id(),
                )
            );
        }
    }

    /**
     * Insert or update one annual affiliation without overwriting its ASPTT number.
     *
     * @param int    $club_id Club ID.
     * @param string $season Season YYYY-YYYY.
     * @param array  $data Optional status/payment/order data.
     * @return bool
     */
    public static function upsert_affiliation( $club_id, $season, $data = array() ) {
        global $wpdb;

        $club_id = absint( $club_id );
        $season  = self::normalize_season( $season );
        if ( $club_id <= 0 || '' === $season ) {
            return false;
        }

        $table          = self::get_affiliations_table();
        $status         = sanitize_key( isset( $data['status'] ) ? $data['status'] : 'active' );
        $payment_status = sanitize_key( isset( $data['payment_status'] ) ? $data['payment_status'] : 'paid' );
        $wc_order_id    = absint( isset( $data['wc_order_id'] ) ? $data['wc_order_id'] : 0 );
        $now            = current_time( 'mysql' );

        $sql = $wpdb->prepare(
            "INSERT INTO `{$table}`
                (club_id, season, status, payment_status, wc_order_id, created_at, updated_at)
             VALUES (%d, %s, %s, %s, NULLIF(%d, 0), %s, %s)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                payment_status = VALUES(payment_status),
                wc_order_id = VALUES(wc_order_id),
                updated_at = VALUES(updated_at)",
            $club_id,
            $season,
            $status,
            $payment_status,
            $wc_order_id,
            $now,
            $now
        );

        $result = $wpdb->query( $sql );
        if ( false === $result ) {
            self::log_error( sprintf( 'Échec archivage affiliation club %d saison %s : %s', $club_id, $season, $wpdb->last_error ) );
            return false;
        }

        do_action( 'ufsc_affiliation_season_saved', $club_id, $season, $wc_order_id );
        return true;
    }

    /**
     * Register the archives page under UFSC Gestion.
     *
     * @return void
     */
    public static function register_admin_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) ) {
            return;
        }

        add_submenu_page(
            'ufsc-dashboard',
            __( 'Affiliations par saison', 'ufsc-clubs' ),
            __( 'Saisons & archives', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_GESTION_READ,
            'ufsc-seasons-archives',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    /**
     * Render annual affiliations without changing historical data.
     *
     * @return void
     */
    public static function render_admin_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_READ ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        global $wpdb;

        $table  = self::get_affiliations_table();
        $season = isset( $_GET['season'] ) ? self::normalize_season( wp_unslash( $_GET['season'] ) ) : '';
        $where  = '';
        if ( '' !== $season ) {
            $where = $wpdb->prepare( 'WHERE a.season = %s', $season );
        }

        $clubs_table = '';
        if ( class_exists( 'UFSC_SQL' ) ) {
            $settings    = UFSC_SQL::get_settings();
            $clubs_table = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
        }

        $join       = '';
        $club_name  = "'' AS club_name";
        if ( $clubs_table ) {
            $join      = "LEFT JOIN `{$clubs_table}` c ON c.id = a.club_id";
            $club_name = 'c.nom AS club_name';
        }

        $rows = $wpdb->get_results(
            "SELECT a.*, {$club_name}
             FROM `{$table}` a
             {$join}
             {$where}
             ORDER BY a.season DESC, club_name ASC, a.club_id ASC",
            ARRAY_A
        );
        $seasons = $wpdb->get_col( "SELECT DISTINCT season FROM `{$table}` ORDER BY season DESC" );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Saisons et archives UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Affiliations annuelles enregistrées après paiement. Les clubs et leurs identifiants historiques restent inchangés.', 'ufsc-clubs' ) . '</p>';

        echo '<form method="get" style="margin:16px 0">';
        echo '<input type="hidden" name="page" value="ufsc-seasons-archives">';
        echo '<label for="ufsc-season-filter"><strong>' . esc_html__( 'Saison :', 'ufsc-clubs' ) . '</strong></label> ';
        echo '<select id="ufsc-season-filter" name="season">';
        echo '<option value="">' . esc_html__( 'Toutes les saisons', 'ufsc-clubs' ) . '</option>';
        foreach ( (array) $seasons as $available_season ) {
            echo '<option value="' . esc_attr( $available_season ) . '"' . selected( $season, $available_season, false ) . '>' . esc_html( $available_season ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'secondary', '', false );
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Paiement', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'N° affiliation ASPTT', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Commande', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Mise à jour', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';

        if ( empty( $rows ) ) {
            echo '<tr><td colspan="7">' . esc_html__( 'Aucune affiliation annuelle enregistrée.', 'ufsc-clubs' ) . '</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $club_label = ! empty( $row['club_name'] ) ? $row['club_name'] : sprintf( __( 'Club #%d', 'ufsc-clubs' ), (int) $row['club_id'] );
                $club_url   = add_query_arg( array( 'page' => 'ufsc-clubs', 'action' => 'view', 'id' => (int) $row['club_id'] ), admin_url( 'admin.php' ) );
                $order_url  = ! empty( $row['wc_order_id'] ) && function_exists( 'wc_get_order' ) ? admin_url( 'post.php?post=' . absint( $row['wc_order_id'] ) . '&action=edit' ) : '';

                echo '<tr>';
                echo '<td><strong>' . esc_html( $row['season'] ) . '</strong></td>';
                echo '<td><a href="' . esc_url( $club_url ) . '">' . esc_html( $club_label ) . '</a></td>';
                echo '<td>' . esc_html( $row['status'] ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $row['payment_status'] ?: '—' ) . '</td>';
                echo '<td>' . esc_html( $row['num_affiliation'] ?: __( 'À renseigner', 'ufsc-clubs' ) ) . '</td>';
                echo '<td>' . ( $order_url ? '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $row['wc_order_id'] ) . '</a>' : '—' ) . '</td>';
                echo '<td>' . esc_html( $row['updated_at'] ? mysql2date( 'd/m/Y H:i', $row['updated_at'] ) : '—' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Licence lineage columns are owned by UFSC_DB_Migrations.
     *
     * @return string[] Existing lineage columns for compatibility reads.
     */
    public static function get_existing_licence_lineage_columns() {
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return array();
        }

        $table   = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        return array_values( array_intersect( array( 'previous_licence_id', 'renewed_from_licence_id' ), $columns ) );
    }

    /**
     * Normalize and validate a season label.
     *
     * @param string $season Raw season.
     * @return string
     */
    private static function normalize_season( $season ) {
        $season = trim( str_replace( '/', '-', sanitize_text_field( (string) $season ) ) );
        if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
            return '';
        }
        return ( (int) $matches[2] === (int) $matches[1] + 1 ) ? $season : '';
    }

    /**
     * Log without interrupting the paid order flow.
     *
     * @param string $message Error message.
     * @return void
     */
    private static function log_error( $message ) {
        if ( class_exists( 'UFSC_Audit_Logger' ) && method_exists( 'UFSC_Audit_Logger', 'log' ) ) {
            UFSC_Audit_Logger::log( $message );
            return;
        }
        error_log( '[UFSC affiliations seasons] ' . $message );
    }
}
