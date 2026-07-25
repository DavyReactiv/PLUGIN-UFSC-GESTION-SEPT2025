<?php
/**
 * Static safeguards for 2026-2027 non-destructive renewals.
 */

$root = dirname( __DIR__ );
$season = file_get_contents( $root . '/inc/common/season.php' );
$seasons_shim = file_get_contents( $root . '/inc/common/seasons.php' );
$hooks  = file_get_contents( $root . '/inc/woocommerce/hooks.php' );
$cart   = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$nominative = file_get_contents( $root . '/inc/woocommerce/nominative-licence-cart.php' );
$admin  = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$migration = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$archive = file_get_contents( $root . '/includes/core/class-ufsc-season-archive-manager.php' );
$manual = file_get_contents( $root . '/includes/admin/class-ufsc-affiliation-archive-admin.php' );

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
$assert( strpos( $archive, "'renew_affiliation' !== \$action" ) !== false, 'Only affiliation renewal order items may create annual affiliation rows.' );
$assert( strpos( $archive, 'ON DUPLICATE KEY UPDATE' ) !== false, 'Annual affiliation persistence must be idempotent.' );
$assert( strpos( $archive, 'num_affiliation = VALUES' ) === false, 'Paid renewals must not overwrite or copy ASPTT affiliation numbers.' );
$assert( strpos( $archive, 'ufsc-seasons-archives' ) !== false, 'Annual affiliation archives must be available in admin.' );
$assert( strpos( $archive, 'admin_post_ufsc_update_affiliation_number' ) !== false, 'ASPTT affiliation number editing must use a dedicated admin-post action.' );
$assert( strpos( $archive, 'CAP_GESTION_MANAGE' ) !== false, 'ASPTT affiliation number editing must require management capability.' );
$assert( strpos( $archive, 'check_admin_referer' ) !== false, 'ASPTT affiliation number editing must verify a nonce.' );
$assert( strpos( $archive, "array( 'id' => \$row_id )" ) !== false, 'ASPTT affiliation number editing must target one annual row only.' );
$assert( strpos( $seasons_shim, 'class-ufsc-affiliation-archive-admin.php' ) !== false, 'Manual annual affiliation admin flow must be loaded.' );
$assert( strpos( $manual, 'admin_post_ufsc_add_manual_affiliation' ) !== false, 'Manual affiliations must use a dedicated admin-post action.' );
$assert( strpos( $manual, 'CAP_GESTION_MANAGE' ) !== false && strpos( $manual, 'check_admin_referer' ) !== false, 'Manual affiliations must require management capability and nonce validation.' );
$assert( strpos( $manual, 'UFSC_Season_Archive_Manager::upsert_affiliation' ) !== false, 'Manual affiliations must use the same idempotent archive upsert.' );
$assert( strpos( $manual, "'wc_order_id'    => 0" ) !== false, 'Manual affiliations must remain distinguishable from WooCommerce orders.' );
$assert( strpos( $seasons_shim, 'nominative-licence-cart.php' ) !== false, 'Nominative licence cart safeguards must be loaded.' );
$assert( strpos( $nominative, 'woocommerce_check_cart_items' ) !== false, 'Anonymous licence lines must be blocked before checkout.' );
$assert( strpos( $nominative, '1 !== $quantity' ) !== false, 'Every licence must remain a separate quantity-one cart line.' );
$assert( strpos( $nominative, 'woocommerce_get_item_data' ) !== false, 'Licence identity must be visible in cart and checkout.' );
$assert( strpos( $nominative, '_ufsc_nominative_licence_id' ) !== false, 'Each Woo order line must keep a nominative licence snapshot.' );
$assert( strpos( $nominative, '_ufsc_nominative_request_type' ) !== false, 'Order trace must distinguish new licences from renewals.' );

 echo "Renewal/season static safeguards OK\n";
