<?php
/**
 * Static safeguards for 2026-2027 non-destructive renewals.
 */

$root = dirname( __DIR__ );
$season = file_get_contents( $root . '/inc/common/season.php' );
$hooks  = file_get_contents( $root . '/inc/woocommerce/hooks.php' );
$cart   = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$admin  = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$migration = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$archive = file_get_contents( $root . '/includes/core/class-ufsc-season-archive-manager.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( strpos( $season, "'numero_licence_delegataire'" ) === false, 'ASPTT licence numbers must not be copied on renewal.' );
$assert( strpos( $hooks, "'previous_licence_id'" ) !== false, 'Renewed licences must store previous_licence_id when the column exists.' );
$assert( strpos( $hooks, 'ufsc_get_renewed_licence_marker' ) !== false, 'Paid renewal processing must guard duplicate renewals.' );
$assert( strpos( $cart, 'previous_licence_id = %d' ) !== false, 'Cart duplicate guard must detect previous_licence_id lineage.' );
$assert( strpos( $admin, 'build_licence_season_condition' ) !== false, 'Admin licences list must centralize season filtering.' );
$assert( strpos( $admin, "REPLACE(l.{\$season_column}, '/', '-')" ) !== false, 'Admin season filter must normalize slash/dash labels.' );
$assert( strpos( $migration, 'ensure_licences_renewal_columns' ) !== false, 'Renewal columns migration must be idempotent.' );
$assert( substr_count( $migration, 'ADD COLUMN `previous_licence_id`' ) === 1, 'Only UFSC_DB_Migrations may add previous_licence_id once.' );
$assert( strpos( $migration, 'ufsc_affiliations_seasons' ) !== false && strpos( $migration, 'UNIQUE KEY `uniq_club_season`' ) !== false, 'Annual affiliation season table must be idempotent.' );
$assert( strpos( $archive, 'ensure_licences_renewal_columns' ) === false && strpos( $archive, 'ADD COLUMN `previous_licence_id`' ) === false, 'Archive manager must not recreate licence lineage columns.' );

echo "Renewal/season static safeguards OK\n";
