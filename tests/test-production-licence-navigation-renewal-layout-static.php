<?php
$root = dirname( __DIR__ );
$ux_js = file_get_contents( $root . '/assets/js/ufsc-production-licence-ux.js' );
$ux_css = file_get_contents( $root . '/assets/css/ufsc-production-licence-ux.css' );
$ux_php = file_get_contents( $root . '/inc/common/production-licence-ux.php' );
$legacy_front = file_get_contents( $root . '/assets/frontend/js/frontend.js' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $legacy_front, "$('#ufsc-section-' + section).addClass('active')" ), 'the reproduced legacy tab race must remain detectable by the compatibility test' );
$assert( false !== strpos( $ux_js, '.ufsc-nav-btn[data-section="licences"]' ), 'production UX must intercept the licences dashboard button' );
$assert( false !== strpos( $ux_js, 'stopImmediatePropagation' ), 'the canonical licences click must stop competing legacy tab handlers' );
$assert( false !== strpos( $ux_js, 'window.location.assign(config().current)' ), 'Mes licences must use the canonical server-rendered route' );
$assert( false !== strpos( $ux_js, "hash === '#licences'" ), 'the old #licences bookmark must be repaired' );
$assert( false !== strpos( $ux_js, 'promoteVisibleRenewalProfiles' ), 'visible renewal profiles must be promoted out of the legacy table row' );
$assert( false !== strpos( $ux_js, 'ufsc-renewal-profile-panel' ), 'the standalone renewal verification panel must exist' );
$assert( false !== strpos( $ux_css, '.ufsc-renewal-profile-panel' ), 'renewal verification panel must have a dedicated full-width presentation' );
$assert( false !== strpos( $ux_css, 'width:100%!important' ), 'renewal/table compatibility layer must retain hard width containment' );
$assert( false !== strpos( $ux_php, 'ufsc_production_redirect_included_direct_renewal' ), 'successful included direct renewals must leave the assistant' );
$assert( false !== strpos( $ux_php, 'ufsc_get_renewed_licence_marker' ), 'included redirect must require the canonical renewal marker' );
$assert( false !== strpos( $ux_php, "'ufsc_message' => 'renewal_included'" ), 'included redirect must expose a clear success message' );

echo "Production licence navigation and renewal layout safeguards OK\n";
