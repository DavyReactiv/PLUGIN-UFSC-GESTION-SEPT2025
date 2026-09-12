<?php
$root      = dirname( __DIR__ );
$front     = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$hardening = file_get_contents( $root . '/inc/common/club-dashboard-hardening.php' );
$layout    = file_get_contents( $root . '/assets/css/ufsc-account-overview-fix.css' );
$failures  = array();
$assert    = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $front, 'public static function get_add_licence_url' ), 'canonical add-licence URL helper is present' );
$assert( false !== strpos( $front, "add_query_arg( \$args, self::get_club_portal_url() ) . '#ufsc-section-add_licence'" ), 'add-licence URL starts from a fragment-free portal URL' );
$assert( false !== strpos( $front, 'self::get_add_licence_url( $role, $season )' ), 'officer cards use the canonical add-licence URL' );
$assert( false === strpos( $front, "get_club_portal_url( 'licences' ) ) . '#ufsc-section-add_licence'" ), 'no add/edit action appends a second fragment to the licence-list URL' );
$assert( false !== strpos( $hardening, "'role' => \$role" ), 'missing-role actions retain the exact office to prefill' );
$assert( false !== strpos( $hardening, 'UFSC_Frontend_Shortcodes::get_add_licence_url' ), 'complete-board buttons open the licence form directly' );
$assert( false !== strpos( $front, "if ( 'licences-renouvellement' === \$requested_section )" ), 'renewal candidates are loaded only on the renewal route' );
$assert( false !== strpos( $front, "if ( 'profile' === \$requested_dashboard_section ) { echo self::render_club_profile" ), 'inactive dashboard panels are not rendered' );
$assert( false !== strpos( $front, 'data-url="<?php echo esc_url( $dashboard_tab_urls' ), 'dashboard tabs have canonical server routes' );
$assert( false !== strpos( $front, 'static $request_cache = array();' ), 'request-level query caching is enabled' );
$assert( false !== strpos( $layout, 'grid-auto-rows: max-content !important' ), 'account rows remain content-sized' );

if ( $failures ) {
    fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
    exit( 1 );
}

echo "Club officer actions/performance static tests passed.\n";
