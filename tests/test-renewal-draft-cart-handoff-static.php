<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-draft-cart-handoff.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $flags, "'/renewal-draft-cart-handoff.php'" ), 'runtime loader includes the renewal draft handoff' );
$assert( false !== strpos( $file, "array( 'ufsc_save_licence', 'ufsc_update_licence' )" ), 'only unified edit/save routes are observed' );
$assert( false !== strpos( $file, "array( 'previous_licence_id', 'renewed_from_licence_id' )" ), 'renewal target requires historical source linkage' );
$assert( false !== strpos( $file, 'ufsc_renewal_native_handoff_add_target' ), 'payable renewal draft reuses the native renewal cart path' );
$assert( false !== strpos( $file, 'ufsc_persist_woocommerce_cart' ), 'native cart is persisted after handoff' );
$assert( false !== strpos( $file, 'ufsc_renewal_recovery_cart_contains_target' ), 'cart presence is confirmed before success' );
$assert( false !== strpos( $file, "'en_attente'" ), 'confirmed cart handoff promotes target to pending validation state' );
$assert( false !== strpos( $file, "\$_POST['ufsc_submit_action'] = 'continue'" ), 'generic unified cart handoff is disabled after the renewal-specific path' );
$assert( false !== strpos( $file, "remove_cart_item( \$line['key'] )" ), 'only the exact stale target cart line may be removed' );
$assert( false === strpos( $file, 'empty_cart(' ), 'existing cart is never emptied' );
$assert( false === stripos( $file, 'ALTER TABLE' ), 'no schema migration is introduced' );
$assert( 0 === preg_match( '/\bDELETE\s+FROM\b/i', $file ), 'no licence/history row deletion is introduced' );
