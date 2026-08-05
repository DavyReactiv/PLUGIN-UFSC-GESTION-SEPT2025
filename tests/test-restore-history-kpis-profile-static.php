<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$season = file_get_contents( $root . '/includes/core/class-ufsc-season-service.php' );
$assert( false !== strpos( $season, 'normalize_parse_input' ), 'Season service normalizes legacy single end-year values.' );
$assert( false !== strpos( $season, "SEASON_START_MONTH = 8" ), 'Season rollover remains August.' );

$diagnostics = file_get_contents( $root . '/inc/common/diagnostics.php' );
$assert( false !== strpos( $diagnostics, 'critical_missing_tables' ), 'Configuration diagnostic exposes critical missing tables.' );
$assert( false !== strpos( $diagnostics, 'optional_missing_tables' ), 'Configuration diagnostic exposes optional missing tables.' );
$assert( false !== strpos( $diagnostics, '$wpdb->prefix' ), 'Configuration diagnostic uses the WordPress table prefix.' );

$migrations = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$assert( false !== strpos( $migrations, 'ensure_attestations_table' ), 'Attestations table is created idempotently by migrations.' );
$assert( false === stripos( $migrations, 'TRUNCATE TABLE' ), 'Migrations do not truncate tables.' );
$assert( false === stripos( $migrations, 'DROP TABLE' ), 'Migrations do not drop tables.' );

$list = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$assert( false !== strpos( $list, 'get_licence_season_exists_sql' ), 'Club season filters include licence-season history.' );
$assert( false !== strpos( $list, "'permanent'" ), 'Permanent clubs view/filter is available.' );
$assert( false !== strpos( $list, 'NOT EXISTS' ), 'Renewal view excludes current active annual affiliations without inner-joining away history.' );

$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$common = file_get_contents( $root . '/inc/common/functions.php' );
$assert( false !== strpos( $common, 'ufsc_get_club_profile_value' ), 'Club profile alias helper exists.' );
$assert( false !== strpos( $front, '$profile_address_line' ), 'Front summary renders a compatible address line.' );
$assert( false !== strpos( $front, '$profile_phone' ), 'Front summary renders compatible phone aliases.' );
$assert( false !== strpos( $front, '$profile_site' ), 'Front summary renders compatible website aliases.' );

fwrite( STDOUT, "Restore history/KPI/profile static checks passed.\n" );
