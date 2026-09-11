<?php
/** Runtime regression: payable renewal must append to an existing native cart. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }

class WP_Error {
    private $code;
    private $message;
    public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function add_action() {}
function remove_action() { return true; }
function add_filter() {}
function remove_filter() { return true; }

final class Native_Product {
    public function exists() { return true; }
}
final class Native_Cart {
    public $items = array();
    public function get_cart() { return $this->items; }
    public function add_to_cart( $product_id, $quantity, $variation_id, $variation, $item_data ) {
        $key = 'line-' . count( $this->items );
        $this->items[ $key ] = array_merge(
            $item_data,
            array(
                'product_id'  => absint( $product_id ),
                'variation_id'=> absint( $variation_id ),
                'variation'   => (array) $variation,
                'quantity'    => absint( $quantity ),
                'data'        => new Native_Product(),
            )
        );
        return $key;
    }
    public function set_quantity() {
        fwrite( STDERR, "FAIL: set_quantity must not be called by native renewal handoff\n" );
        exit( 1 );
    }
}
final class Native_WC {
    public $cart;
    public function __construct() { $this->cart = new Native_Cart(); }
}
$GLOBALS['native_wc'] = new Native_WC();
function WC() { return $GLOBALS['native_wc']; }

function ufsc_get_licence_product_id() { return 55; }
function ufsc_get_cart_product_arguments( $product_id ) {
    return array( 'product_id' => absint( $product_id ), 'variation_id' => 0, 'variation' => array() );
}
function ufsc_ensure_woocommerce_cart() { return true; }
function ufsc_is_licence_linked_to_order() { return false; }
function ufsc_cart_has_renewal_item() { return false; }
function ufsc_renewal_recovery_missing_fields() { return array(); }
function ufsc_renewal_recovery_cart_metadata( $source, $target, $club_id, $season ) {
    return array(
        'ufsc_action'                => 'renew_licence',
        'ufsc_request_type'          => 'renewal',
        'ufsc_item_type'             => 'licence_renewal',
        'ufsc_operation_type'        => 'renewal',
        'ufsc_target_season'         => $season,
        'ufsc_season'                => $season,
        'ufsc_renew_from_licence_id' => absint( $source->id ?? 0 ),
        'ufsc_previous_licence_id'   => absint( $source->id ?? 0 ),
        'ufsc_person_identifier'     => 'person:7:100',
        'ufsc_fighter_level'         => 'classe_c',
        'ufsc_weight'                => '75',
        'quantity'                   => 1,
    );
}
function ufsc_renewal_recovery_cart_contains_target( $target_id ) {
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( absint( $item['ufsc_licence_id'] ?? 0 ) === absint( $target_id ) ) {
            return true;
        }
    }
    return false;
}

require dirname( __DIR__ ) . '/inc/common/renewal-native-cart-handoff.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

// Browser fallback must still resolve a final request when the clicked submitter is omitted.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action' => 'ufsc_bulk_renew_licences',
    'ufsc_renew_intent_fallback' => 'add_to_cart',
);
$assert( ufsc_renewal_native_handoff_is_final_request(), 'fallback add_to_cart intent was not recognized' );

$source = (object) array( 'id' => 100, 'club_id' => 7 );
$target = (object) array( 'id' => 200, 'club_id' => 7 );

// Existing normal licence line must survive the renewal append.
WC()->cart->items['existing-normal'] = array(
    'product_id' => 55,
    'ufsc_licence_id' => 150,
    'ufsc_license_ids' => array( 150 ),
    'quantity' => 1,
    'data' => new Native_Product(),
);

$result = ufsc_renewal_native_handoff_add_target( $source, $target, 7, '2026-2027' );
$assert( ! is_wp_error( $result ) && ! empty( $result['added'] ), 'renewal target was not appended to native cart' );
$assert( 2 === count( WC()->cart->items ), 'existing cart line was replaced or renewal line is missing' );
$assert( isset( WC()->cart->items['existing-normal'] ), 'existing normal licence disappeared' );
$assert( ufsc_renewal_recovery_cart_contains_target( 200 ), 'renewal target is not discoverable in cart after add' );

$renewal = end( WC()->cart->items );
$assert( 'renew_licence' === ( $renewal['ufsc_action'] ?? '' ), 'renewal action metadata missing' );
$assert( 200 === absint( $renewal['ufsc_licence_id'] ?? 0 ), 'target licence id missing from cart line' );
$assert( 100 === absint( $renewal['ufsc_renew_from_licence_id'] ?? 0 ), 'source licence id missing from cart line' );
$assert( 1 === absint( $renewal['quantity'] ?? 0 ), 'renewal quantity must be exactly one' );

// Retry must detect the existing target and never create a third line.
$retry = ufsc_renewal_native_handoff_add_target( $source, $target, 7, '2026-2027' );
$assert( ! is_wp_error( $retry ) && ! empty( $retry['existing'] ), 'retry did not reuse existing cart target' );
$assert( 2 === count( WC()->cart->items ), 'retry duplicated the renewal cart line' );

echo "Renewal native cart handoff runtime: OK\n";
