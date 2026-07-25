<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin-only manual affiliation workflow for payments or regularisations
 * handled outside WooCommerce.
 */
final class UFSC_Affiliation_Archive_Admin {

    /** @var bool */
    private static $registered = false;

    public static function init() {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        add_action( 'admin_menu', array( __CLASS__, 'register_page' ), 31 );
        add_action( 'admin_post_ufsc_add_manual_affiliation', array( __CLASS__, 'handle_submit' ) );
    }

    public static function register_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) ) {
            return;
        }

        add_submenu_page(
            'ufsc-dashboard',
            __( 'Ajouter une affiliation annuelle', 'ufsc-clubs' ),
            __( 'Ajouter une affiliation', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_GESTION_MANAGE,
            'ufsc-add-season-affiliation',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function handle_submit() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_add_manual_affiliation' );

        $club_id        = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season         = isset( $_POST['season'] ) ? self::normalize_season( wp_unslash( $_POST['season'] ) ) : '';
        $status         = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active';
        $payment_status = isset( $_POST['payment_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_status'] ) ) : 'paid';
        $number         = isset( $_POST['num_affiliation'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['num_affiliation'] ) ) ) : '';

        $allowed_statuses = array( 'draft', 'pending', 'active', 'suspended' );
        $allowed_payments = array( 'pending', 'paid', 'exempt' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'active';
        }
        if ( ! in_array( $payment_status, $allowed_payments, true ) ) {
            $payment_status = 'paid';
        }

        if ( function_exists( 'mb_substr' ) ) {
            $number = mb_substr( $number, 0, 191 );
        } else {
            $number = substr( $number, 0, 191 );
        }

        $redirect = add_query_arg( 'page', 'ufsc-add-season-affiliation', admin_url( 'admin.php' ) );
        if ( $club_id <= 0 || '' === $season || ! class_exists( 'UFSC_Season_Archive_Manager' ) ) {
            wp_safe_redirect( add_query_arg( 'ufsc_manual_affiliation', 'invalid', $redirect ) );
            exit;
        }

        $saved = UFSC_Season_Archive_Manager::upsert_affiliation(
            $club_id,
            $season,
            array(
                'status'         => $status,
                'payment_status' => $payment_status,
                'wc_order_id'    => 0,
            )
        );

        if ( ! $saved ) {
            wp_safe_redirect( add_query_arg( 'ufsc_manual_affiliation', 'error', $redirect ) );
            exit;
        }

        if ( '' !== $number ) {
            global $wpdb;
            $table = UFSC_Season_Archive_Manager::get_affiliations_table();
            $wpdb->update(
                $table,
                array(
                    'num_affiliation' => $number,
                    'updated_at'      => current_time( 'mysql' ),
                ),
                array(
                    'club_id' => $club_id,
                    'season'  => $season,
                ),
                array( '%s', '%s' ),
                array( '%d', '%s' )
            );
        }

        do_action( 'ufsc_manual_affiliation_saved', $club_id, $season, get_current_user_id() );
        $archives_url = add_query_arg(
            array(
                'page'   => 'ufsc-seasons-archives',
                'season' => $season,
                'ufsc_manual_affiliation' => 'success',
            ),
            admin_url( 'admin.php' )
        );
        wp_safe_redirect( $archives_url );
        exit;
    }

    public static function render_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        global $wpdb;
        $clubs = array();
        if ( class_exists( 'UFSC_SQL' ) ) {
            $settings = UFSC_SQL::get_settings();
            $table    = isset( $settings['table_clubs'] ) ? (string) $settings['table_clubs'] : '';
            if ( $table ) {
                $clubs = $wpdb->get_results( "SELECT id, nom FROM `{$table}` ORDER BY nom ASC", ARRAY_A );
            }
        }

        $default_season = class_exists( 'UFSC_Season_Service' )
            ? UFSC_Season_Service::get_current_season()
            : '';

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Ajouter une affiliation annuelle', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'À utiliser pour un règlement ou une régularisation traités hors WooCommerce. Une affiliation déjà existante pour le même club et la même saison sera mise à jour, jamais dupliquée.', 'ufsc-clubs' ) . '</p>';

        if ( isset( $_GET['ufsc_manual_affiliation'] ) ) {
            $notice = sanitize_key( wp_unslash( $_GET['ufsc_manual_affiliation'] ) );
            if ( 'invalid' === $notice ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'Le club ou la saison est invalide.', 'ufsc-clubs' ) . '</p></div>';
            } elseif ( 'error' === $notice ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'L’affiliation annuelle n’a pas pu être enregistrée.', 'ufsc-clubs' ) . '</p></div>';
            }
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="max-width:760px">';
        wp_nonce_field( 'ufsc_add_manual_affiliation' );
        echo '<input type="hidden" name="action" value="ufsc_add_manual_affiliation">';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="ufsc-manual-club">' . esc_html__( 'Club', 'ufsc-clubs' ) . '</label></th><td>';
        echo '<select id="ufsc-manual-club" name="club_id" required style="min-width:360px">';
        echo '<option value="">' . esc_html__( 'Sélectionner un club', 'ufsc-clubs' ) . '</option>';
        foreach ( (array) $clubs as $club ) {
            echo '<option value="' . esc_attr( absint( $club['id'] ) ) . '">' . esc_html( $club['nom'] ) . ' (#' . esc_html( absint( $club['id'] ) ) . ')</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="ufsc-manual-season">' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</label></th><td>';
        echo '<input id="ufsc-manual-season" type="text" name="season" value="' . esc_attr( $default_season ) . '" pattern="[0-9]{4}[-/][0-9]{4}" placeholder="2026-2027" required>';
        echo '<p class="description">' . esc_html__( 'Format accepté : 2026-2027 ou 2026/2027.', 'ufsc-clubs' ) . '</p></td></tr>';

        echo '<tr><th><label for="ufsc-manual-status">' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</label></th><td>';
        echo '<select id="ufsc-manual-status" name="status"><option value="active">' . esc_html__( 'Active', 'ufsc-clubs' ) . '</option><option value="pending">' . esc_html__( 'En attente', 'ufsc-clubs' ) . '</option><option value="draft">' . esc_html__( 'Brouillon', 'ufsc-clubs' ) . '</option><option value="suspended">' . esc_html__( 'Suspendue', 'ufsc-clubs' ) . '</option></select></td></tr>';

        echo '<tr><th><label for="ufsc-manual-payment">' . esc_html__( 'Paiement', 'ufsc-clubs' ) . '</label></th><td>';
        echo '<select id="ufsc-manual-payment" name="payment_status"><option value="paid">' . esc_html__( 'Payé', 'ufsc-clubs' ) . '</option><option value="pending">' . esc_html__( 'En attente', 'ufsc-clubs' ) . '</option><option value="exempt">' . esc_html__( 'Exonéré', 'ufsc-clubs' ) . '</option></select></td></tr>';

        echo '<tr><th><label for="ufsc-manual-number">' . esc_html__( 'N° affiliation ASPTT', 'ufsc-clubs' ) . '</label></th><td>';
        echo '<input id="ufsc-manual-number" type="text" name="num_affiliation" maxlength="191">';
        echo '<p class="description">' . esc_html__( 'Facultatif : il peut être renseigné ultérieurement depuis les archives.', 'ufsc-clubs' ) . '</p></td></tr>';

        echo '</tbody></table>';
        submit_button( __( 'Enregistrer l’affiliation annuelle', 'ufsc-clubs' ) );
        echo '</form></div>';
    }

    private static function normalize_season( $season ) {
        $season = trim( str_replace( '/', '-', sanitize_text_field( (string) $season ) ) );
        if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
            return '';
        }
        return ( (int) $matches[2] === (int) $matches[1] + 1 ) ? $season : '';
    }
}

UFSC_Affiliation_Archive_Admin::init();
