<?php
$root      = dirname( __DIR__ );
$module    = file_get_contents( $root . '/includes/admin/class-licences-export-season-filter.php' );
$canonical = file_get_contents( $root . '/includes/admin/class-licences-canonical-export.php' );
$loader    = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failed = false;
$assert = static function ( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failed = true;
    }
};

$assert( false !== strpos( $module, "UFSC_Season_Service::get_current_season()" ), 'filter uses canonical current season service' );
$assert( false !== strpos( $module, "UFSC_Season_Service::get_available_seasons()" ), 'filter exposes available seasons' );
$assert( false !== strpos( $module, "UFSC_Season_Service::get_previous_season()" ), 'previous season remains selectable' );
$assert( false !== strpos( $module, "seasonSelect.name='filter_season'" ), 'season selector posts through existing filter_season field' );
$assert( false !== strpos( $module, "option.selected=season===currentSeason" ), 'current season is selected by default' );
$assert( false !== strpos( $module, "all.value='all'" ), 'all seasons remains an explicit opt-in choice' );
$assert( false !== strpos( $module, "clubSelect.name='filter_club_affiliation'" ), 'club affiliation selector is present' );
$assert( false !== strpos( $module, '<option value="active" selected>Actifs — par défaut</option>' ), 'active clubs are selected by default' );
$assert( false !== strpos( $module, '<option value="inactive">Non actifs</option>' ), 'inactive clubs remain selectable' );
$assert( false !== strpos( $module, '<option value="all">Tous les clubs</option>' ), 'all clubs remains an explicit choice' );

$assert( false !== strpos( $canonical, ": 'active';" ), 'server defaults the club affiliation filter to active' );
$assert( false !== strpos( $canonical, "array( 'active', 'validated', 'valide' )" ), 'canonical active affiliation statuses are reused' );
$assert( false !== strpos( $canonical, 'get_annual_affiliations_table' ), 'annual affiliation storage resolver is reused' );
$assert( false !== strpos( $canonical, "REPLACE(a.season,'/','-')=%s" ), 'club active filter is scoped to the selected season' );
$assert( false !== strpos( $canonical, "? $active_sql : 'NOT ' . $active_sql" ), 'active and inactive server filters share the same source of truth' );

$assert( false !== strpos( $loader, "class-licences-export-season-filter.php" ), 'season filter module is loaded' );
$assert( false !== strpos( $loader, 'UFSC_Licences_Export_Season_Filter::init();' ), 'season filter module is initialized' );

// Export filters must remain read-only: no licence, club or schema mutation.
foreach ( array( $module, $canonical ) as $source ) {
    $assert( 0 === preg_match( '/\$wpdb\s*->\s*(insert|update|delete|replace)\s*\(/i', $source ), 'export filters perform no database row mutation' );
    $assert( false === stripos( $source, 'ALTER TABLE' ), 'export filters perform no schema mutation' );
    $assert( false === stripos( $source, 'DELETE FROM' ), 'export filters contain no DELETE SQL' );
    $assert( false === stripos( $source, 'TRUNCATE' ), 'export filters contain no TRUNCATE SQL' );
}

if ( $failed ) { exit( 1 ); }
echo "OK licences export season and active-club filters\n";
