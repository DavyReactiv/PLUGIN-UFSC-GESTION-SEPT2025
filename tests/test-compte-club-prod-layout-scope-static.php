<?php
$root   = dirname( __DIR__ );
$portal = file_get_contents( $root . '/inc/common/portal-ui-cleanup.php' );
$css    = file_get_contents( $root . '/assets/css/ufsc-account-overview-fix.css' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $portal, "is_page( 'compte-club' )" ), 'Compte Club must receive the portal scope even when Elementor stores the shortcode outside post_content' );
$assert( false !== strpos( $portal, "'ufsc-portal-clean-page'" ), 'production fallback must activate the existing scoped portal layout class' );
$assert( false !== strpos( $portal, 'has_shortcode( $post->post_content' ), 'existing shortcode detection must remain available for other portal pages' );
$assert( false !== strpos( $css, 'body.ufsc-portal-clean-page .ufsc-club-account' ), 'known-good Compte Club CSS must stay scoped to the portal body class' );
$assert( false !== strpos( $css, 'body.ufsc-portal-clean-page .ufsc-club-account .ufsc-club-hero {' ), 'overview hero must keep an explicit scoped layout contract' );
$assert( false !== strpos( $css, 'grid-template-columns: minmax(190px, 220px) minmax(0, 1fr) !important;' ), 'overview hero must keep the validated compact two-column layout contract' );
$assert( false !== strpos( $css, 'grid-column: 1 !important;' ) && false !== strpos( $css, 'grid-column: 2 !important;' ), 'logo and club content must remain pinned to separate desktop columns' );
$assert( false === strpos( $css, '.ufsc-club-hero:has(> .ufsc-club-hero-media)' ), 'overview layout must not depend on :has() support' );
$assert( false === strpos( $portal, 'UFSC_Unified_Handlers::' ), 'presentation scope repair must not call licence business handlers' );
$assert( false === strpos( $portal, 'WC()->cart' ), 'presentation scope repair must not touch WooCommerce cart logic' );
$assert( false === strpos( $portal, 'ALTER TABLE' ), 'presentation scope repair must not change database schema' );

echo "Compte Club production layout scope safeguards OK\n";
