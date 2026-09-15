<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-licences-export-season-filter.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

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
$assert( false !== strpos( $module, "select.name='filter_season'" ), 'season selector posts through existing filter_season field' );
$assert( false !== strpos( $module, "option.selected=season===currentSeason" ), 'current season is selected by default' );
$assert( false !== strpos( $module, "all.value='all'" ), 'all seasons remains an explicit opt-in choice' );
$assert( false !== strpos( $loader, "class-licences-export-season-filter.php" ), 'season filter module is loaded' );
$assert( false !== strpos( $loader, 'UFSC_Licences_Export_Season_Filter::init();' ), 'season filter module is initialized' );

// UI-only/read-only: no licence or schema mutation.
$assert( 0 === preg_match( '/\$wpdb\s*->\s*(insert|update|delete|replace)\s*\(/i', $module ), 'season filter performs no database row mutation' );
$assert( false === stripos( $module, 'ALTER TABLE' ), 'season filter performs no schema mutation' );
$assert( false === stripos( $module, 'DELETE FROM' ), 'season filter contains no DELETE SQL' );
$assert( false === stripos( $module, 'TRUNCATE' ), 'season filter contains no TRUNCATE SQL' );

if ( $failed ) { exit( 1 ); }
echo "OK licences export season filter\n";
