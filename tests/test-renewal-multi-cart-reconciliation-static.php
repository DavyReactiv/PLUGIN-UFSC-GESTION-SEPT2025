<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/renewal-multi-cart-reconciliation.php' );
$compat = file_get_contents( $root . '/inc/common/renewal-intent-compat.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $compat, "'/renewal-multi-cart-reconciliation.php'" ), 'multi-cart reconciliation is loaded in renewal runtime' );
$assert( false !== strpos( $file, 'function ufsc_renewal_multi_cart_reconcile_target' ), 'one canonical reconciliation helper owns stale same-source cleanup' );
$assert( false !== strpos( $file, "add_action( 'ufsc_licence_updated', 'ufsc_renewal_multi_cart_reconcile_before_draft_handoff', 15, 1 )" ), 'draft reconciliation keeps its existing hook' );
$assert( false !== strpos( $file, "add_action( 'admin_post_ufsc_bulk_renew_licences', 'ufsc_renewal_multi_cart_reconcile_before_bulk_final_request', 0 )" ), 'bulk final renewal reconciles before the native priority-1 handler' );
$assert( false !== strpos( $file, "wp_verify_nonce( $nonce, 'ufsc_bulk_renew_licences_' . $club_id )" ), 'bulk pre-handoff cart mutation is nonce protected' );
$assert( false !== strpos( $file, "'ufsc_renew_from_licence_id'" ), 'same historical renewal source is identified' );
$assert( false !== strpos( $file, "'ufsc_club_id'" ), 'club scope is mandatory' );
$assert( false !== strpos( $file, "'ufsc_target_season'" ), 'season scope is mandatory' );
$assert( false !== strpos( $file, 'remove_cart_item' ), 'only targeted stale cart rows can be removed' );
$assert( false === strpos( $file, 'empty_cart(' ), 'cart is never emptied' );
$assert( 0 === preg_match( '/\bDELETE\s+FROM\b/i', $file ), 'no licence/history deletion is introduced' );
$assert( false === stripos( $file, 'ALTER TABLE' ), 'no schema migration is introduced' );
$assert( false === strpos( $file, 'ufsc_cart_max_licence_ids' ), 'no new business cart-size limit is introduced' );

echo "Renewal multi-cart reconciliation static safeguards: OK\n";
