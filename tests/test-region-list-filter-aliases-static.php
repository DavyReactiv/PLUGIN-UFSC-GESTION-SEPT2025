<?php
$root = dirname( __DIR__ );
$clubs = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$licences = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-licences-list-table.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $clubs, 'ufsc_expand_region_access_values( $region_values )' ), 'club region filter must expand canonical/legacy aliases' );
$assert( false !== strpos( $clubs, 'region IN ({$placeholders})' ), 'club region filter must use IN for expanded aliases' );
$assert( false !== strpos( $licences, 'ufsc_expand_region_access_values( $region_values )' ), 'licence region filter must expand canonical/legacy aliases' );
$assert( false !== strpos( $licences, 'c.region IN ({$placeholders})' ), 'licence region filter must use IN for expanded aliases' );

$assert( false === strpos( $clubs, 'UPDATE ' ), 'club region filter fix must not mutate club records' );
$assert( false === strpos( $licences, 'UPDATE ' ), 'licence region filter fix must not mutate licence records' );
$assert( false === strpos( $clubs, 'WC()->cart' ), 'club region filter fix must not touch WooCommerce cart' );
$assert( false === strpos( $licences, 'WC()->cart' ), 'licence region filter fix must not touch WooCommerce cart' );

echo "Regional list filter alias safeguards OK\n";
