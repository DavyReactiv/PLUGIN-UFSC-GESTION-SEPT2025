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

echo "Production admin compatibility safeguards OK\n";
