<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Renouvellement manuel d'une affiliation annuelle depuis l'administration.
 *
 * Cette action ne crée aucune commande WooCommerce et ne modifie pas les
 * saisons antérieures. Elle s'appuie sur la source canonique des affiliations
 * annuelles afin de conserver l'historique de validation administrateur.
 */
final class UFSC_Club_Manual_Renewal_Admin {
    const ACTION = 'ufsc_admin_manual_renew_club';

    public static function init() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
    }

    public static function action_url( $club_id, $season, $return_to = '' ) {
        $club_id = absint( $club_id );
        $season  = self::normalize_season( $season );
        if ( ! $club_id || ! $season ) { return ''; }

        $url = add_query_arg(
            array(
                'action'    => self::ACTION,
                'club_id'   => $club_id,
                'season'    => $season,
                'return_to' => $return_to,
            ),
            admin_url( 'admin-post.php' )
        );
        return wp_nonce_url( $url, self::ACTION . '_' . $club_id . '_' . $season );
    }

    public static function handle() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }

        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0;
        $season  = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) ? self::normalize_season( wp_unslash( $_GET['season'] ) ) : '';
        if ( ! $club_id || ! $season ) {
            wp_die( esc_html__( 'Club ou saison invalide.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }
        check_admin_referer( self::ACTION . '_' . $club_id . '_' . $season );

        if ( ! class_exists( 'UFSC_Season_Archive_Manager' ) ) {
            wp_die( esc_html__( 'Le gestionnaire des affiliations annuelles est indisponible.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }
        if ( class_exists( 'UFSC_Storage_Resolver' ) && ! UFSC_Storage_Resolver::club_exists( $club_id ) ) {
            wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ), '', array( 'response' => 404 ) );
        }

        $existing = UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season );
        $existing_status = $existing ? UFSC_Season_Archive_Manager::normalize_status( $existing->status ?? $existing->statut ?? '' ) : '';
        if ( in_array( $existing_status, array( 'active', 'validated' ), true ) ) {
            self::redirect_back( $club_id, $season, 'already_active' );
        }

        $values = array(
            'status'           => 'active',
            'payment_status'   => 'manual',
            'request_type'     => 'admin_manual',
            'decision_reason'  => __( 'Renouvellement manuel effectué par un administrateur UFSC.', 'ufsc-clubs' ),
            // Un ancien numéro d'affiliation ne doit jamais être repris automatiquement.
            'num_affiliation'  => $existing && ! empty( $existing->num_affiliation ) ? (string) $existing->num_affiliation : '',
            'wc_order_id'      => $existing ? absint( $existing->wc_order_id ?? 0 ) : 0,
            'requested_at'     => current_time( 'mysql' ),
        );

        $result = UFSC_Season_Archive_Manager::save_admin_affiliation( $club_id, $season, $values, get_current_user_id() );
        if ( is_wp_error( $result ) ) {
            self::redirect_back( $club_id, $season, 'error', $result->get_error_message() );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'club_manual_renewal', array(
                'club_id' => $club_id,
                'season'  => $season,
                'user_id' => get_current_user_id(),
            ) );
        }

        self::redirect_back( $club_id, $season, 'renewed' );
    }

    private static function redirect_back( $club_id, $season, $status, $message = '' ) {
        $return_to = isset( $_GET['return_to'] ) && ! is_array( $_GET['return_to'] ) ? wp_unslash( $_GET['return_to'] ) : '';
        $return_to = wp_validate_redirect( $return_to, '' );
        if ( ! $return_to ) {
            $return_to = add_query_arg(
                array( 'page' => 'ufsc-sql-clubs', 'season' => $season ),
                admin_url( 'admin.php' )
            );
        }
        $args = array(
            'ufsc_manual_renewal' => sanitize_key( $status ),
            'renewed_club_id'     => absint( $club_id ),
        );
        if ( $message ) { $args['ufsc_manual_renewal_message'] = sanitize_text_field( $message ); }
        wp_safe_redirect( add_query_arg( $args, $return_to ) );
        exit;
    }

    private static function normalize_season( $season ) {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::normalize_season( $season );
        }
        $season = trim( str_replace( '/', '-', sanitize_text_field( (string) $season ) ) );
        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
    }
}

UFSC_Club_Manual_Renewal_Admin::init();
