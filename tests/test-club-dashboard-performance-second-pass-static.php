<?php
/** Static non-regression guards for the second club-dashboard performance pass. */
$root       = dirname( __DIR__ );
$front      = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$compliance = file_get_contents( $root . '/inc/common/compliance.php' );
$failures   = array();
$assert     = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $front, 'self::get_dashboard_renewable_count( $club_id, $previous_season, $season )' ), 'dashboard must use the cached exact renewal KPI' );
$assert( false !== strpos( $front, 'count( self::get_renewable_sources( $club_id, $source_season, $target_season ) )' ), 'renewal KPI must retain the canonical eligibility calculation' );
$assert( false !== strpos( $front, "add_action( 'ufsc_licence_updated', array( 'UFSC_Frontend_Shortcodes', 'clear_dashboard_renewable_count_cache' )" ), 'renewal KPI cache must be invalidated after licence updates' );
$assert( false !== strpos( $front, 'SELECT `id`, `{$role_column}` AS `role`' ), 'honorability KPI query must select only id and role' );
$assert( false === strpos( $front, "array( 'season' => \$current_season, 'page' => 1, 'per_page' => 2000 )" ), 'dashboard must not load 2,000 complete licence rows for honorability' );
$assert( false !== strpos( $compliance, "function_exists( 'wp_prime_option_caches' )" ), 'honorability option records must be bulk-primed when WordPress supports it' );
$assert( false !== strpos( $front, 'if ( ! $renewal_affiliation_done ) {' ), 'WooCommerce renewal diagnostics must be skipped for an active affiliation' );
$assert( false !== strpos( $front, "if ( 'stats' === \$requested_dashboard_section ) {" ), 'chart payload must remain limited to the statistics tab' );

if ( $failures ) {
    fwrite( STDERR, "Club dashboard second-pass safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}

echo "Club dashboard second-pass safeguards: OK\n";
