<?php
/** Runtime proof of the canonical quantity-one licence cart path and reload. */
define( 'ABSPATH', __DIR__ . '/' );
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function current_user_can() { return false; }
function get_current_user_id() { return 44; }
class WP_Error { private $code; private $message; public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; } public function get_error_message() { return $this->message; } public function get_error_code() { return $this->code; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class UFSC_Season_Service { public static function get_current_season() { return '2026-2027'; } }
function ufsc_club_can_manage_licences_for_season( $club_id, $season ) { return array( 'allowed' => 7 === (int) $club_id && '2026-2027' === $season, 'message' => 'inactive' ); }
function ufsc_get_licences_table() { return 'wp_licences'; }
function ufsc_get_licence_season_label( $row ) { return $row->season; }
function ufsc_is_licence_locked_for_club() { return false; }
function ufsc_role_requires_honorability() { return false; }
class UFSC_Cart_Test_WPDB {
    public $rows = array();
    public function prepare( $query, ...$values ) { return array( $query, $values ); }
    public function get_row( $prepared ) { $id = absint( $prepared[1][0] ?? 0 ); return $this->rows[ $id ] ?? null; }
}
$wpdb = new UFSC_Cart_Test_WPDB();
$base = array( 'club_id' => 7, 'season' => '2026-2027', 'nom' => 'Martin', 'prenom' => 'Alice', 'sexe' => 'F', 'date_naissance' => '1990-02-03', 'adresse' => '1 rue Test', 'code_postal' => '75001', 'ville' => 'Paris', 'pays' => 'France', 'email' => 'alice@example.test', 'telephone' => '0102030405', 'role' => 'adherent', 'is_included' => 0 );
$wpdb->rows[1] = (object) array_merge( array( 'id' => 1 ), $base );
$wpdb->rows[2] = (object) array_merge( array( 'id' => 2 ), $base, array( 'club_id' => 8 ) );
$wpdb->rows[3] = (object) array_merge( array( 'id' => 3 ), $base, array( 'season' => '2025-2026' ) );
$wpdb->rows[4] = (object) array_merge( array( 'id' => 4 ), $base, array( 'is_included' => 1 ) );
$GLOBALS['wpdb'] = $wpdb;
class UFSC_Cart_Test_Product {
    private $type; public function __construct( $type = 'simple' ) { $this->type = $type; }
    public function exists() { return true; } public function get_type() { return $this->type; }
    public function get_parent_id() { return 50; } public function get_variation_attributes() { return array( 'attribute_size' => 'adult' ); }
}
function wc_get_product( $id ) { return 99 === (int) $id ? new UFSC_Cart_Test_Product( 'variation' ) : new UFSC_Cart_Test_Product(); }
class UFSC_Cart_Test_Session {
    public $stored = array(); public $cookies = 0; public $saves = 0;
    public function set_customer_session_cookie( $set ) { if ( $set ) { $this->cookies++; } }
    public function save_data() { $this->saves++; }
}
class UFSC_Cart_Test_Cart {
    public $items = array(); private $session;
    public function __construct( $session ) { $this->session = $session; $this->items = $session->stored; }
    public function get_cart() { return $this->items; }
    public function add_to_cart( $product_id, $quantity, $variation_id, $variation, $data ) {
        $key = hash( 'sha256', (string) ( $data['ufsc_cart_identity'] ?? uniqid( '', true ) ) );
        if ( isset( $this->items[ $key ] ) ) { $this->items[ $key ]['quantity'] += $quantity; return $key; }
        $this->items[ $key ] = array_merge( array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'quantity' => $quantity ), $data ); return $key;
    }
    public function set_quantity( $key, $quantity ) { $this->items[ $key ]['quantity'] = $quantity; }
    public function calculate_totals() {}
    public function set_session() { $this->session->stored = $this->items; }
}
class UFSC_Cart_Test_WC { public $cart; public $session; public function __construct() { $this->session = new UFSC_Cart_Test_Session(); $this->cart = new UFSC_Cart_Test_Cart( $this->session ); } }
$GLOBALS['wc'] = new UFSC_Cart_Test_WC();
function WC() { return $GLOBALS['wc']; }
require dirname( __DIR__ ) . '/inc/woocommerce/cart-integration.php';
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$result = ufsc_add_licence_ids_to_cart_idempotent( 55, 7, array( 1 ), array( 'ufsc_operation_type' => 'new_licence' ) );
$assert( ! is_wp_error( $result ) && array( 1 ) === $result['added'] && 1 === count( WC()->cart->items ), 'valid server row adds one line' );
$line = reset( WC()->cart->items );
$assert( 1 === $line['quantity'] && 1 === $line['ufsc_licence_id'] && 7 === $line['ufsc_club_id'] && '2026-2027' === $line['ufsc_target_season'], 'server metadata and quantity are canonical' );

$result = ufsc_add_licence_ids_to_cart_idempotent( 55, 7, array( 1 ) );
$line = reset( WC()->cart->items );
$assert( array( 1 ) === $result['existing'] && 1 === count( WC()->cart->items ) && 1 === $line['quantity'], 'double submission cannot duplicate or increment the line' );

$result = ufsc_add_licence_ids_to_cart_idempotent( 55, 7, array( 4 ) );
$assert( array( 4 ) === $result['included'] && 1 === count( WC()->cart->items ), 'included licence creates no paid line' );
$assert( is_wp_error( ufsc_add_licence_ids_to_cart_idempotent( 55, 7, array( 2 ) ) ), 'foreign club licence is refused' );
$assert( is_wp_error( ufsc_add_licence_ids_to_cart_idempotent( 55, 7, array( 3 ) ) ), 'forged target season is refused' );

$reloaded = new UFSC_Cart_Test_Cart( WC()->session );
$reloaded_items = $reloaded->get_cart(); $reloaded_line = reset( $reloaded_items );
$assert( 1 === count( $reloaded_items ) && 1 === $reloaded_line['quantity'] && WC()->session->cookies > 0 && WC()->session->saves > 0, 'native session survives a new cart instance' );
$variation = ufsc_get_cart_product_arguments( 99 );
$assert( 50 === $variation['product_id'] && 99 === $variation['variation_id'] && 'adult' === $variation['variation']['attribute_size'], 'configured variation uses its parent and attributes' );
echo "Native licence cart runtime safeguards OK\n";
