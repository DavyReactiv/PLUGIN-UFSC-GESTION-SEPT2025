<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$diagnostics = file_get_contents( $root . '/inc/common/diagnostics.php' );
$tables = file_get_contents( $root . '/inc/common/tables.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$common = file_get_contents( $root . '/inc/common/functions.php' );
$assert( strpos( $resolver, 'class UFSC_Storage_Resolver' ) !== false, 'Storage resolver exists.' );
$assert( strpos( $resolver, 'get_inventory' ) !== false, 'Resolver exposes read-only inventory.' );
$assert( strpos( $resolver, 'resolve_club_for_user' ) !== false, 'Resolver centralizes user-club links.' );
$assert( strpos( $diagnostics, 'critical_missing_tables' ) !== false && strpos( $diagnostics, 'optional_missing_tables' ) !== false, 'Diagnostic separates critical/optional tables.' );
$assert( strpos( $tables, 'ufsc_normalize_season_reference' ) !== false, 'Central season normalization function exists.' );
$assert( strpos( $common, 'ufsc_get_club_profile_value' ) !== false, 'Club profile alias helper exists.' );
$assert( strpos( $front, '$profile_address_line' ) !== false && strpos( $front, '$profile_phone' ) !== false && strpos( $front, '$profile_site' ) !== false, 'Front profile summary uses alias values.' );
echo "Restore history/KPI/profile static checks passed.\n";
