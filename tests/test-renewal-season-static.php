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
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$dashboard = file_get_contents( $root . '/templates/frontend/club-dashboard.php' );
$settings  = file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' );
$season_service = file_get_contents( $root . '/includes/core/class-ufsc-season-service.php' );
$admin_dashboard = file_get_contents( $root . '/includes/admin/class-admin-menu.php' );
$clubs_list = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$shortcodes = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );

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
$assert( strpos( $migration, 'ufsc_affiliation_seasons' ) === false && strpos( $archive, 'ufsc_affiliation_seasons' ) === false, 'Only the canonical plural affiliations table name may be used.' );
$assert( strpos( $archive, 'ALTER TABLE' ) === false && strpos( $archive, 'ensure_licences_renewal_columns' ) === false, 'Archive manager must not alter the licences table.' );
$assert( strpos( $archive, "class_exists( 'UFSC_DB_Migrations' )" ) !== false, 'Archive manager must tolerate migration class load order.' );
$assert( strpos( $bootstrap, "array( 'UFSC_Season_Archive_Manager', 'maybe_migrate' )" ) === false, 'Archive migration must not run twice during bootstrap.' );
$assert( strpos( $dashboard, '$renewal_affiliation_season = $current_season' ) !== false, 'Renewal must target the configured current season.' );
$assert( strpos( $dashboard, 'Renouveler mon affiliation %s' ) !== false, 'Historical club without a current annual affiliation must be offered renewal.' );
$assert( strpos( $dashboard, 'ufsc_get_affiliation_renewal_url' ) !== false && strpos( $dashboard, 'href="<?php echo esc_url( $renewal_url ); ?>"' ) !== false, 'Renewal button must open the configured product page.' );
$assert( strpos( $settings, 'wc_get_product( $product_id )' ) !== false && strpos( $settings, "'publish' === \$diagnostic['product_status']" ) !== false, 'Missing, unpublished or unavailable products must fail closed.' );
$assert( strpos( $settings, 'get_permalink( $product_id )' ) !== false, 'Renewal URL must come from the configured WooCommerce product.' );
$assert( strpos( $cart, "\$cart_item_data['ufsc_club_id'] = \$club_id" ) !== false && strpos( $cart, "\$cart_item_data['ufsc_target_season'] = \$season" ) !== false, 'Club and season context must survive the product-page cart flow.' );
$assert( strpos( $archive, 'ON DUPLICATE KEY UPDATE' ) !== false, 'Repeated payment hooks must upsert the unique annual affiliation.' );
$assert( strpos( $hooks, 'UFSC_Season_Archive_Manager::record_paid_renewal' ) !== false, 'Renewal payment must update the annual affiliation record.' );
$assert( strpos( $admin, 'Saison précédente : %s' ) !== false, 'Admin season filter must expose the previous season.' );
$assert( strpos( $admin, "COALESCE(l.season_end_year, 0) = 0 OR" ) === false, 'Unseasoned licences must not be counted as current-season licences.' );
$assert( substr_count( $season_service, 'self::shift_season(' ) === 5, 'Private shift_season calls must remain internal to the season service.' );
$assert( strpos( $settings, 'UFSC_Season_Service::shift_season' ) === false, 'Renewal URL must never call the private season shifter.' );
$assert( strpos( $settings, 'UFSC_Season_Service::get_current_season()' ) !== false, 'Renewal URL must target the configured current season.' );
$assert( strpos( $shortcodes, '$renewal_affiliation_season = $current_season' ) !== false && strpos( $dashboard, '$renewal_affiliation_season = $current_season' ) !== false, 'Both club dashboard renderers must target the current season.' );
$assert( strpos( $admin_dashboard, "REPLACE(`{\$season_column}`, '/', '-') = %s" ) !== false, 'Dashboard counters must use an explicit licence season predicate.' );
$assert( strpos( $admin_dashboard, 'ufsc_affiliations_seasons' ) !== false && strpos( $clubs_list, 'UFSC_Season_Archive_Manager::get_affiliation' ) !== false, 'Admin affiliation counts and rows must use annual affiliation storage.' );

echo "Renewal/season static safeguards OK\n";
