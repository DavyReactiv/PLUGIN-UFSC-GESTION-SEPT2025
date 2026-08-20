<?php
$root = dirname( __DIR__ );
$compliance = file_get_contents( $root . '/inc/common/compliance.php' );
$season = file_get_contents( $root . '/inc/common/season.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $season, "array( 'paid_season', 'season', 'saison', 'season_end_year' )" ), 'canonical storage resolver must prefer paid_season' );
$assert( false !== strpos( $compliance, 'ufsc_get_pack_season_storage_context' ), 'pack allocation must use shared storage context' );
$assert( false !== strpos( $compliance, "array( 'paid_season', 'season', 'saison', 'season_end_year' )" ), 'pack allocation must support paid_season and historical season_end_year' );
$assert( false !== strpos( $compliance, '$season_value' ), 'pack SQL must use the normalized stored season value' );
$assert( false === strpos( $compliance, "$season_column = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );" ), 'legacy season/saison-only detector must be removed from pack flow' );

echo "Pack paid_season storage safeguards OK\n";
