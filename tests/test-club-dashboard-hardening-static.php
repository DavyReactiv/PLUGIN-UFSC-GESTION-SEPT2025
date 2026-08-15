<?php
$root = dirname( __DIR__ );
$hardening = file_get_contents( $root . '/inc/common/club-dashboard-hardening.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-club-mobile-v2.css' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$handlers = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, 'club-dashboard-hardening.php' ), 'hardening file is loaded' );
$assert( false !== strpos( $hardening, "'submitted_at'" ), 'submitted_at schema is present' );
$assert( false !== strpos( $hardening, "'validated_at'" ), 'validated_at schema is present' );
$assert( false !== strpos( $hardening, "'validated_by'" ), 'validated_by schema is present' );
$assert( false !== strpos( $hardening, 'ufsc_capture_admin_validation_candidates' ), 'admin transition capture is present' );
$assert( false !== strpos( $hardening, 'Historique : date non disponible' ), 'historical dates are not invented' );
$assert( false !== strpos( $hardening, 'Éléments à compléter' ), 'actionable club profile summary is present' );
$assert( false !== strpos( $hardening, 'Traçabilité affiliation' ), 'annual affiliation chronology is exposed' );
$assert( false !== strpos( $css, 'responsive mobile hardening' ), 'mobile containment stylesheet is present' );
$assert( false === strpos( $css, '!important' ), 'mobile stylesheet adds no !important' );

// Cart regression guards: preserve the canonical new-licence route and pack/cart helpers.
$assert( false !== strpos( $flags, 'UFSC_Unified_Handlers::handle_save_licence();' ), 'new licence keeps canonical save/cart route' );
$assert( false !== strpos( $cart, 'ufsc_allocate_pack_credit' ), 'pack allocation remains server-side' );
$assert( false !== strpos( $handlers, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'paid licences retain idempotent Woo cart insertion' );

// Existing statistics helper must remain official/validated-only for demographics.
$stats = file_get_contents( $root . '/includes/front/class-ufsc-stats.php' );
$assert( false !== strpos( $stats, 'if ( ! $official ) { continue; }' ), 'demographic stats remain limited to official licences' );

echo "OK: club dashboard hardening and cart regression guards\n";
