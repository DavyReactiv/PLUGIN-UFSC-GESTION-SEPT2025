<?php
$root    = dirname( __DIR__ );
$bridge  = file_get_contents( $root . '/inc/common/affiliation-order-state-bridge.php' );
$flags   = file_get_contents( $root . '/inc/common/feature-flags.php' );
$licence = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert( false !== strpos( $flags, "affiliation-order-state-bridge.php" ), 'bridge must be loaded by runtime composition' );
$assert( false !== strpos( $bridge, "woocommerce_checkout_order_created" ), 'order creation must immediately register the affiliation request' );
$assert( false !== strpos( $bridge, "'pending_payment', 'payment_status' => 'pending'" ), 'bank-transfer pending orders must map to pending payment' );
$assert( false !== strpos( $bridge, "'pending_validation', 'payment_status' => 'paid'" ), 'paid orders must wait for UFSC validation' );
$assert( false !== strpos( $bridge, "array( 'active', 'validated' )" ), 'admin-validated affiliations must never be demoted by Woo status changes' );
$assert( false !== strpos( $bridge, "woocommerce_add_to_cart_validation" ), 'one-pack rule must be enforced server-side' );
$assert( false !== strpos( $bridge, "find_existing_order" ), 'legacy/current Woo orders must block duplicate pack purchases' );
$assert( false !== strpos( $bridge, "maybe_reconcile_existing_orders" ), 'orders created during earlier deployments must be reconciled' );
$assert( false !== strpos( $bridge, "Pack d’affiliation :" ), 'club-facing wording must not expose WooCommerce vocabulary' );
$assert( false !== strpos( $licence, "ufsc_add_renewal_sources_to_cart" ), 'existing licence renewal engine must remain present' );
$assert( false === strpos( $bridge, "ufsc_allocate_pack_credit" ), 'affiliation bridge must not alter licence quota allocation' );
$assert( false === strpos( $bridge, "UFSC_Renewal_Service::create_target_draft" ), 'affiliation bridge must not create or mutate licence renewals' );

echo "Affiliation order state bridge safeguards OK\n";
