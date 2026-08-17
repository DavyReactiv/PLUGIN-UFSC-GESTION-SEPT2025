<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$p0 = file_get_contents( $root . '/inc/common/p0-quota-cart-kpi.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-p0-quota-cart-kpi.css' );
$handlers = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$stats = file_get_contents( $root . '/includes/front/class-ufsc-stats.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "apply_filters( 'ufsc_quotas_enabled', true )" ), 'quota affiliation is enabled by default' );
$assert( false !== strpos( $flags, 'p0-quota-cart-kpi.php' ), 'P0 module is loaded' );
$assert( false !== strpos( $p0, 'ufsc_allocate_pack_credit' ), 'server-side pack allocation is authoritative at finalization' );
$assert( false !== strpos( $p0, 'Envoyer pour validation — inclus dans votre affiliation' ), 'included licence has a non-payment CTA' );
$assert( false !== strpos( $p0, 'Ajouter au panier — licence payante' ), 'paid licence has the WooCommerce CTA' );
$assert( false !== strpos( $p0, 'name="product_id"' ), 'licence detail form posts canonical WooCommerce product id' );
$assert( false !== strpos( $p0, "$_POST['product_id']" ), 'paid fallback injects canonical product before secure cart handler' );
$assert( false !== strpos( $p0, 'ufsc_handle_add_to_cart_secure();' ), 'paid licences delegate to canonical secure cart handler' );
$assert( false !== strpos( $p0, "remove_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output', 20 )" ), 'misplaced #537 profile enrichment is removed' );
$assert( false !== strpos( $p0, 'class="ufsc-club-form ufsc-club-profile"' ), 'profile actions are inserted before the real club form, not the logo form' );
$assert( false !== strpos( $p0, "validated_licences'] ?? 0" ), 'main licence KPI uses validated current-season licences' );
$assert( false !== strpos( $stats, 'if ( ! $official ) { continue; }' ), 'demographics remain official/validated only' );
$assert( false !== strpos( $handlers, "if ( ! empty( $allocation['included'] ) )" ), 'canonical unified flow still short-circuits included licences before cart' );
$assert( false !== strpos( $cart, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'paid Woo cart insertion remains idempotent' );
$assert( false === strpos( $css, '!important' ), 'P0 stylesheet adds no important overrides' );
$assert( false !== strpos( $css, 'minmax(140px, 190px)' ), 'affiliation trace dates keep a readable desktop column' );

echo "OK: P0 quota/cart/KPI/layout static guards\n";
