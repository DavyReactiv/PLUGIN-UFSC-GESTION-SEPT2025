<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$journey = file_get_contents( $root . '/inc/common/club-journey.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false === strpos( $flags, 'p0-paid-cart-handoff.php' ), 'legacy handoff wrapper is not loaded' );
$assert( false !== strpos( $flags, 'club-journey.php' ), 'consolidated journey is loaded' );
$assert( false !== strpos( $journey, 'ufsc_handle_add_to_cart_secure' ), 'paid decision hands off to canonical secure cart handler' );
$assert( false !== strpos( $journey, "wp_create_nonce( 'ufsc_add_to_cart_action' )" ), 'canonical cart nonce is prepared after server authorization' );
$assert( false !== strpos( $journey, "'new_licence'" ), 'new licence paid intent is explicit' );
$assert( false !== strpos( $cart, 'ufsc_persist_woocommerce_cart' ), 'canonical WooCommerce cart persistence remains present' );

echo "Legacy paid-cart wrapper retired; consolidated cart handoff safeguards OK\n";
