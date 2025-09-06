<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Helper for licence form operations on the frontend.
 */
class UFSC_Licence_Form {

    /**
     * Get current number of licences included in the quota for a club.
     *
     * @param int $club_id Optional club ID. Defaults to current user's club.
     * @return int
     */
    public static function get_included_count( $club_id = 0 ) {
        if ( ! $club_id && is_user_logged_in() ) {
            $club_id = ufsc_get_user_club_id( get_current_user_id() );
        }
        if ( ! $club_id ) {
            return 0;
        }
        return UFSC_SQL::count_included_licences( $club_id );
    }

    /**
     * Ensure first and last name are present before adding to cart.
     *
     * @param array $data Licence data.
     * @return true|WP_Error
     */
    public static function validate_names_for_cart( $data ) {
        if ( empty( $data['prenom'] ) || empty( $data['nom'] ) ) {
            return new WP_Error( 'missing_name', __( 'Le nom et le prénom sont obligatoires pour ajouter au panier.', 'ufsc-clubs' ) );
        }
        return true;
    }

    /**
     * Redirect to given URL with notice and optional tab parameter
     *
     * @param string $url     Base URL for redirection.
     * @param string $notice  Notice slug to display.
     * @param string $tab     Optional tab to preserve on redirect.
     */
    public static function redirect_with_notice( $url, $notice, $tab = '' ) {
        $redirect = ufsc_redirect_with_notice( $url, $notice );
        if ( $tab ) {
            $redirect = add_query_arg( 'tab', sanitize_key( $tab ), $redirect );
        }
        wp_safe_redirect( $redirect );
        exit;
    }
}
