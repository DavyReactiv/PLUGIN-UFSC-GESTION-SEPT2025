<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/compliance.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $file, 'function ufsc_pack_row_consumes_slot' ), 'quota must expose one canonical occupancy rule' );
$assert( false !== strpos( $file, "array( 'en_attente', 'valide', 'validee', 'validated' )" ), 'submitted/validated licences must consume quota' );
$assert( false !== strpos( $file, "array( 'pending', 'pending_payment', 'requis', 'required', 'paid', 'payee', 'completed', 'processing' )" ), 'paid/payable rows must never be inferred as included' );
$assert( false !== strpos( $file, 'ufsc_get_effective_pack_rows' ), 'dashboard and allocator must share the effective rows helper' );
$assert( substr_count( $file, 'ufsc_get_effective_pack_rows(' ) >= 3, 'allocator and KPI must use the same quota source' );
$assert( false !== strpos( $file, 'array_slice( $rows, 0, $limit )' ), 'KPI must cap included usage at the canonical limit' );
$assert( false !== strpos( $file, "'0000-00-00 00:00:00'" ), 'deleted history must not consume a current pack place' );
$assert( false === stripos( $file, 'TRUNCATE' ), 'quota consistency fix must never truncate data' );
$assert( false === stripos( $file, 'DELETE FROM' ), 'quota consistency fix must never delete licence rows' );

fwrite( STDOUT, "Pack quota pending consistency safeguards OK\n" );
