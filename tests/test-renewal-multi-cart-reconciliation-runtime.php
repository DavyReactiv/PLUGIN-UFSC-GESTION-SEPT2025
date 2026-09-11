<?php
/** Runtime regression: a stale same-source renewal must not block a reopened draft. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function add_action() {}

final class Multi_Cart {
    public $items = array();
    public function get_cart() { return $this->items; }
    public function remove_cart_item( $key ) {
        if ( ! isset( $this->items[ $key ] ) ) { return false; }
        unset( $this->items[ $key ] );
        return true;
    }
}
final class Multi_WC {
    public $cart;
    public function __construct() { $this->cart = new Multi_Cart(); }
}
$GLOBALS['multi_wc'] = new Multi_WC();
function WC() { return $GLOBALS['multi_wc']; }

final class Multi_WPDB {
    public $target;
    public function prepare( $sql ) { return $sql; }
    public function get_row() { return $this->target; }
}
$GLOBALS['wpdb'] = new Multi_WPDB();

function ufsc_renewal_draft_cart_is_final_request() { return true; }
function ufsc_get_licences_table() { return 'wp_licences'; }
function ufsc_renewal_draft_cart_source_id( $row ) { return absint( $row->previous_licence_id ?? 0 ); }
function ufsc_extract_licence_ids_from_cart_item( $item ) {
    $ids = array( absint( $item['ufsc_licence_id'] ?? 0 ) );
    $ids = array_merge( $ids, array_map( 'absint', (array) ( $item['ufsc_license_ids'] ?? array() ) ) );
    return array_values( array_unique( array_filter( $ids ) ) );
}
function ufsc_renewal_recovery_cart_contains_target( $target_id ) {
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( in_array( absint( $target_id ), ufsc_extract_licence_ids_from_cart_item( $item ), true ) ) { return true; }
    }
    return false;
}
function ufsc_wc_log() {}
final class UFSC_Season_Service {
    public static function get_current_season() { return '2026-2027'; }
}

require dirname( __DIR__ ) . '/inc/common/renewal-multi-cart-reconciliation.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'licence_id' => 200 );
$GLOBALS['wpdb']->target = (object) array(
    'id' => 200,
    'club_id' => 7,
    'previous_licence_id' => 100,
);

// Two unrelated products/licences must survive untouched.
WC()->cart->items['existing-normal'] = array(
    'product_id' => 55,
    'ufsc_licence_id' => 150,
    'ufsc_license_ids' => array( 150 ),
    'ufsc_club_id' => 7,
    'quantity' => 1,
);
WC()->cart->items['other-renewal'] = array(
    'product_id' => 55,
    'ufsc_action' => 'renew_licence',
    'ufsc_item_type' => 'licence_renewal',
    'ufsc_licence_id' => 201,
    'ufsc_license_ids' => array( 201 ),
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 101,
    'quantity' => 1,
);

// Old failed attempt for the same historical source: this is the only line to reconcile.
WC()->cart->items['stale-same-source'] = array(
    'product_id' => 55,
    'ufsc_action' => 'renew_licence',
    'ufsc_item_type' => 'licence_renewal',
    'ufsc_licence_id' => 199,
    'ufsc_license_ids' => array( 199 ),
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 100,
    'quantity' => 1,
);

ufsc_renewal_multi_cart_reconcile_before_draft_handoff( 7 );

$assert( 2 === count( WC()->cart->items ), 'only one stale line is removed from a populated cart' );
$assert( isset( WC()->cart->items['existing-normal'] ), 'ordinary existing licence remains in cart' );
$assert( isset( WC()->cart->items['other-renewal'] ), 'unrelated renewal remains in cart' );
$assert( ! isset( WC()->cart->items['stale-same-source'] ), 'stale same-source renewal no longer blocks current draft' );

// If the exact target is already in cart, reconciliation is a no-op.
WC()->cart->items['current-target'] = array(
    'product_id' => 55,
    'ufsc_action' => 'renew_licence',
    'ufsc_item_type' => 'licence_renewal',
    'ufsc_licence_id' => 200,
    'ufsc_license_ids' => array( 200 ),
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 100,
    'quantity' => 1,
);
WC()->cart->items['stale-after-target'] = array(
    'product_id' => 55,
    'ufsc_action' => 'renew_licence',
    'ufsc_item_type' => 'licence_renewal',
    'ufsc_licence_id' => 198,
    'ufsc_license_ids' => array( 198 ),
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 100,
    'quantity' => 1,
);
$count_before = count( WC()->cart->items );
ufsc_renewal_multi_cart_reconcile_before_draft_handoff( 7 );
$assert( $count_before === count( WC()->cart->items ), 'exact current target already present prevents any cart mutation' );

echo "Renewal multi-cart reconciliation runtime: OK\n";
