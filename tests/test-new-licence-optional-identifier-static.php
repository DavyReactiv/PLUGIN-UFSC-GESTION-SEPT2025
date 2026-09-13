<?php
$root = dirname( __DIR__ );
$runtime = file_get_contents( $root . '/inc/common/production-traceability-schema-compat.php' );
$compat = file_get_contents( $root . '/inc/common/licence-create-schema-compat.php' );
$handler = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $runtime, "'/licence-create-schema-compat.php'" ), 'runtime must load new licence schema compatibility guard' );
$assert( false !== strpos( $compat, "add_filter( 'ufsc_licence_fields'" ), 'new licence guard must filter canonical field whitelist before insert' );
$assert( false !== strpos( $compat, 'unset( $fields[\'numero_licence_delegataire\'] )' ), 'absent optional delegated number must be omitted from new insert' );
$assert( false !== strpos( $compat, '0 === $licence_id' ), 'compatibility guard must be creation-only' );
$assert( false !== strpos( $compat, "add_action( 'admin_post_ufsc_add_licence'" ), 'direct add route needs mutation-only schema preflight' );
$assert( false !== strpos( $compat, "add_action( 'admin_post_ufsc_save_licence'" ), 'unified save route needs mutation-only schema preflight' );
$assert( false !== strpos( $compat, 'ufsc_production_prepare_optional_unique_identifiers' ), 'preflight must reuse canonical idempotent schema repair' );
$assert( false !== strpos( $handler, '$data[\'numero_licence_delegataire\']' ), 'test must remain anchored to the legacy optional field produced by the handler' );
$assert( false !== strpos( $handler, '$result = $wpdb->insert( $licences_table, $data )' ), 'test must remain anchored to the canonical licence insert' );

// No destructive licence-row operation belongs in this compatibility layer.
$assert( false === stripos( $compat, 'DELETE FROM' ), 'compatibility guard must never delete licence rows' );
$assert( false === stripos( $compat, 'TRUNCATE' ), 'compatibility guard must never truncate data' );

fwrite( STDOUT, "New licence optional identifier safeguards OK\n" );
