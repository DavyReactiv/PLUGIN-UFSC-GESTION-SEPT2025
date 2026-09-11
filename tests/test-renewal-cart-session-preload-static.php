<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-cart-session-preload.php' );
$compat = file_get_contents( $root . '/inc/common/renewal-intent-compat.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $compat, "'/renewal-cart-session-preload.php'" ), 'renewal compatibility loader includes early Woo cart preload' );
$assert( false !== strpos( $file, "add_action( 'wp_loaded', 'ufsc_cart_preload_before_finalisation', 1 )" ), 'preload runs before Woo cart session priority 10' );
$assert( false !== strpos( $file, "'ufsc_bulk_renew_licences'" ), 'bulk renewal finalization is covered' );
$assert( false !== strpos( $file, "'ufsc_update_licence'" ), 'reopened renewal draft finalization is covered' );
$assert( false === strpos( $file, "'save_draft'" ), 'draft save is not treated as a payment intent' );
$assert( false !== strpos( $file, 'wc_load_cart();' ), 'native WooCommerce cart loader is used' );
$assert( false === strpos( $file, 'empty_cart(' ), 'existing cart is never emptied' );
$assert( 0 === preg_match( '/\bDELETE\s+FROM\b/i', $file ), 'no licence/history deletion is introduced' );
$assert( 0 === preg_match( '/\bUPDATE\s+[^;]*\bSET\b/i', $file ), 'no database mutation is introduced' );
$assert( false === stripos( $file, 'ALTER TABLE' ), 'no schema migration is introduced' );

echo "Renewal cart early-session preload static safeguards: OK\n";
