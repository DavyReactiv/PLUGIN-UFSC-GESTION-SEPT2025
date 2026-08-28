<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Clear French access-denied messages for strict read-only UFSC accounts.
 *
 * Presentation/security boundary only: no business data is written and no
 * licence, affiliation, quota or WooCommerce handler is called here.
 */
function ufsc_readonly_access_denied_message_guard() {
    if ( ! is_admin() || ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user() ) {
        return;
    }

    $method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
    $page   = isset( $_REQUEST['page'] ) && ! is_array( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
    $action = isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

    if ( 'POST' === $method && ( 0 === strpos( $page, 'ufsc' ) || 0 === strpos( $action, 'ufsc' ) ) ) {
        wp_die(
            esc_html__( 'Accès refusé : vous ne disposez pas des droits nécessaires pour modifier ces informations. Votre compte UFSC est limité à la consultation.', 'ufsc-clubs' ),
            esc_html__( 'Droits insuffisants', 'ufsc-clubs' ),
            array( 'response' => 403 )
        );
    }

    $blocked_pages = array(
        'ufsc-exports',
        'ufsc-import',
        'ufsc-settings',
        'ufsc-woocommerce',
        'ufsc-permissions',
        'ufsc-readonly-access',
        'ufsc-diagnostics',
    );

    if ( in_array( $page, $blocked_pages, true ) || false !== strpos( $page, 'communication' ) || false !== strpos( $page, 'mail' ) ) {
        wp_die(
            esc_html__( 'Accès refusé : vous ne disposez pas des droits nécessaires pour consulter cette rubrique. Elle est réservée aux administrateurs autorisés.', 'ufsc-clubs' ),
            esc_html__( 'Droits insuffisants', 'ufsc-clubs' ),
            array( 'response' => 403 )
        );
    }

    $post_type = isset( $_REQUEST['post_type'] ) && ! is_array( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
    if ( 'shop_order' === $post_type || 'woocommerce' === $page || 0 === strpos( $page, 'wc-' ) ) {
        wp_die(
            esc_html__( 'Accès refusé : vous ne disposez pas des droits nécessaires pour accéder aux commandes, règlements ou données WooCommerce.', 'ufsc-clubs' ),
            esc_html__( 'Droits insuffisants', 'ufsc-clubs' ),
            array( 'response' => 403 )
        );
    }

    if ( 'GET' === $method && 0 === strpos( $page, 'ufsc' ) && in_array( $action, array( 'new', 'delete', 'trash', 'restore', 'force-delete', 'renew', 'validate', 'approve', 'reject', 'import', 'export', 'save' ), true ) ) {
        wp_die(
            esc_html__( 'Accès refusé : vous ne disposez pas des droits nécessaires pour effectuer cette action. Votre compte UFSC est en consultation uniquement.', 'ufsc-clubs' ),
            esc_html__( 'Droits insuffisants', 'ufsc-clubs' ),
            array( 'response' => 403 )
        );
    }
}
add_action( 'admin_init', 'ufsc_readonly_access_denied_message_guard', 4 );

// Federation read-only accounts must land in the UFSC back office after login.
// Ordinary club accounts keep their existing front-office redirect unchanged.
$ufsc_readonly_access_login_redirect = dirname( __FILE__ ) . '/readonly-access-login-redirect.php';
if ( file_exists( $ufsc_readonly_access_login_redirect ) ) {
    require_once $ufsc_readonly_access_login_redirect;
}

// Managed federation read-only accounts may use /wp-admin/ but must never inherit
// WordPress/other-plugin administration capabilities from an older non-admin role.
$ufsc_readonly_wordpress_admin_isolation = dirname( __FILE__ ) . '/readonly-wordpress-admin-isolation.php';
if ( file_exists( $ufsc_readonly_wordpress_admin_isolation ) ) {
    require_once $ufsc_readonly_wordpress_admin_isolation;
}
