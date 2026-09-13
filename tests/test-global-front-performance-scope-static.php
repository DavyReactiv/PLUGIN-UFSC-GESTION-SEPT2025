<?php
/** Guards against expensive UFSC work leaking onto unrelated public pages. */
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$ux = file_get_contents( $root . '/inc/common/production-licence-ux.php' );
$admin_compat = file_get_contents( $root . '/inc/common/production-admin-compat.php' );
$traceability = file_get_contents( $root . '/inc/common/production-traceability-schema-compat.php' );
$asset_files = array(
    'portal-ui-cleanup.php',
    'account-overview-layout.php',
    'club-journey.php',
    'licence-workflow-structural.php',
    'p0-dev-recipe-v2.php',
    'p0-dev-recipe-v3.php',
    'p0-quota-cart-kpi.php',
    'club-dashboard-hardening.php',
    'new-club-onboarding-hardening.php',
    'production-licence-ux.php',
);
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $flags, 'function ufsc_is_club_portal_request()' ), 'one shared portal request detector must exist' );
foreach ( $asset_files as $file ) {
    $source = file_get_contents( $root . '/inc/common/' . $file );
    $assert( false !== strpos( $source, 'ufsc_is_club_portal_request()' ), $file . ' must not enqueue on unrelated public pages' );
}
$assert( false !== strpos( $ux, "'renewalCounts'  => \$is_renewal_route ? ufsc_production_renewal_state_counts() : array()" ), 'renewal scans must run only on the renewal route' );
$assert( false !== strpos( $ux, "'licenceMeta' => \$is_current_route ? ufsc_production_current_licence_meta() : array()" ), 'licence metadata scans must run only on the current licence route' );
$assert( false !== strpos( $ux, 'ufsc_production_licence_season_query_context' ), 'front licence queries must use a strict database season predicate' );
$assert( false === strpos( $admin_compat, "add_action( 'plugins_loaded', 'ufsc_production_prepare_optional_unique_identifiers'" ), 'identifier schema repair must not run on every public plugin load' );
$assert( false !== strpos( $admin_compat, "add_action( 'admin_init', 'ufsc_production_prepare_optional_unique_identifiers'" ), 'identifier repair must remain available in admin' );
$assert( false !== strpos( $admin_compat, 'ufsc_optional_identifier_repair_version' ), 'identifier repair must be version-idempotent' );
$assert( false !== strpos( $traceability, "! is_admin()" ), 'traceability schema checks must return before public rendering' );

if ( $failures ) {
    fwrite( STDERR, "Global front performance safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Global front performance safeguards: OK\n";
