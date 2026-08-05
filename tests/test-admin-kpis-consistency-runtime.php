<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$dashboard = file_get_contents( $root . '/includes/admin/class-admin-menu.php' );
$diagnostics = file_get_contents( $root . '/inc/common/diagnostics.php' );
$assert( strpos( $dashboard, 'ufsc_get_configuration_diagnostic' ) !== false, 'Admin dashboard uses structured storage diagnostic.' );
$assert( strpos( $dashboard, 'get_dashboard_data_cached' ) !== false, 'Dashboard still renders KPI data after non-blocking diagnostics.' );
$assert( strpos( $dashboard, 'Ouvrir le diagnostic' ) !== false || strpos( $dashboard, 'Diagnostic détaillé' ) !== false, 'Dashboard links to detailed diagnostics.' );
$assert( strpos( $diagnostics, 'critical_missing_tables' ) !== false && strpos( $diagnostics, 'optional_missing_tables' ) !== false, 'Diagnostic separates critical and optional tables.' );
$assert( strpos( $diagnostics, 'inventory' ) !== false, 'Diagnostic carries real table inventory.' );
echo "Admin KPI consistency runtime safeguards OK\n";
