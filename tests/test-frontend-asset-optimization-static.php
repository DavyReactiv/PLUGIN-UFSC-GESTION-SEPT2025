<?php
/** Static contracts for scoped frontend assets and idempotent portal runtime. */

$root      = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$frontend  = file_get_contents( $root . '/assets/frontend/js/frontend.js' );
$dashboard = file_get_contents( $root . '/assets/js/frontend-dashboard.js' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$method_start = strpos( $bootstrap, 'public function enqueue_frontend_assets()' );
$method_end   = strpos( $bootstrap, 'public function localize_frontend_scripts()', $method_start );
$method       = substr( $bootstrap, $method_start, $method_end - $method_start );

$assert( false !== $method_start && false !== $method_end, 'Frontend enqueue method is inspectable.' );
$assert( false === strpos( $method, 'is_account_page' ), 'Generic WooCommerce account pages do not load portal assets.' );
$assert( false === strpos( $method, "'mon-compte'" ) && false === strpos( $method, "'my-account'" ), 'Generic account slugs are excluded.' );
foreach ( array( 'tableau-de-bord-club', 'compte-club', 'tableau-de-bord', 'club-dashboard', 'mon-club' ) as $slug ) {
    $assert( false !== strpos( $method, "'{$slug}'" ), 'Known portal fallback remains supported: ' . $slug );
}

preg_match( '/\$style_shortcodes\s*=\s*array\((.*?)\);/s', $method, $style_match );
preg_match( '/\$runtime_shortcodes\s*=\s*array\((.*?)\);/s', $method, $runtime_match );
$assert( ! empty( $style_match[1] ) && ! empty( $runtime_match[1] ), 'Style and runtime consumer maps are explicit.' );
foreach ( array( 'ufsc_club_dashboard', 'ufsc_club_licences', 'ufsc_club_stats', 'ufsc_club_profile', 'ufsc_add_licence' ) as $shortcode ) {
    $assert( false !== strpos( $style_match[1], "'{$shortcode}'" ), 'Existing stylesheet consumer is preserved: ' . $shortcode );
}
foreach ( array( 'ufsc_club_dashboard', 'ufsc_club_licences', 'ufsc_club_profile' ) as $shortcode ) {
    $assert( false !== strpos( $runtime_match[1], "'{$shortcode}'" ), 'Interactive runtime consumer is preserved: ' . $shortcode );
}
$assert( false === strpos( $runtime_match[1], "'ufsc_club_stats'" ), 'Server-rendered statistics avoid both general scripts.' );
$assert( false === strpos( $runtime_match[1], "'ufsc_add_licence'" ), 'Licence form uses only its dedicated production script.' );

foreach ( array( 'assets/frontend/css/frontend.css', 'assets/frontend/js/frontend.js' ) as $asset ) {
    $assert( false !== strpos( $method, "ufsc_asset_version( '{$asset}' )" ), 'filemtime helper remains active for ' . $asset );
}
$assert( false !== strpos( $method, "\$dashboard_css = UFSC_CL_DIR . 'assets/css/ufsc-front.css'" ) && false !== strpos( $method, 'filemtime( $dashboard_css )' ), 'Direct filemtime remains active for dashboard CSS.' );
$assert( false !== strpos( $method, "\$dashboard_js = UFSC_CL_DIR . 'assets/js/frontend-dashboard.js'" ) && false !== strpos( $method, 'filemtime( $dashboard_js )' ), 'Direct filemtime remains active for dashboard JavaScript.' );

$assert( false !== strpos( $frontend, "data('ufsc-frontend-initialized')" ), 'General portal initialization is idempotent.' );
$assert( false !== strpos( $frontend, "getElementById('ufsc-dynamic-styles')" ) && false !== strpos( $frontend, "attr('id', 'ufsc-dynamic-styles')" ), 'Dynamic style injection is unique.' );
foreach ( array( 'ufsc-renewal-initialized', 'ufsc-renewal-profile-initialized', 'ufsc-logo-editor-initialized', 'ufsc-account-tabs-initialized' ) as $marker ) {
    $assert( false !== strpos( $dashboard, $marker ), 'Component initialization marker exists: ' . $marker );
}
$assert( false !== strpos( $dashboard, "document.hidden || !document.getElementById('ufsc-dashboard')" ), 'Periodic refresh pauses when hidden or absent.' );
$assert( false !== strpos( $dashboard, "!document.hidden && document.getElementById('ufsc-dashboard')" ), 'Visibility refresh requires the dashboard component.' );
$assert( false !== strpos( $dashboard, 'if (this.refresh_timer)' ), 'Only one refresh interval can be created.' );

echo "Frontend asset optimization safeguards OK\n";
