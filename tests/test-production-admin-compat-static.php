<?php
$root = dirname( __DIR__ );
$compat = file_get_contents( $root . '/inc/common/production-admin-compat.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "'/production-admin-compat.php'" ), 'runtime must load production admin compatibility fixes' );
$assert( false !== strpos( $compat, 'page=ufsc-woocommerce-settings' ), 'compat must detect the obsolete Woo settings slug' );
$assert( false !== strpos( $compat, "page=ufsc-woocommerce'" ), 'compat must redirect to the canonical Woo settings slug' );
$assert( false !== strpos( $compat, '$same_season_sql = "l2.`{$season_column}` <=> l.`{$season_column}`";' ), 'duplicate identity comparison must include same-season equality' );
$assert( false !== strpos( $compat, "'ufsc_lc_licences'" ), 'duplicate fix must be scoped to canonical licence admin pages' );
$assert( false !== strpos( $compat, "add_action( 'admin_init'" ), 'season column resolution must happen outside the global query filter' );
$assert( false !== strpos( $compat, "add_filter( 'query'" ), 'duplicate compatibility must affect generated SQL before execution' );
$assert( false !== strpos( $compat, "function_exists( 'wc_get_cart_item_data_hash' )" ), 'admin-post cart bootstrap must verify the Woo cart hash helper' );
$assert( false !== strpos( $compat, "includes/wc-cart-functions.php" ), 'admin-post cart bootstrap must load native Woo cart functions' );
$assert( false !== strpos( $compat, "'ufsc_bulk_renew_licences'" ), 'paid renewal admin-post must be covered by Woo cart bootstrap' );
$assert( false !== strpos( $compat, "'ufsc_save_licence'" ), 'new licence save/finalization must be covered by Woo cart bootstrap' );
$assert( false !== strpos( $compat, "'ufsc_journey_finalize_licence'" ), 'journey finalization must be covered by Woo cart bootstrap' );
$assert( false !== strpos( $compat, "'Renouvellement de licence'" ), 'cart request metadata must identify licence renewals correctly' );
$assert( false !== strpos( $compat, "'Nouvelle licence'" ), 'cart request metadata must identify new licences correctly' );
$assert( false !== strpos( $compat, "add_filter( 'woocommerce_get_item_data'" ), 'cart metadata correction must run on Woo item display' );
$assert( false !== strpos( $compat, "remove_action( 'woocommerce_before_calculate_totals', 'ufsc_apply_included_quota_to_cart', 10 )" ), 'legacy cart quota repricer must be disabled after canonical quota allocation' );
$assert( false !== strpos( $compat, "add_action( 'wp_loaded', 'ufsc_production_remove_legacy_cart_quota_repricing'" ), 'legacy quota repricer removal must run after Woo hooks are registered' );
$assert( false !== strpos( $compat, "'numero_licence_delegataire'" ), 'preflight must normalize optional delegated licence identifiers' );
$assert( false !== strpos( $compat, "'num_affiliation'" ), 'preflight must normalize optional affiliation identifiers' );
$assert( false !== strpos( $compat, 'SET `{$column}` = NULL' ), 'preflight must represent missing optional identifiers as SQL NULL' );
$assert( false !== strpos( $compat, "register_activation_hook( UFSC_CL_DIR . 'ufsc-clubs-licences-sql.php'" ), 'identifier normalization must run before activation migrations' );
$assert( false !== strpos( $compat, 'ufsc_production_strict_datetime_query_compat' ), 'strict MySQL date compatibility must be registered' );
$assert( false !== strpos( $compat, 'TRIM(CAST({$column} AS CHAR))' ), 'deleted_at compatibility must avoid direct DATETIME empty-string comparison' );
$assert( false !== strpos( $compat, 'date_affiliation|date_validation|date_asptt' ), 'legacy affiliation date evidence must avoid DATE() on blank legacy values' );

echo "Production admin compatibility safeguards OK\n";
