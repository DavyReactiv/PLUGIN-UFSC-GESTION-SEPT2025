<?php
$root    = dirname( __DIR__ );
$module  = file_get_contents( $root . '/inc/common/readonly-access-denied-messages.php' );
$flags   = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'readonly-access-denied-messages.php' ), 'runtime must load explicit access-denied messages' );
$assert( false !== strpos( $module, 'vous ne disposez pas des droits nécessaires' ), 'forbidden routes must clearly explain missing rights in French' );
$assert( false !== strpos( $module, 'Droits insuffisants' ), 'forbidden page title must be explicit' );
$assert( false !== strpos( $module, "'ufsc-settings'" ), 'plugin settings must be covered by the explicit guard' );
$assert( false !== strpos( $module, "'ufsc-permissions'" ), 'permissions screen must be covered by the explicit guard' );
$assert( false !== strpos( $module, "'ufsc-woocommerce'" ), 'WooCommerce settings must be covered by the explicit guard' );
$assert( false !== strpos( $module, "add_action( 'admin_init', 'ufsc_readonly_access_denied_message_guard', 4 )" ), 'explicit message guard must run before the existing priority-5 guard' );
$assert( false === strpos( $module, 'UFSC_Unified_Handlers::' ), 'message layer must not call licence business handlers' );
$assert( false === strpos( $module, 'WC()->cart' ), 'message layer must not touch the WooCommerce cart' );
$assert( false === strpos( $module, '$wpdb->' ), 'message layer must not write or query business data' );

echo "Read-only access denied messages safeguards OK\n";
