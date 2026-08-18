<?php
$root = dirname( __DIR__ );
$flags    = file_get_contents( $root . '/inc/common/feature-flags.php' );
$journey  = file_get_contents( $root . '/inc/common/club-journey.php' );
$css      = file_get_contents( $root . '/assets/css/ufsc-club-journey.css' );
$handlers = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$cart     = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$stats    = file_get_contents( $root . '/includes/front/class-ufsc-stats.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "apply_filters( 'ufsc_quotas_enabled', true )" ), 'quota affiliation is enabled by default' );
$assert( false !== strpos( $flags, 'club-journey.php' ), 'consolidated journey module is loaded' );
$assert( false === strpos( $flags, 'p0-quota-cart-kpi.php' ), 'legacy P0 quota/cart module is not loaded' );
$assert( false !== strpos( $journey, 'ufsc_journey_pack_state' ), 'one pack-state source is available' );
$assert( false !== strpos( $journey, 'ufsc_journey_licence_decision' ), 'one licence decision source is available' );
$assert( false !== strpos( $journey, 'ufsc_allocate_pack_credit' ), 'server-side pack allocation is authoritative at finalization' );
$assert( false !== strpos( $journey, 'Envoyer pour validation' ), 'included licence has a non-payment CTA' );
$assert( false !== strpos( $journey, 'Ajouter au panier — licence payante' ), 'paid licence has the WooCommerce CTA' );
$assert( false !== strpos( $journey, "\$_POST['product_id']" ), 'paid handoff injects canonical product before secure cart handler' );
$assert( false !== strpos( $journey, 'ufsc_handle_add_to_cart_secure();' ), 'paid licences delegate to canonical secure cart handler' );
$assert( false !== strpos( $journey, "validated_licences'] ?? 0" ), 'club journey active-licence count uses validated current-season licences' );
$assert( false !== strpos( $stats, 'if ( ! $official ) { continue; }' ), 'demographics remain official/validated only' );
$assert( false !== strpos( $handlers, "if ( ! empty( $allocation['included'] ) )" ), 'canonical unified flow still short-circuits included licences before cart' );
$assert( false !== strpos( $cart, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'paid Woo cart insertion remains idempotent' );
$assert( false === strpos( $css, '!important' ), 'consolidated journey stylesheet adds no important overrides' );
$assert( false !== strpos( $css, '@media (max-width: 820px)' ), 'consolidated journey includes responsive tablet/mobile contract' );

echo "OK: consolidated quota/cart/KPI/layout static guards\n";
