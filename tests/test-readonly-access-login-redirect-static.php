<?php
$root     = dirname( __DIR__ );
$loader   = file_get_contents( $root . '/inc/common/readonly-access-denied-messages.php' );
$redirect = file_get_contents( $root . '/inc/common/readonly-access-login-redirect.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'readonly-access-login-redirect.php' ), 'read-only access runtime must load the dedicated login redirect layer' );
$assert( false !== strpos( $redirect, 'ufsc_readonly_access_is_user( $user->ID )' ), 'redirect must target only managed federation read-only users' );
$assert( false !== strpos( $redirect, "admin_url( 'admin.php?page=ufsc-dashboard' )" ), 'federation read-only users must land on the UFSC admin dashboard' );
$assert( false !== strpos( $redirect, "add_filter( 'login_redirect', 'ufsc_readonly_access_force_admin_login_redirect', PHP_INT_MAX, 3 )" ), 'login redirect must have final priority' );
$assert( false !== strpos( $redirect, "add_filter( 'wp_redirect', 'ufsc_readonly_access_keep_backoffice_after_login', PHP_INT_MAX, 2 )" ), 'custom/branded login redirects must also be guarded at final priority' );
$assert( false !== strpos( $redirect, "'wp-login.php' === $pagenow" ), 'wp_redirect guard must remain limited to native login requests' );
$assert( false === strpos( $redirect, 'UFSC_Unified_Handlers::' ), 'login redirect must not call licence mutation handlers' );
$assert( false === strpos( $redirect, 'WC()->cart' ), 'login redirect must not touch cart logic' );
$assert( false === strpos( $redirect, '$wpdb->' ), 'login redirect must not query or mutate business data' );

echo "Read-only federation admin login redirect safeguards OK\n";
