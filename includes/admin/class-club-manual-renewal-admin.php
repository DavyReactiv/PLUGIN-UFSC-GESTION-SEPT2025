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
        add_action( 'admin_notices', array( __CLASS__, 'render_panel' ) );
        add_action( 'admin_notices', array( __CLASS__, 'render_result_notice' ) );
    }

    public static function render_panel() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) { return; }
        $page   = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        $action = isset( $_GET['action'] ) && ! is_array( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        $club_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        if ( 'ufsc-sql-clubs' !== $page || 'edit' !== $action || ! $club_id ) { return; }
        if ( ! class_exists( 'UFSC_Season_Archive_Manager' ) ) { return; }
        if ( class_exists( 'UFSC_Storage_Resolver' ) && ! UFSC_Storage_Resolver::club_exists( $club_id ) ) { return; }

        $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
        $season = self::normalize_season( $season );
        if ( ! $season ) { return; }

        $existing = UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season );
        $status = $existing ? UFSC_Season_Archive_Manager::normalize_status( $existing->status ?? $existing->statut ?? '' ) : '';
        $is_active = in_array( $status, array( 'active', 'validated' ), true );

        echo '<div class="notice notice-info ufsc-manual-renewal" style="padding:14px 18px;border-left-color:#2271b1">';
        echo '<p style="margin:0 0 8px"><strong>' . esc_html__( 'Renouvellement manuel du club', 'ufsc-clubs' ) . '</strong> — ' . esc_html( $season ) . '</p>';
        if ( $is_active ) {
            echo '<p style="margin:0"><span style="color:#008a20;font-weight:700">✓ ' . esc_html__( 'Affiliation annuelle déjà active pour cette saison.', 'ufsc-clubs' ) . '</span></p>';
            echo '</div>';
            return;
        }

        echo '<p>' . esc_html__( 'Cette action active directement l’affiliation annuelle sans créer de commande WooCommerce. Les saisons précédentes restent intactes et l’administrateur est enregistré dans l’historique.', 'ufsc-clubs' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0 2px">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        echo '<input type="hidden" name="return_to" value="' . esc_url( self::current_url() ) . '">';
        wp_nonce_field( self::ACTION . '_' . $club_id . '_' . $season );
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'' . esc_js( sprintf( __( 'Confirmer le renouvellement manuel du club pour la saison %s ?', 'ufsc-clubs' ), $season ) ) . '\')">' . esc_html( sprintf( __( 'Renouveler manuellement pour %s', 'ufsc-clubs' ), $season ) ) . '</button>';
        echo '</form></div>';
    }

    public static function render_result_notice() {
        if ( ! isset( $_GET['ufsc_manual_renewal'] ) || is_array( $_GET['ufsc_manual_renewal'] ) ) { return; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message de retour uniquement.
        $status = sanitize_key( wp_unslash( $_GET['ufsc_manual_renewal'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'renewed' === $status ) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Affiliation renouvelée manuellement avec succès.', 'ufsc-clubs' ) . '</strong></p></div>';
        } elseif ( 'already_active' === $status ) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Cette affiliation est déjà active pour la saison sélectionnée.', 'ufsc-clubs' ) . '</p></div>';
        } elseif ( 'error' === $status ) {
            $message = isset( $_GET['ufsc_manual_renewal_message'] ) && ! is_array( $_GET['ufsc_manual_renewal_message'] ) ? sanitize_text_field( wp_unslash( $_GET['ufsc_manual_renewal_message'] ) ) : __( 'Le renouvellement manuel a échoué.', 'ufsc-clubs' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

    public static function handle() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }
        if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
            wp_die( esc_html__( 'Méthode non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 405 ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season  = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? self::normalize_season( wp_unslash( $_POST['season'] ) ) : '';
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
            'status'          => 'active',
            'payment_status'  => 'manual',
            'request_type'    => 'admin_manual',
            'decision_reason' => __( 'Renouvellement manuel effectué par un administrateur UFSC.', 'ufsc-clubs' ),
            // Ne jamais réinjecter automatiquement un ancien numéro d'affiliation.
            'num_affiliation' => $existing && ! empty( $existing->num_affiliation ) ? (string) $existing->num_affiliation : '',
            'wc_order_id'     => $existing ? absint( $existing->wc_order_id ?? 0 ) : 0,
            'requested_at'    => current_time( 'mysql' ),
        );

        $result = UFSC_Season_Archive_Manager::save_admin_affiliation( $club_id, $season, $values, get_current_user_id() );
        if ( is_wp_error( $result ) ) {
            self::redirect_back( $club_id, $season, 'error', $result->get_error_message() );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'club_manual_renewal', array( 'club_id' => $club_id, 'season' => $season, 'user_id' => get_current_user_id() ) );
        }
        self::redirect_back( $club_id, $season, 'renewed' );
    }

    private static function redirect_back( $club_id, $season, $status, $message = '' ) {
        $return_to = isset( $_POST['return_to'] ) && ! is_array( $_POST['return_to'] ) ? wp_unslash( $_POST['return_to'] ) : '';
        $return_to = wp_validate_redirect( $return_to, '' );
        if ( ! $return_to ) {
            $return_to = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'edit', 'id' => absint( $club_id ) ), admin_url( 'admin.php' ) );
        }
        $args = array( 'ufsc_manual_renewal' => sanitize_key( $status ), 'renewed_club_id' => absint( $club_id ) );
        if ( $message ) { $args['ufsc_manual_renewal_message'] = sanitize_text_field( $message ); }
        wp_safe_redirect( add_query_arg( $args, $return_to ) );
        exit;
    }

    private static function current_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        return $request_uri ? admin_url( ltrim( $request_uri, '/' ) ) : admin_url( 'admin.php?page=ufsc-sql-clubs' );
    }

    private static function normalize_season( $season ) {
        if ( class_exists( 'UFSC_Season_Service' ) ) { return (string) UFSC_Season_Service::normalize_season( $season ); }
        $season = trim( str_replace( '/', '-', sanitize_text_field( (string) $season ) ) );
        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
    }
}

UFSC_Club_Manual_Renewal_Admin::init();
