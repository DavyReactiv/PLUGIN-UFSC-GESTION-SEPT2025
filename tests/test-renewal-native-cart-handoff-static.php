<?php
/** Static guardrails for the P0 renewal cart handoff. */
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-native-cart-handoff.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$errors = array();
$assert = static function ( $condition, $message ) use ( &$errors ) {
    if ( ! $condition ) { $errors[] = $message; }
};

$assert( false !== strpos( $flags, "renewal-native-cart-handoff.php" ), 'feature-flags must load the renewal handoff' );
$assert( false !== strpos( $file, "remove_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_recovery_handle_final_request', 1 )" ), 'P0 must replace the previous final renewal handler only' );
$assert( false !== strpos( $file, "ufsc_renew_intent_fallback" ), 'final intent fallback must be honored' );
$assert( false !== strpos( $file, "ufsc_add_licence_ids_to_cart_idempotent(" ), 'payable renewal must reuse the canonical licence cart helper' );
$assert( false === strpos( $file, "WC()->cart->add_to_cart" ), 'renewal handoff must not maintain a second direct Woo add implementation' );
$assert( false === strpos( $file, "->set_quantity(" ), 'renewal handoff must not mutate the just-created row with set_quantity' );
$assert( false !== strpos( $file, "ufsc_persist_woocommerce_cart()" ), 'handler must persist the cart once after processing paid renewals' );
$assert( false !== strpos( $file, "ufsc_renewal_recovery_cart_contains_target" ), 'cart presence must be verified before success' );
$assert( false !== strpos( $file, "ufsc_is_licence_linked_to_order" ), 'existing order protection must remain' );
$assert( false !== strpos( $file, "ufsc_renewal_recovery_keep_editable" ), 'failed handoff must keep the dossier recoverable' );
$assert( false !== strpos( $file, "ufsc_operation_type']          = 'renewal'" ), 'renewal operation metadata must remain explicit' );
$assert( false !== strpos( $file, "ufsc_renew_from_licence_id']   = $source_id" ), 'historical renewal source metadata must remain explicit' );

if ( $errors ) {
    foreach ( $errors as $error ) { fwrite( STDERR, "FAIL: {$error}\n" ); }
    exit( 1 );
}

echo "Renewal canonical cart handoff static: OK\n";
