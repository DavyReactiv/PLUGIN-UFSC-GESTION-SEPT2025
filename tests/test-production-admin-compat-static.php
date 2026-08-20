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
$assert( false !== strpos( $compat, "page=ufsc-woocommerce-settings" ), 'compat must detect the obsolete Woo settings slug' );
$assert( false !== strpos( $compat, "page=ufsc-woocommerce'" ), 'compat must redirect to the canonical Woo settings slug' );
$assert( false !== strpos( $compat, "l2.`{$season_column}` <=> l.`{$season_column}`" ), 'duplicate identity comparison must include same-season equality' );
$assert( false !== strpos( $compat, "'ufsc_lc_licences'" ), 'duplicate fix must be scoped to canonical licence admin pages' );
$assert( false !== strpos( $compat, "add_action( 'admin_init'" ), 'season column resolution must happen outside the global query filter' );
$assert( false !== strpos( $compat, "add_filter( 'query'" ), 'duplicate compatibility must affect generated SQL before execution' );

echo "Production admin compatibility safeguards OK\n";
