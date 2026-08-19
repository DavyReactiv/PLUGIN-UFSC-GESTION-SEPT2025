<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

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
function apply_filters( $hook, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function get_current_user_id() { return 42; }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }
function esc_html( $value ) { return $value; }
function ufsc_get_licence_product_id() { return 2934; }
function ufsc_club_can_manage_licences_for_season( $club_id, $season ) { return array( 'allowed' => true ); }
function ufsc_get_licences_table() { return 'wp_ufsc_licences'; }
function ufsc_get_licence_season_label( $source ) { return '2025-2026'; }
function ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role ) { return array( 'included' => false, 'bucket' => 'payante' ); }

final class Repro_Product {
    public function exists() { return true; }
    public function get_type() { return 'simple'; }
}
function wc_get_product( $product_id ) { return new Repro_Product(); }

final class Repro_Session {
    public $cookies = 0;
    public $saves = 0;
    public function set_customer_session_cookie( $set ) { if ( $set ) { $this->cookies++; } }
    public function save_data() { $this->saves++; }
}
final class Repro_Cart {
    public $items = array();
    public $session_sets = 0;
    public $totals = 0;
    public function get_cart() { return $this->items; }
    public function add_to_cart( $product_id, $quantity, $variation_id, $variation, $data ) {
        $key = 'cart-key-' . count( $this->items );
        $this->items[ $key ] = array_merge(
            array(
                'product_id' => $product_id,
                'quantity' => $quantity,
                'variation_id' => $variation_id,
                'variation' => $variation,
            ),
            $data
        );
        return $key;
    }
    public function set_quantity( $key, $quantity, $refresh_totals = true ) {
        if ( isset( $this->items[ $key ] ) ) { $this->items[ $key ]['quantity'] = $quantity; }
    }
    public function calculate_totals() { $this->totals++; }
    public function set_session() { $this->session_sets++; }
}
final class Repro_WC_Env {
    public $cart;
    public $session;
    public function __construct() { $this->cart = new Repro_Cart(); $this->session = new Repro_Session(); }
}
$repro_wc = new Repro_WC_Env();
function WC() { global $repro_wc; return $repro_wc; }

final class Repro_WPDB {
    public $source;
    public function __construct() {
        $this->source = (object) array(
            'id' => 100,
            'club_id' => 7,
            'nom' => 'TEST',
            'prenom' => 'PAYANT',
            'date_naissance' => '1990-01-01',
            'role' => 'adherent',
            'statut' => 'valide',
        );
    }
    public function prepare( $query, ...$args ) { return $query; }
    public function get_row( $query ) { return $this->source; }
    public function query( $query ) { return 0; }
}
$wpdb = new Repro_WPDB();

final class UFSC_Renewal_Service {
    public static function can_renew( $source, $club_id, $season ) { return true; }
    public static function sanitize_renewal_updates( $source, $input ) {
        return array(
            'data' => array(
                'nom' => $source->nom,
                'prenom' => $source->prenom,
                'role' => 'adherent',
                'fighter_level' => 'classe_c',
                'poids' => 75,
            ),
            'errors' => array(),
            'changes' => array(),
            'sensitive_identity_change' => false,
        );
    }
    public static function create_target_draft( $source, $club_id, $season, $data ) { return array( 'licence_id' => 200 ); }
    public static function person_key( $source, $club_id ) { return 'person-100'; }
}
final class UFSC_Identifier_Resolver {
    public static function read( $source, $type ) { return 'UFSC-TEST-100'; }
}

require_once dirname( __DIR__, 2 ) . '/inc/woocommerce/cart-integration.php';

$result = ufsc_add_renewal_sources_to_cart( 2934, 7, array( 100 ), '2026-2027', array() );
if ( ! empty( $result['included'] ) ) {
    fwrite( STDERR, "FAIL: paid renewal must not consume an included credit\n" );
    exit( 1 );
}
if ( array( 100 ) !== array_values( $result['added'] ) ) {
    fwrite( STDERR, 'FAIL: paid renewal was not reported as added: ' . json_encode( $result ) . "\n" );
    exit( 1 );
}
$items = WC()->cart->get_cart();
if ( 1 !== count( $items ) ) {
    fwrite( STDERR, "FAIL: expected exactly one WooCommerce cart item\n" );
    exit( 1 );
}
$item = reset( $items );
$checks = array(
    'product_id' => 2934,
    'quantity' => 1,
    'ufsc_action' => 'renew_licence',
    'ufsc_club_id' => 7,
    'ufsc_target_season' => '2026-2027',
    'ufsc_renew_from_licence_id' => 100,
    'ufsc_licence_id' => 200,
);
foreach ( $checks as $key => $expected ) {
    if ( ! array_key_exists( $key, $item ) || $item[ $key ] !== $expected ) {
        fwrite( STDERR, "FAIL: cart item {$key} mismatch\n" );
        exit( 1 );
    }
}
if ( WC()->cart->totals < 1 || WC()->cart->session_sets < 1 || WC()->session->cookies < 1 || WC()->session->saves < 1 ) {
    fwrite( STDERR, "FAIL: native WooCommerce cart/session persistence was not executed\n" );
    exit( 1 );
}

echo "OK: paid renewal created and persisted one quantity-one WooCommerce cart item with licence metadata\n";
