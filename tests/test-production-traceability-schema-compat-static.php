<?php
$root   = dirname( __DIR__ );
$compat = file_get_contents( $root . '/inc/common/production-traceability-schema-compat.php' );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "'/production-traceability-schema-compat.php'" ), 'feature composition must load the production traceability schema compatibility layer' );
$assert( false !== strpos( $compat, "remove_action( 'init', 'ufsc_ensure_licence_traceability_columns', 6 )" ), 'legacy cached traceability installer must be removed' );
$assert( false !== strpos( $compat, "add_action( 'init', 'ufsc_production_ensure_licence_traceability_columns', 6 )" ), 'safe replacement installer must use the same init priority' );
$assert( false !== strpos( $compat, 'ufsc_production_traceability_columns( $table, true )' ), 'schema must be force-refreshed before ALTER TABLE' );
$assert( false !== strpos( $compat, 'SHOW COLUMNS FROM `{$table}` LIKE %s' ), 'each apparently missing column must be checked live before ALTER' );
$assert( false !== strpos( $compat, "'submitted_at' => 'datetime NULL DEFAULT NULL'" ), 'submitted_at remains nullable and non-destructive' );
$assert( false !== strpos( $compat, "'validated_at' => 'datetime NULL DEFAULT NULL'" ), 'validated_at remains nullable and non-destructive' );
$assert( false !== strpos( $compat, "'validated_by' => 'bigint(20) unsigned NULL DEFAULT NULL'" ), 'validated_by remains nullable and non-destructive' );
$assert( false !== strpos( $compat, 'ufsc_flush_table_columns_cache( $table )' ), 'cache flush must target only the licence table' );
$assert( false === strpos( $compat, 'DROP COLUMN' ), 'hotfix must never remove columns' );
$assert( false === strpos( $compat, 'UPDATE `' ), 'hotfix must never rewrite licence rows' );
$assert( false === strpos( $compat, 'UFSC_Unified_Handlers::' ), 'hotfix must not call licence business handlers' );
$assert( false === strpos( $compat, 'WC()->cart' ), 'hotfix must not touch WooCommerce cart logic' );

echo "Production traceability schema cache safeguards OK\n";
