<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$hotfix = file_get_contents( $root . '/inc/common/production-readiness-hotfix.php' );
$affiliation = file_get_contents( $root . '/includes/frontend/class-affiliation-form.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'production-readiness-hotfix.php' ), 'production readiness bridge must be loaded' );
$assert( false !== strpos( $hotfix, 'admin_post_ufsc_journey_finalize_licence' ), 'included licence finalization must be deterministically rebound' );
$assert( false !== strpos( $hotfix, 'admin_post_ufsc_bulk_renew_licences' ), 'final renewal requests must be intercepted' );
$assert( false !== strpos( $hotfix, 'UFSC_Licence_Finalization_Service::finalize' ), 'renewal must use canonical finalization service' );
$assert( false !== strpos( $hotfix, 'ufsc_persist_woocommerce_cart' ), 'paid affiliation/licence mutations must persist Woo cart' );
$assert( false !== strpos( $hotfix, 'ufsc_cart_has_renewal_item' ), 'renewal must be idempotent against an existing cart item' );
$assert( false !== strpos( $hotfix, 'ufsc_mark_renewed_licence_marker' ), 'included renewal must mark source as renewed without mutating it' );
$assert( false !== strpos( $hotfix, 'repair_affiliation_form_markup' ), 'invalid nested affiliation form must be repaired before output' );
$assert( false !== strpos( $affiliation, 'class="ufsc-form">' ) && false !== strpos( $affiliation, 'class="ufsc-form ufsc-grid"' ), 'legacy affiliation renderer contains the nested form pair and bridge remains required' );
$assert( false !== strpos( $cart, "status='en_attente'" ), 'legacy renewal path is still present and must remain bypassed for final intents' );
$assert( false === strpos( $hotfix, "status='en_attente'" ), 'production renewal finalizer must not write legacy status vocabulary directly' );

echo "OK: production readiness affiliation/licence boundaries\n";
