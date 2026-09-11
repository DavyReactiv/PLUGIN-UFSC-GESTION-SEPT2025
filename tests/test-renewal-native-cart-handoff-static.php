<?php
/** Static guardrails for the P0 renewal native cart handoff. */
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-native-cart-handoff.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$errors = array();
$assert = static function ( $condition, $message ) use ( &$errors ) {
    if ( ! $condition ) { $errors[] = $message; }
};

$assert( false !== strpos( $flags, "renewal-native-cart-handoff.php" ), 'feature-flags must load the native renewal handoff' );
$assert( false !== strpos( $file, "remove_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_recovery_handle_final_request', 1 )" ), 'new P0 must replace the previous final renewal handler only' );
$assert( false !== strpos( $file, "ufsc_renew_intent_fallback" ), 'final intent fallback must be honored' );
$assert( false !== strpos( $file, "WC()->cart->add_to_cart" ), 'payable renewal must use native Woo add_to_cart' );
$assert( false === strpos( $file, "ufsc_add_licence_ids_to_cart_idempotent(" ), 'retry handoff must not re-enter the generic licence helper' );
$assert( false === strpos( $file, "->set_quantity(" ), 'renewal handoff must not mutate the just-created row with set_quantity' );
$assert( false !== strpos( $file, "ufsc_persist_woocommerce_cart()" ), 'handler must persist the cart once after processing paid renewals' );
$assert( false !== strpos( $file, "ufsc_renewal_recovery_cart_contains_target" ), 'cart presence must be verified before success' );
$assert( false !== strpos( $file, "ufsc_is_licence_linked_to_order" ), 'existing order protection must remain' );
$assert( false !== strpos( $file, "ufsc_renewal_recovery_keep_editable" ), 'failed handoff must keep the dossier recoverable' );

if ( $errors ) {
    foreach ( $errors as $error ) { fwrite( STDERR, "FAIL: {$error}\n" ); }
    exit( 1 );
}

echo "Renewal native cart handoff static: OK\n";
