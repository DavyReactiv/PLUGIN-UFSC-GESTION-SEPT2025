<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-draft-cart-integrity-bridge.php' );
$loader = file_get_contents( $root . '/inc/common/renewal-intent-compat.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $loader, "'/renewal-draft-cart-integrity-bridge.php'" ), 'renewal draft integrity bridge is loaded' );
$assert( false !== strpos( $file, "'ufsc_save_licence', 'ufsc_update_licence'" ), 'only unified existing-licence save/update routes are observed' );
$assert( false !== strpos( $file, 'previous_licence_id' ), 'renewal lineage is required' );
$assert( false !== strpos( $file, "add_action( 'woocommerce_add_to_cart', 'ufsc_renewal_draft_integrity_capture_added_item', -999, 1 )" ), 'native cart row is snapshotted before later add-to-cart hooks' );
$assert( false !== strpos( $file, "add_action( 'woocommerce_add_to_cart', 'ufsc_renewal_draft_integrity_repair_after_add', 999999, 1 )" ), 'renewal row is repaired before add_to_cart returns' );
$assert( false !== strpos( $file, "add_action( 'woocommerce_before_calculate_totals', 'ufsc_renewal_draft_integrity_repair_before_totals', 2, 1 )" ), 'second repair guard runs before totals' );
$assert( false === strpos( $file, 'empty_cart(' ), 'existing cart is never emptied' );
$assert( false === preg_match( '/\bDELETE\s+FROM\b/i', $file ), 'no licence/history deletion is introduced' );
$assert( false === stripos( $file, 'ALTER TABLE' ), 'no schema migration is introduced' );
$assert( false === strpos( $file, 'ufsc_allocate_pack_credit' ), 'bridge does not alter pack quota' );
$assert( false === strpos( $file, 'wc_create_order' ), 'bridge does not create orders' );

echo "Renewal draft cart integrity bridge static safeguards: OK\n";
