<?php
/** Static safeguards for affiliation cart / checkout idempotency. */
$root = dirname( __DIR__ );
$boundary = file_get_contents( $root . '/inc/common/production-payment-boundary.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( strpos( $boundary, "ufsc_wc_has_pending_renewal_order( 'renew_affiliation'" ) !== false, 'New-club flow must detect an existing pending affiliation order.' );
$assert( strpos( $boundary, 'ufsc_get_pending_affiliation_payment_url' ) !== false, 'Existing payable order must be resumed instead of duplicated.' );
$assert( strpos( $boundary, 'Ne créez pas une nouvelle commande' ) !== false, 'Non-payable pending order must explicitly prevent a second order.' );
$assert( strpos( $boundary, "ufsc_cart_has_renewal_item( 'renew_affiliation'" ) !== false, 'Existing cart affiliation line must be reused.' );
$assert( strpos( $boundary, "home_url( '/tableau-de-bord-club/' )" ) !== false, 'Pending/active affiliation returns to canonical club dashboard.' );
$assert( strpos( $cart, 'ufsc_force_affiliation_product_quantity_one' ) !== false, 'Affiliation quantity remains forced to one.' );

echo "Affiliation cart / checkout hardening safeguards OK\n";
