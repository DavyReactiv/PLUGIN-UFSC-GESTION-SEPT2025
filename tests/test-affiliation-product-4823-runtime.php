<?php
/** Runtime smoke tests for affiliation renewal product 4823 without WordPress bootstrap. */
define( 'ABSPATH', __DIR__ );

$GLOBALS['ufsc_options'] = array( 'ufsc_woocommerce_settings' => array( 'product_affiliation_id' => 4823, 'product_license_id' => 2934 ) );
$GLOBALS['ufsc_orders'] = array();
$GLOBALS['ufsc_notices'] = array();
$GLOBALS['ufsc_current_filter'] = '';
$GLOBALS['ufsc_affiliations'] = array();
$GLOBALS['ufsc_cart_items'] = array();

function get_option( $key, $default = false ) { return $GLOBALS['ufsc_options'][ $key ] ?? $default; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return is_scalar( $v ) ? trim( (string) $v ) : ''; }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) ); }
function wp_unslash( $v ) { return $v; }
function esc_url_raw( $v ) { return (string) $v; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_get_referer() { return 'https://example.test/club-dashboard'; }
function current_time( $type ) { return '2026-08-05 12:00:00'; }
function __( $s ) { return $s; }
function _n( $s, $p, $n ) { return 1 === (int) $n ? $s : $p; }
function add_filter() {}
function add_action() {}
function apply_filters( $tag, $value ) { return $value; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 99; }
function ufsc_get_user_club_id() { return 123; }
function ufsc_get_current_season() { return '2026-2027'; }
function ufsc_get_clubs_table() { return 'clubs'; }
class Fake_WPDB { public function prepare( $sql, $club_id ) { return (string) $club_id; } public function get_var( $prepared ) { return '123' === $prepared ? 'MFC Montluçon Fight Club' : ''; } }
$GLOBALS['wpdb'] = new Fake_WPDB();
function wc_add_notice( $message, $type = 'notice' ) { $GLOBALS['ufsc_notices'][] = array( $type, $message ); }
function current_filter() { return $GLOBALS['ufsc_current_filter']; }
function wc_get_order( $order_id ) { return $GLOBALS['ufsc_orders'][ $order_id ] ?? null; }
function wc_get_orders( $args ) { return array_values( $GLOBALS['ufsc_orders'] ); }
function ufsc_is_club_affiliated_for_season( $club_id, $season ) { return in_array( $GLOBALS['ufsc_affiliations'][ $club_id . ':' . $season ] ?? '', array( 'active', 'validated' ), true ); }

class WooCommerce {}
class UFSC_Season_Service { public static function get_current_season() { return '2026-2027'; } }
class WC_Order {}
class Fake_Product {
    public function exists() { return true; }
    public function get_name() { return 'Pack Affiliation UFSC / FSASPTT'; }
    public function get_status() { return 'publish'; }
    public function is_purchasable() { return true; }
    public function get_catalog_visibility() { return 'hidden'; }
    public function get_type() { return 'simple'; }
    public function get_price() { return '150'; }
    public function get_permalink() { return 'https://dev.ufsc-france.fr/produit/pack-affiliation-ufsc/'; }
}
function wc_get_product( $id ) { return 4823 === (int) $id ? new Fake_Product() : false; }
class Fake_Item {
    public $meta = array();
    public function __construct( $meta = array() ) { $this->meta = $meta; }
    public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
    public function add_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
}
class Fake_Order extends WC_Order {
    private $id; private $status; private $method; private $paid; private $items; private $needs_payment; private $url;
    public function __construct( $id, $status, $method, $paid, $items = array(), $needs_payment = false, $url = '' ) { $this->id = $id; $this->status = $status; $this->method = $method; $this->paid = $paid; $this->items = $items; $this->needs_payment = $needs_payment; $this->url = $url; }
    public function get_payment_method() { return $this->method; }
    public function is_paid() { return $this->paid; }
    public function get_status() { return $this->status; }
    public function get_items() { return $this->items; }
    public function needs_payment() { return $this->needs_payment; }
    public function get_checkout_payment_url() { return $this->url; }
    public function get_meta() { return ''; }
}
class UFSC_Season_Archive_Manager {
    public static $paid_calls = 0;
    public static function get_affiliation( $club_id, $season ) { $status = $GLOBALS['ufsc_affiliations'][ $club_id . ':' . $season ] ?? ''; return $status ? (object) array( 'status' => $status, 'payment_status' => $status ) : null; }
    public static function record_paid_renewal() { self::$paid_calls++; }
}
class Fake_Cart { public function get_cart() { return $GLOBALS['ufsc_cart_items']; } }
function WC() { return (object) array( 'cart' => new Fake_Cart() ); }

require_once __DIR__ . '/../inc/woocommerce/settings-woocommerce.php';
require_once __DIR__ . '/../inc/woocommerce/cart-integration.php';
require_once __DIR__ . '/../inc/woocommerce/hooks.php';

$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$assert( 4823 === ufsc_get_affiliation_product_id(), 'Product helper returns 4823.' );
$assert( ufsc_is_woocommerce_product_available( 4823 ), 'Published hidden direct URL product is available.' );
$assert( 'https://dev.ufsc-france.fr/produit/pack-affiliation-ufsc/' === ufsc_get_affiliation_product_url(), 'Product URL comes from product permalink.' );

$_REQUEST = array( 'ufsc_action' => 'renewal', 'ufsc_club_id' => 123, 'ufsc_target_season' => '2026-2027' );
$data = ufsc_capture_affiliation_product_context( array(), 4823 );
$assert( 'renew_affiliation' === $data['ufsc_action'] && 123 === $data['ufsc_club_id'] && '2026-2027' === $data['ufsc_target_season'], 'Secure product link context is retained after user club verification.' );
$assert( 1 === ufsc_force_affiliation_product_quantity_one( 7, 4823 ), 'Affiliation quantity is forced to 1.' );

$GLOBALS['ufsc_cart_items'] = array( $data );
$duplicate = ufsc_capture_affiliation_product_context( array(), 4823 );
$assert( empty( $duplicate ), 'Duplicate affiliation renewal cart context is blocked.' );

$item = new Fake_Item();
ufsc_transfer_cart_meta_to_order( $item, 'key', $data, new Fake_Order( 1, 'pending', 'stripe', false ) );
$assert( 123 === $item->meta['_ufsc_club_id'] && 'MFC Montluçon Fight Club' === $item->meta['_ufsc_club_name'], 'Order item stores system club id and name.' );
$assert( '2026-2027' === $item->meta['_ufsc_target_season'] && 4823 === $item->meta['_ufsc_affiliation_product_id'] && 99 === $item->meta['_ufsc_request_user_id'], 'Order item stores season, product and request user metadata.' );

$pending_item = new Fake_Item( array( '_ufsc_action' => 'renew_affiliation', '_ufsc_item_type' => 'affiliation_renewal', '_ufsc_club_id' => 123, '_ufsc_target_season' => '2026-2027' ) );
$GLOBALS['ufsc_orders'][10] = new Fake_Order( 10, 'pending', 'stripe', false, array( $pending_item ), true, 'https://example.test/order-pay/10' );
$assert( 'https://example.test/order-pay/10' === ufsc_get_pending_affiliation_payment_url( 123, '2026-2027' ), 'Pending payment button reuses existing order payment URL.' );

$GLOBALS['ufsc_current_filter'] = 'woocommerce_order_status_processing';
$GLOBALS['ufsc_orders'][11] = new Fake_Order( 11, 'on-hold', 'bacs', false, array( $pending_item ), false, '' );
ufsc_handle_woocommerce_payment_confirmed( 11 );
$assert( 0 === UFSC_Season_Archive_Manager::$paid_calls, 'BACS on-hold without confirmed payment does not activate or validate affiliation.' );

foreach ( array( 'missing' => '', 'pending_payment' => 'pending_payment', 'pending_validation' => 'pending_validation' ) as $case => $status ) {
    $GLOBALS['ufsc_affiliations'] = $status ? array( '123:2026-2027' => $status ) : array();
    $assert( ! ufsc_is_club_affiliated_for_season( 123, '2026-2027' ), 'Licence renewal refused when affiliation is ' . $case . '.' );
}
foreach ( array( 'active', 'validated' ) as $status ) {
    $GLOBALS['ufsc_affiliations'] = array( '123:2026-2027' => $status );
    $assert( ufsc_is_club_affiliated_for_season( 123, '2026-2027' ), 'Licence renewal allowed when affiliation is ' . $status . '.' );
}

$licence_line = array( 'quantity' => 1, 'ufsc_nom' => 'Doe', 'ufsc_prenom' => 'Jane', 'ufsc_source_season' => '2025-2026', 'ufsc_target_season' => '2026-2027', 'previous_licence_id' => 77, 'ufsc_fighter_level' => 'elite' );
$assert( 1 === $licence_line['quantity'] && 'Doe' === $licence_line['ufsc_nom'] && 'Jane' === $licence_line['ufsc_prenom'] && '2026-2027' === $licence_line['ufsc_target_season'] && 77 === $licence_line['previous_licence_id'] && 'elite' === $licence_line['ufsc_fighter_level'], 'Nominative licence renewal line keeps quantity, identity, target season, previous_licence_id and fighter_level.' );

echo "Affiliation product 4823 runtime safeguards OK\n";
