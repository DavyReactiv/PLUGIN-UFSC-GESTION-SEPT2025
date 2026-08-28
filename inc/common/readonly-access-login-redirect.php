<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Force federation read-only accounts into the UFSC back office after login.
 *
 * This is intentionally isolated from club/member authentication: ordinary club
 * accounts keep their existing front-office redirect, while only users managed
 * by the read-only federation access layer are sent to the UFSC admin dashboard.
 */
function ufsc_readonly_access_force_admin_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    unset( $requested_redirect_to );

    if ( ! $user instanceof WP_User ) {
        return $redirect_to;
    }

    if ( ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user( $user->ID ) ) {
        return $redirect_to;
    }

    return admin_url( 'admin.php?page=ufsc-dashboard' );
}
add_filter( 'login_redirect', 'ufsc_readonly_access_force_admin_login_redirect', PHP_INT_MAX, 3 );

/**
 * Detect the native WordPress login request without changing normal front-office
 * navigation after the user is authenticated.
 */
function ufsc_readonly_access_is_native_login_request() {
    $pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
    if ( 'wp-login.php' === $pagenow ) {
        return true;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    return '' !== $request_uri && false !== strpos( $request_uri, 'wp-login.php' );
}

/**
 * Last-resort redirect guard for branded-login/custom redirect plugins.
 *
 * Some installations apply an additional wp_redirect filter after WordPress has
 * already resolved login_redirect. Only during wp-login.php, and only for a
 * managed federation read-only account, keep the destination inside UFSC admin.
 */
function ufsc_readonly_access_keep_backoffice_after_login( $location, $status ) {
    unset( $status );

    if ( ! is_user_logged_in() || ! ufsc_readonly_access_is_native_login_request() ) {
        return $location;
    }

    if ( ! function_exists( 'ufsc_readonly_access_is_user' ) || ! ufsc_readonly_access_is_user() ) {
        return $location;
    }

    return admin_url( 'admin.php?page=ufsc-dashboard' );
}
add_filter( 'wp_redirect', 'ufsc_readonly_access_keep_backoffice_after_login', PHP_INT_MAX, 2 );
