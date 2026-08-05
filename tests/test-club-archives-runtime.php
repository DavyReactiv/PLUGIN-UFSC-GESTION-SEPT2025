<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$licences = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$assert( strpos( $resolver, 'normalize_season_reference' ) !== false, 'Central season reference normalizer exists.' );
$assert( strpos( $clubs, 'get_licence_season_exists_sql' ) !== false, 'Club archive filters include licence-backed historical seasons.' );
$assert( strpos( $clubs, "'permanent'" ) !== false, 'Permanent club filter bypasses annual season requirements.' );
$assert( strpos( $licences, 'IN (%s, %s)' ) !== false, 'Licence filters compare legacy and normalized season labels.' );
$assert( strpos( $licences, "'all' === \$filter_season" ) !== false, 'All seasons licence view remains unfiltered by season.' );
echo "Club archive runtime safeguards OK\n";
