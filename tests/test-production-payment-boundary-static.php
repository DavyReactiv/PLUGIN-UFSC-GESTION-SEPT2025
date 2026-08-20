<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$boundary = file_get_contents( $root . '/inc/common/production-payment-boundary.php' );
$woo_hooks = file_get_contents( $root . '/inc/woocommerce/hooks.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'production-payment-boundary.php' ), 'production payment boundary must be loaded' );
$assert( false !== strpos( $boundary, "'ufsc_action']          = 'licence_payment'" ), 'paid renewal target must be renamed away from the historical copy action' );
$assert( false !== strpos( $boundary, "'ufsc_original_action'] = 'renew_licence'" ), 'renewal traceability must remain explicit' );
$assert( false !== strpos( $woo_hooks, "if ( 'renew_licence' === \$action )" ), 'historical post-payment copier exists and must be bypassed for target payments' );
$assert( false !== strpos( $boundary, 'ufsc_notice=club_created' ), 'new club payment chaining must require a successful club creation redirect' );
$assert( false !== strpos( $boundary, "'ufsc_action'                  => 'renew_affiliation'" ), 'new club annual purchase must use the annual affiliation payment processor' );
$assert( false !== strpos( $boundary, "'ufsc_request_type'            => 'new_affiliation'" ), 'new affiliation must be distinguishable from a renewal' );
$assert( false !== strpos( $boundary, 'ufsc_persist_woocommerce_cart' ), 'new affiliation cart must be persisted' );
$assert( false !== strpos( $boundary, 'ufsc_cart_has_renewal_item' ), 'new affiliation chain must be idempotent in cart' );

echo "OK: production Woo payment boundaries\n";
