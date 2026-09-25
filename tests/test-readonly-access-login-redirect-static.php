<?php
$root     = dirname( __DIR__ );
$loader   = file_get_contents( $root . '/inc/common/readonly-access-denied-messages.php' );
$redirect = file_get_contents( $root . '/inc/common/readonly-access-login-redirect.php' );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$simplified = file_get_contents( $root . '/includes/admin/class-ufsc-simplified-admin.php' );
$permissions = file_get_contents( $root . '/includes/permissions/class-ufsc-permissions.php' );

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
$assert( false !== strpos( $bootstrap, 'UFSC_Simplified_Admin::init();' ), 'limited-admin redirect guards must register while the plugin is loading, before init redirects can fire' );
$assert( false === strpos( $bootstrap, "add_action( 'init', array( 'UFSC_Simplified_Admin', 'init' ) )" ), 'limited-admin redirect guards must not wait for the init hook' );
$assert( false !== strpos( $simplified, 'static $initialized = false' ), 'simplified admin bootstrap must be idempotent when initialized early' );
$assert( false !== strpos( $bootstrap, "UFSC_CL_ROUTING_DIAGNOSTIC_BUILD" ) && false !== strpos( $bootstrap, 'diag-20260925-1' ), 'production diagnostics must expose a deployment marker' );
$assert( false !== strpos( $simplified, 'record_routing_trace' ), 'limited-admin routing diagnostics must record bounded technical traces' );
$assert( false !== strpos( $simplified, "'_ufsc_admin_routing_trace'" ), 'routing trace must use dedicated technical user meta' );
$assert( false !== strpos( $simplified, 'array_slice( $trace, -12 )' ), 'routing trace must remain bounded' );
$assert( false !== strpos( $permissions, 'Comptes UFSC et dernière trace de routage' ), 'administrator diagnostics must expose the last routing trace' );
$assert( false !== strpos( $permissions, 'Marqueur diagnostic' ), 'administrator diagnostics must expose the production deployment marker' );
$assert( false !== strpos( $permissions, 'Aucune trace' ), 'administrator diagnostics must explain missing routing traces' );

echo "Read-only federation admin login redirect safeguards OK\n";
