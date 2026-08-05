<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$tables = file_get_contents( $root . '/inc/common/tables.php' );
$diagnostics = file_get_contents( $root . '/inc/common/diagnostics.php' );
$assert( strpos( $resolver, 'class UFSC_Storage_Resolver' ) !== false, 'Storage resolver class exists.' );
$assert( strpos( $resolver, "'legacy:ufsc_clubs'" ) !== false && strpos( $resolver, "'legacy:ufsc_licences'" ) !== false, 'Legacy club/licence table candidates are scanned.' );
$assert( strpos( $resolver, 'get_inventory' ) !== false && strpos( $resolver, 'SHOW TABLES LIKE %s' ) !== false, 'Read-only inventory lists matching UFSC tables.' );
$assert( strpos( $tables, 'UFSC_Storage_Resolver::get_clubs_table()' ) !== false, 'Table helpers delegate to storage resolver.' );
$assert( strpos( $diagnostics, 'Mode de compatibilité' ) !== false, 'Diagnostic reports compatibility mode instead of false critical failure.' );
echo "Legacy storage resolution runtime safeguards OK\n";
