<?php
$root = dirname( __DIR__ );
$runtime = file_get_contents( $root . '/inc/common/production-traceability-schema-compat.php' );
$compat = file_get_contents( $root . '/inc/common/licence-create-schema-compat.php' );
$handler = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$sql_admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $runtime, "'/licence-create-schema-compat.php'" ), 'runtime must load licence schema compatibility guard' );
$assert( false !== strpos( $compat, "add_filter( 'ufsc_licence_fields'" ), 'licence guard must filter canonical field whitelist before persistence' );
$assert( false !== strpos( $compat, 'unset( $fields[\'numero_licence_delegataire\'] )' ), 'absent optional delegated number must be omitted from unsafe writes' );
$assert( false !== strpos( $compat, '0 === $licence_id' ), 'new licence detection must remain available' );
$assert( false !== strpos( $compat, 'ufsc_production_is_new_admin_sql_licence_request' ), 'legacy SQL admin create must be detected explicitly' );
$assert( false !== strpos( $compat, 'ufsc_production_is_existing_admin_licence_update_request' ), 'legacy SQL admin update must be detected explicitly' );
$assert( false !== strpos( $compat, "'ufsc_lc_licences'" ), 'admin create/update guard must stay scoped to the licences admin page' );
$assert( false !== strpos( $compat, '$licence_id > 0' ), 'admin update guard must require an existing licence id' );
$assert( false !== strpos( $compat, '! $delegated_enabled && \'\' === $delegated_number' ), 'only a disabled delegated licence with an empty number may be omitted' );
$assert( false !== strpos( $compat, "add_action( 'admin_post_ufsc_add_licence'" ), 'direct add route needs mutation-only schema preflight' );
$assert( false !== strpos( $compat, "add_action( 'admin_post_ufsc_save_licence'" ), 'unified save route needs mutation-only schema preflight' );
$assert( false !== strpos( $compat, "add_action( 'admin_init', 'ufsc_production_preflight_new_licence_identifier_schema', -100 )" ), 'legacy SQL admin create route needs the same schema preflight' );
$assert( false !== strpos( $compat, 'ufsc_production_prepare_optional_unique_identifiers' ), 'preflight must reuse canonical idempotent schema repair' );
$assert( false !== strpos( $handler, '$data[\'numero_licence_delegataire\']' ), 'test must remain anchored to the legacy optional field produced by the unified handler' );
$assert( false !== strpos( $handler, '$result = $wpdb->insert( $licences_table, $data )' ), 'test must remain anchored to the canonical licence insert' );
$assert( false !== strpos( $sql_admin, '$result = $wpdb->insert($t, $data_db)' ), 'test must remain anchored to the legacy SQL admin licence insert' );
$assert( false !== strpos( $sql_admin, '$result = $wpdb->update($t, $data_db, [$pk => $id])' ), 'test must remain anchored to the legacy admin licence update' );

// No destructive licence-row operation belongs in this compatibility layer.
$assert( false === stripos( $compat, 'DELETE FROM' ), 'compatibility guard must never delete licence rows' );
$assert( false === stripos( $compat, 'TRUNCATE' ), 'compatibility guard must never truncate data' );
$assert( false === strpos( $compat, '$wpdb->update' ), 'compatibility guard must not perform a parallel update' );
$assert( false === strpos( $compat, '$wpdb->delete' ), 'compatibility guard must not perform a parallel delete' );

fwrite( STDOUT, "Licence optional identifier safeguards OK\n" );
