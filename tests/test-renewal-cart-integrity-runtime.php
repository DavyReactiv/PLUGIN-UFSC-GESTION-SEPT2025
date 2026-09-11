<?php
/** Runtime reproduction of the DEV fatal: a renewal cart key survives but its Woo product data disappears before totals. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action'            => 'ufsc_bulk_renew_licences',
    'ufsc_renew_intent' => 'add_to_cart',
);

function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function add_action() {}
function add_filter() {}
function remove_filter() {}
function ufsc_wc_log() {}
function ufsc_is_licence_cart_item( $item ) {
    return ! empty( $item['ufsc_licence_id'] ) || ! empty( $item['ufsc_license_ids'] ) || 'renew_licence' === ( $item['ufsc_action'] ?? '' );
}
function ufsc_validate_licence_affiliation_cart_item( $item, $entrypoint ) {
    unset( $entrypoint );
    return empty( $item['ufsc_force_invalid'] );
}

final class Integrity_Product {
    private $id;
    public function __construct( $id ) { $this->id = (int) $id; }
    public function exists() { return $this->id > 0; }
    public function get_id() { return $this->id; }
}
function wc_get_product( $product_id ) { return $product_id > 0 ? new Integrity_Product( $product_id ) : false; }

final class Integrity_Cart {
    public $items = array();
    public function get_cart_contents() { return $this->items; }
    public function get_cart() { return $this->items; }
    public function set_cart_contents( $items ) { $this->items = $items; }
}
final class Integrity_WC {
    public $cart;
    public function __construct() { $this->cart = new Integrity_Cart(); }
}
$GLOBALS['integrity_wc'] = new Integrity_WC();
function WC() { return $GLOBALS['integrity_wc']; }

require dirname( __DIR__ ) . '/inc/common/renewal-cart-integrity.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$existing_product = new Integrity_Product( 2934 );
$renewal_product  = new Integrity_Product( 2934 );

// First licence already present in the cart: it must survive the renewal repair untouched.
WC()->cart->items['existing-line'] = array(
    'product_id'       => 2934,
    'data'             => $existing_product,
    'quantity'         => 1,
    'ufsc_licence_id'  => 150,
    'ufsc_license_ids' => array( 150 ),
);

// The renewal is initially a valid Woo line. Capture exactly what Woo created.
WC()->cart->items['renewal-key'] = array(
    'product_id'                   => 2934,
    'data'                         => $renewal_product,
    'quantity'                     => 1,
    'ufsc_action'                  => 'renew_licence',
    'ufsc_club_id'                 => 7,
    'ufsc_licence_id'              => 200,
    'ufsc_license_ids'             => array( 200 ),
    'ufsc_renew_from_licence_id'   => 100,
    'ufsc_target_season'           => '2026-2027',
);
ufsc_renewal_cart_integrity_capture_added_item( 'renewal-key' );

// Reproduce the DEV symptom just before calculate_totals(): key still exists,
// but its row has lost product_id/data and only quantity remains.
WC()->cart->items['renewal-key'] = array( 'quantity' => 1 );

// Also add an unrelated unidentifiable ghost. Only this malformed row may be dropped.
WC()->cart->items['orphan-key'] = array( 'quantity' => 1 );

ufsc_renewal_cart_integrity_repair_before_totals( WC()->cart );

$assert( isset( WC()->cart->items['existing-line'] ), 'existing licence cart line was lost' );
$assert( WC()->cart->items['existing-line']['data'] === $existing_product, 'existing valid Woo product object was replaced' );
$assert( isset( WC()->cart->items['renewal-key'] ), 'renewal row was not restored from the post-add snapshot' );
$assert( 2934 === ( WC()->cart->items['renewal-key']['product_id'] ?? 0 ), 'restored renewal product id is missing' );
$assert( is_object( WC()->cart->items['renewal-key']['data'] ?? null ), 'restored renewal Woo product data is missing' );
$assert( 200 === ( WC()->cart->items['renewal-key']['ufsc_licence_id'] ?? 0 ), 'restored renewal licence metadata is missing' );
$assert( 100 === ( WC()->cart->items['renewal-key']['ufsc_renew_from_licence_id'] ?? 0 ), 'restored source licence metadata is missing' );
$assert( 1 === ( WC()->cart->items['renewal-key']['quantity'] ?? 0 ), 'restored renewal quantity is not one' );
$assert( ! isset( WC()->cart->items['orphan-key'] ), 'unrecoverable quantity-only ghost was not removed' );
$assert( 2 === count( WC()->cart->items ), 'cart repair changed valid line count unexpectedly' );

// A row that still has a product id but lost `data` must be rehydrated rather than removed.
WC()->cart->items['rehydrate-key'] = array(
    'product_id'       => 2934,
    'quantity'         => 1,
    'ufsc_licence_id'  => 201,
    'ufsc_license_ids' => array( 201 ),
);
ufsc_renewal_cart_integrity_repair_before_totals( WC()->cart );
$assert( isset( WC()->cart->items['rehydrate-key'] ), 'recoverable product-id row was removed' );
$assert( is_object( WC()->cart->items['rehydrate-key']['data'] ?? null ), 'Woo product was not rehydrated from product id' );

// Session restoration now uses WooCommerce's boolean pre-remove contract.
$valid_session_item = array(
    'product_id'       => 2934,
    'ufsc_licence_id'  => 202,
    'ufsc_license_ids' => array( 202 ),
);
$remove_valid = ufsc_renewal_cart_integrity_pre_remove_session_item( false, 'valid', $valid_session_item, new Integrity_Product( 2934 ) );
$assert( false === $remove_valid, 'valid UFSC licence would be removed from Woo session' );

$invalid_session_item = $valid_session_item;
$invalid_session_item['ufsc_force_invalid'] = 1;
$remove_invalid = ufsc_renewal_cart_integrity_pre_remove_session_item( false, 'invalid', $invalid_session_item, new Integrity_Product( 2934 ) );
$assert( true === $remove_invalid, 'invalid UFSC licence was not marked for supported Woo session removal' );

$regular_item = array( 'product_id' => 999 );
$remove_regular = ufsc_renewal_cart_integrity_pre_remove_session_item( false, 'regular', $regular_item, new Integrity_Product( 999 ) );
$assert( false === $remove_regular, 'non-UFSC cart item was affected by renewal integrity guard' );

echo "Renewal cart integrity debug reproduction: OK\n";
