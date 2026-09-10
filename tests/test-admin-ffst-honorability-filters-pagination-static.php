<?php
/** Static safeguards for FFST/Honorability admin search, season filters and pagination. */
$root = dirname( __DIR__ );
$ffst = file_get_contents( $root . '/includes/admin/class-ffst-documents-admin.php' );
$honorability = file_get_contents( $root . '/inc/common/honorability-admin.php' );
$compliance = file_get_contents( $root . '/includes/admin/class-ffst-compliance-admin.php' );
$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $ffst, 'name="s"' ), 'FFST admin search field missing.' );
$assert( false !== strpos( $ffst, 'name="season"' ), 'FFST season selector missing.' );
$assert( false !== strpos( $ffst, 'name="per_page"' ), 'FFST page-size selector missing.' );
$assert( false !== strpos( $ffst, 'paginate_links(' ), 'FFST pagination missing.' );
$assert( false !== strpos( $ffst, 'get_clubs_page' ), 'FFST clubs must be paginated server-side.' );
$assert( false !== strpos( $ffst, 'ufsc_get_detected_season_column' ), 'FFST view must use canonical season-column detection.' );
$assert( false !== strpos( $ffst, "'paid_season', 'season', 'saison', 'season_end_year'" ), 'FFST season fallback order missing.' );

$assert( false !== strpos( $honorability, 'name="s"' ), 'Honorability search field missing.' );
$assert( false !== strpos( $honorability, '<select name="season">' ), 'Honorability season selector missing.' );
$assert( false !== strpos( $honorability, 'name="per_page"' ), 'Honorability page-size selector missing.' );
$assert( false !== strpos( $honorability, 'paginate_links(' ), 'Honorability pagination missing.' );
$assert( false !== strpos( $honorability, 'array_slice( $rows' ), 'Honorability records must be paginated before rendering.' );
$assert( false !== strpos( $honorability, 'ufsc_get_honorability_admin_seasons' ), 'Honorability season options must include stored seasons.' );
$assert( false === strpos( $honorability, 'update_option(' ), 'Honorability list/filter UI must remain read-only.' );

$assert( false !== strpos( $compliance, "isset( \$_GET['season'] )" ), 'FFST compliance panel must follow the selected season.' );
$assert( false !== strpos( $compliance, '.ufsc-ffst-compliance{width:auto;max-width:none' ), 'FFST compliance panel must not be capped to the old narrow width.' );
$assert( false !== strpos( $compliance, "self::redirect_back( 'saved', \$club_id, \$season )" ), 'FFST compliance save must preserve season context.' );
$assert( false !== strpos( $compliance, "self::redirect_back( 'uploaded', \$club_id, \$season )" ), 'FFST compliance upload must preserve season context.' );

if ( $failures ) {
    fwrite( STDERR, "Admin FFST/Honorability filter safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "Admin FFST/Honorability filter safeguards: OK\n";
