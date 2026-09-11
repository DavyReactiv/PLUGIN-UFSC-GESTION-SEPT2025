<?php
/** Static production safeguards for paid licence cart handoff. */
$root   = dirname( __DIR__ );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );
$guard  = file_get_contents( $root . '/inc/common/paid-licence-cart-postcondition.php' );
$cart   = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$errors = array();

$assert = static function( $condition, $message ) use ( &$errors ) {
    if ( ! $condition ) { $errors[] = $message; }
};

$assert( false !== strpos( $flags, 'paid-licence-cart-postcondition.php' ), 'P0 cart postcondition must be loaded by feature flags.' );
$assert( false !== strpos( $guard, "add_filter( 'wp_redirect', 'ufsc_paid_cart_enforce_redirect_postcondition', 999, 2 )" ), 'Guard must run as the final paid cart redirect postcondition.' );
$assert( false !== strpos( $guard, 'ufsc_paid_cart_is_cart_redirect' ), 'Guard must run only for a declared Woo cart redirect.' );
$included_guard = 'if ( ! empty( $row->is_included ) )';
$assert( false !== strpos( $guard, $included_guard ), 'Included licences must be excluded from paid recovery.' );
$assert( false !== strpos( $guard, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'Recovery must reuse the canonical idempotent cart helper.' );
$assert( false !== strpos( $guard, 'ufsc_persist_woocommerce_cart' ), 'Native Woo session must be persisted after status hooks.' );
$assert( false !== strpos( $guard, 'ufsc_paid_cart_contains_licence' ), 'Success must be verified against the live native cart.' );
$assert( false !== strpos( $guard, 'ufsc_is_licence_linked_to_order' ), 'Failure recovery must not alter a licence already owned by an order.' );
$assert( false !== strpos( $guard, "'brouillon'" ), 'A confirmed unpaid handoff failure must remain editable as draft.' );
$assert( false === strpos( $guard, 'ufsc_allocate_pack_credit' ), 'Postcondition must never allocate or mutate the affiliation quota.' );
$assert( false === strpos( $guard, 'DELETE FROM' ) && false === strpos( $guard, 'ALTER TABLE' ), 'P0 guard must not delete rows or change schema.' );
$assert( false !== strpos( $cart, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'Canonical native cart helper must remain available.' );

if ( $errors ) {
    fwrite( STDERR, "Paid licence cart postcondition safeguards failed:\n- " . implode( "\n- ", $errors ) . "\n" );
    exit( 1 );
}

echo "Paid licence cart postcondition safeguards: OK\n";
