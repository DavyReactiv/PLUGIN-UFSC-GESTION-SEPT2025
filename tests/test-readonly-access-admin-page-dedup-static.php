<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/inc/common/readonly-access-admin-page-dedup.php' );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'readonly-access-admin-page-dedup.php' ), 'runtime must load the admin page dedup compatibility layer' );
$assert( false !== strpos( $module, "get_plugin_page_hookname( 'ufsc-readonly-access', 'ufsc-dashboard' )" ), 'dedup must target only the read-only access submenu hook' );
$assert( false !== strpos( $module, 'remove_action( $hook, \'ufsc_readonly_access_render_admin_page\' )' ), 'dedup must remove only the legacy renderer' );
$assert( false !== strpos( $module, "add_action( 'admin_menu', 'ufsc_readonly_access_deduplicate_admin_page_callback', 33 )" ), 'dedup must run after the hardened submenu is registered' );
$assert( false === strpos( $module, 'UFSC_Unified_Handlers::' ), 'dedup must not call licence business handlers' );
$assert( false === strpos( $module, 'WC()->cart' ), 'dedup must not touch WooCommerce cart logic' );
$assert( false === strpos( $module, '$wpdb->' ), 'dedup must not query or write business data' );

echo "Read-only access admin page dedup safeguards OK\n";
