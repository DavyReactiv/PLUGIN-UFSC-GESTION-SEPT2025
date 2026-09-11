<?php
/** Runtime regression: a reopened renewal draft must survive Woo row corruption and append to a populated cart. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action'             => 'ufsc_save_licence',
    'ufsc_submit_action' => 'add_to_cart',
    'licence_id'         => 200,
);

function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function add_action() {}
function ufsc_get_licences_table() { return 'wp_ufsc_licences'; }
function ufsc_renewal_draft_cart_source_id( $row ) { return absint( $row->previous_licence_id ?? 0 ); }
function ufsc_renewal_draft_cart_is_current_season( $row ) { return '2026-2027' === (string) ( $row->season ?? '' ); }
function ufsc_extract_licence_ids_from_cart_item( $item ) {
    $ids = array();
    if ( ! empty( $item['ufsc_licence_id'] ) ) { $ids[] = absint( $item['ufsc_licence_id'] ); }
    foreach ( (array) ( $item['ufsc_license_ids'] ?? array() ) as $id ) { $ids[] = absint( $id ); }
    return array_values( array_unique( array_filter( $ids ) ) );
}
function ufsc_wc_log() {}

final class Draft_Integrity_DB {
    public $row;
    public function __construct() {
        $this->row = (object) array(
            'id'                  => 200,
            'club_id'             => 7,
            'season'              => '2026-2027',
            'previous_licence_id' => 100,
        );
    }
    public function prepare( $sql, ...$args ) { unset( $args ); return $sql; }
    public function get_row( $sql ) { unset( $sql ); return $this->row; }
}
$GLOBALS['wpdb'] = new Draft_Integrity_DB();

final class Draft_Integrity_Product {
    private $id;
    public function __construct( $id ) { $this->id = (int) $id; }
    public function exists() { return $this->id > 0; }
}
final class Draft_Integrity_Cart {
    public $items = array();
    public function get_cart_contents() { return $this->items; }
    public function get_cart() { return $this->items; }
    public function set_cart_contents( $items ) { $this->items = $items; }
}
final class Draft_Integrity_WC {
    public $cart;
    public function __construct() { $this->cart = new Draft_Integrity_Cart(); }
}
$GLOBALS['draft_integrity_wc'] = new Draft_Integrity_WC();
function WC() { return $GLOBALS['draft_integrity_wc']; }

require dirname( __DIR__ ) . '/inc/common/renewal-draft-cart-integrity-bridge.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( ufsc_renewal_draft_integrity_is_request(), 'reopened current-season renewal draft was not recognized' );

$product_a = new Draft_Integrity_Product( 2934 );
$product_b = new Draft_Integrity_Product( 2934 );
$product_r = new Draft_Integrity_Product( 2934 );

// Two unrelated paid licence lines already in the cart must survive untouched.
WC()->cart->items['existing-a'] = array(
    'product_id' => 2934,
    'data' => $product_a,
    'quantity' => 1,
    'ufsc_licence_id' => 150,
    'ufsc_license_ids' => array( 150 ),
);
WC()->cart->items['existing-b'] = array(
    'product_id' => 2934,
    'data' => $product_b,
    'quantity' => 1,
    'ufsc_licence_id' => 151,
    'ufsc_license_ids' => array( 151 ),
);

// Native add_to_cart has just created the renewed target row.
WC()->cart->items['renewal-key'] = array(
    'product_id' => 2934,
    'data' => $product_r,
    'quantity' => 1,
    'ufsc_action' => 'renew_licence',
    'ufsc_item_type' => 'licence_renewal',
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 100,
    'ufsc_licence_id' => 200,
    'ufsc_license_ids' => array( 200 ),
);
ufsc_renewal_draft_integrity_capture_added_item( 'renewal-key' );

// Reproduce the DEV symptom before add_to_cart() returns to the UFSC caller.
WC()->cart->items['renewal-key'] = array( 'quantity' => 1 );
ufsc_renewal_draft_integrity_repair_after_add( 'renewal-key' );

$assert( 3 === count( WC()->cart->items ), 'renewal draft did not append as a third line' );
$assert( WC()->cart->items['existing-a']['data'] === $product_a, 'first existing line was modified' );
$assert( WC()->cart->items['existing-b']['data'] === $product_b, 'second existing line was modified' );
$assert( isset( WC()->cart->items['renewal-key'] ), 'renewal row disappeared' );
$assert( 200 === absint( WC()->cart->items['renewal-key']['ufsc_licence_id'] ?? 0 ), 'renewal target id was not restored' );
$assert( 100 === absint( WC()->cart->items['renewal-key']['ufsc_renew_from_licence_id'] ?? 0 ), 'renewal source id was not restored' );
$assert( is_object( WC()->cart->items['renewal-key']['data'] ?? null ), 'renewal Woo product object was not restored' );

// Ordinary current-season licence edits must remain outside this bridge.
$GLOBALS['wpdb']->row->previous_licence_id = 0;
$assert( ! ufsc_renewal_draft_integrity_is_request(), 'ordinary licence edit was incorrectly classified as a renewal draft' );

echo "Renewal draft cart integrity bridge runtime: OK\n";
