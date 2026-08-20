<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production compatibility for the licence traceability schema.
 *
 * The historical traceability installer uses the shared column cache. After a
 * deployment, that cache can briefly contain the pre-migration schema while the
 * database already contains the new columns. In that situation the legacy init
 * hook attempts the same ALTER TABLE statements again and Query Monitor reports
 * harmless but noisy "Duplicate column name" errors.
 *
 * This compatibility layer replaces only that schema-check hook. It never
 * rewrites licence rows and never removes or renames a column.
 */

/**
 * Return the current licence table using the same resolver as the traceability
 * module.
 *
 * @return string
 */
function ufsc_production_traceability_table() {
    if ( function_exists( 'ufsc_traceability_licence_table' ) ) {
        return (string) ufsc_traceability_licence_table();
    }
    if ( function_exists( 'ufsc_get_licences_table' ) ) {
        return (string) ufsc_get_licences_table();
    }
    return '';
}

/**
 * Read the table columns, optionally bypassing the shared schema cache.
 *
 * @param string $table Table name.
 * @param bool   $force Force a live schema read when the helper supports it.
 * @return string[]
 */
function ufsc_production_traceability_columns( $table, $force = false ) {
    global $wpdb;

    $table = function_exists( 'ufsc_sanitize_table_name' )
        ? ufsc_sanitize_table_name( $table )
        : preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );

    if ( ! $table ) {
        return array();
    }

    if ( function_exists( 'ufsc_table_columns' ) ) {
        return (array) ufsc_table_columns( $table, (bool) $force );
    }

    return (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
}

/**
 * Add only genuinely missing nullable traceability columns.
 *
 * A cached read is used first for the normal fast path. If one of the expected
 * columns appears to be missing, the schema is immediately re-read from MySQL
 * before any ALTER is attempted. This is what prevents the false duplicate
 * column errors seen after the production deployment.
 *
 * @return void
 */
function ufsc_production_ensure_licence_traceability_columns() {
    global $wpdb;

    $table = ufsc_production_traceability_table();
    $table = function_exists( 'ufsc_sanitize_table_name' )
        ? ufsc_sanitize_table_name( $table )
        : preg_replace( '/[^A-Za-z0-9_]/', '', (string) $table );

    if ( ! $table ) {
        return;
    }

    if ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $table ) ) {
        return;
    }

    $definitions = array(
        'submitted_at' => 'datetime NULL DEFAULT NULL',
        'validated_at' => 'datetime NULL DEFAULT NULL',
        'validated_by' => 'bigint(20) unsigned NULL DEFAULT NULL',
    );

    $columns = ufsc_production_traceability_columns( $table, false );
    $missing = array_diff( array_keys( $definitions ), $columns );
    if ( empty( $missing ) ) {
        return;
    }

    // Critical production safeguard: bypass any pre-migration transient/object
    // cache before deciding that ALTER TABLE is actually required.
    $columns = ufsc_production_traceability_columns( $table, true );
    $missing = array_diff( array_keys( $definitions ), $columns );
    if ( empty( $missing ) ) {
        return;
    }

    $changed = false;
    foreach ( $missing as $column ) {
        // Re-check the individual column live immediately before ALTER. This
        // also closes the small race window between concurrent requests.
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table}` LIKE %s",
                $column
            )
        );
        if ( $exists ) {
            continue;
        }

        $definition = $definitions[ $column ];
        $result     = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
        if ( false !== $result ) {
            $changed = true;
        }
    }

    if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
        // Flush only this table, never the whole WordPress object cache.
        ufsc_flush_table_columns_cache( $table );
    } elseif ( $changed && function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
}

/**
 * Replace the legacy cached schema installer after it has been registered by
 * club-dashboard-hardening.php. No other traceability hooks are touched.
 */
function ufsc_production_replace_traceability_schema_hook() {
    remove_action( 'init', 'ufsc_ensure_licence_traceability_columns', 6 );
    add_action( 'init', 'ufsc_production_ensure_licence_traceability_columns', 6 );
}
ufsc_production_replace_traceability_schema_hook();
