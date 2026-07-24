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

    public static function get_affiliations_table() {
        global $wpdb;
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    public static function maybe_migrate() {
        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'ensure_season_archive_tables' ) ) {
            UFSC_DB_Migrations::ensure_season_archive_tables();
        }
        self::register_hooks();
    }

    private static function register_hooks() {
        if ( self::$hooks_registered ) {
            return;
        }
        self::$hooks_registered = true;

        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_paid_order' ), 30 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 30 );
        add_action( 'admin_post_ufsc_update_affiliation_number', array( __CLASS__, 'handle_update_affiliation_number' ) );
    }

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

    public static function handle_update_affiliation_number() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        $row_id = isset( $_POST['affiliation_row_id'] ) ? absint( wp_unslash( $_POST['affiliation_row_id'] ) ) : 0;
        check_admin_referer( 'ufsc_update_affiliation_number_' . $row_id );

        $number = isset( $_POST['num_affiliation'] ) ? sanitize_text_field( wp_unslash( $_POST['num_affiliation'] ) ) : '';
        $number = trim( $number );
        if ( function_exists( 'mb_substr' ) ) {
            $number = mb_substr( $number, 0, 191 );
        } else {
            $number = substr( $number, 0, 191 );
        }

        $redirect = add_query_arg( 'page', 'ufsc-seasons-archives', admin_url( 'admin.php' ) );
        $season   = isset( $_POST['season_filter'] ) ? self::normalize_season( wp_unslash( $_POST['season_filter'] ) ) : '';
        if ( $season ) {
            $redirect = add_query_arg( 'season', $season, $redirect );
        }

        if ( $row_id <= 0 ) {
            wp_safe_redirect( add_query_arg( 'ufsc_affiliation_updated', 'invalid', $redirect ) );
            exit;
        }

        global $wpdb;
        $table  = self::get_affiliations_table();
        $result = $wpdb->update(
            $table,
            array(
                'num_affiliation' => '' === $number ? null : $number,
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( 'id' => $row_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $result ) {
            self::log_error( sprintf( 'Échec mise à jour numéro affiliation ligne %d : %s', $row_id, $wpdb->last_error ) );
            wp_safe_redirect( add_query_arg( 'ufsc_affiliation_updated', 'error', $redirect ) );
            exit;
        }

        do_action( 'ufsc_affiliation_number_updated', $row_id, $number, get_current_user_id() );
        wp_safe_redirect( add_query_arg( 'ufsc_affiliation_updated', 'success', $redirect ) );
        exit;
    }

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

        $join      = '';
        $club_name = "'' AS club_name";
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
        $seasons   = $wpdb->get_col( "SELECT DISTINCT season FROM `{$table}` ORDER BY season DESC" );
        $can_manage = current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Saisons et archives UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Affiliations annuelles enregistrées après paiement. Les clubs et leurs identifiants historiques restent inchangés.', 'ufsc-clubs' ) . '</p>';

        if ( isset( $_GET['ufsc_affiliation_updated'] ) ) {
            $notice = sanitize_key( wp_unslash( $_GET['ufsc_affiliation_updated'] ) );
            if ( 'success' === $notice ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Numéro d’affiliation ASPTT mis à jour.', 'ufsc-clubs' ) . '</p></div>';
            } elseif ( 'error' === $notice ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'La mise à jour du numéro d’affiliation a échoué.', 'ufsc-clubs' ) . '</p></div>';
            } elseif ( 'invalid' === $notice ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'Affiliation annuelle invalide.', 'ufsc-clubs' ) . '</p></div>';
            }
        }

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
                echo '<td>';
                if ( $can_manage ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;align-items:center">';
                    wp_nonce_field( 'ufsc_update_affiliation_number_' . absint( $row['id'] ) );
                    echo '<input type="hidden" name="action" value="ufsc_update_affiliation_number">';
                    echo '<input type="hidden" name="affiliation_row_id" value="' . esc_attr( absint( $row['id'] ) ) . '">';
                    echo '<input type="hidden" name="season_filter" value="' . esc_attr( $season ) . '">';
                    echo '<input type="text" name="num_affiliation" value="' . esc_attr( (string) $row['num_affiliation'] ) . '" maxlength="191" placeholder="' . esc_attr__( 'À renseigner', 'ufsc-clubs' ) . '">';
                    submit_button( __( 'Enregistrer', 'ufsc-clubs' ), 'small', '', false );
                    echo '</form>';
                } else {
                    echo esc_html( $row['num_affiliation'] ?: __( 'À renseigner', 'ufsc-clubs' ) );
                }
                echo '</td>';
                echo '<td>' . ( $order_url ? '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $row['wc_order_id'] ) . '</a>' : '—' ) . '</td>';
                echo '<td>' . esc_html( $row['updated_at'] ? mysql2date( 'd/m/Y H:i', $row['updated_at'] ) : '—' ) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public static function get_existing_licence_lineage_columns() {
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return array();
        }

        $table   = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        return array_values( array_intersect( array( 'previous_licence_id', 'renewed_from_licence_id' ), $columns ) );
    }

    private static function normalize_season( $season ) {
        $season = trim( str_replace( '/', '-', sanitize_text_field( (string) $season ) ) );
        if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
            return '';
        }
        return ( (int) $matches[2] === (int) $matches[1] + 1 ) ? $season : '';
    }

    private static function log_error( $message ) {
        if ( class_exists( 'UFSC_Audit_Logger' ) && method_exists( 'UFSC_Audit_Logger', 'log' ) ) {
            UFSC_Audit_Logger::log( $message );
            return;
        }
        error_log( '[UFSC affiliations seasons] ' . $message );
    }
}
