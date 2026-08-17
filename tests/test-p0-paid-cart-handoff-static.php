<?php
$root = dirname( __DIR__ );
$handoff = file_get_contents( $root . '/inc/common/p0-paid-cart-handoff.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$p0 = file_get_contents( $root . '/inc/common/p0-quota-cart-kpi.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "p0-paid-cart-handoff.php" ), 'handoff module is loaded' );
$assert( false !== strpos( $handoff, "wp_create_nonce( 'ufsc_add_to_cart_action' )" ), 'WooCommerce nonce is prepared' );
$assert( false !== strpos( $handoff, "\$_POST['ufsc_action'] = 'new_licence'" ), 'new licence cart intent is explicit' );
$assert( false !== strpos( $handoff, "remove_action( 'admin_post_ufsc_p0_finalize_licence', 'ufsc_p0_handle_finalize_licence' )" ), 'legacy finalizer hook is replaced once' );
$assert( false !== strpos( $handoff, 'ufsc_p0_handle_finalize_licence();' ), 'existing quota and authorization finalizer remains authoritative' );
$assert( false !== strpos( $p0, 'name="product_id"' ) && false !== strpos( $p0, 'name="ufsc_license_ids"' ), 'licence detail form keeps canonical WooCommerce product and licence identifiers' );

echo "P0 paid cart handoff static safeguards OK\n";
