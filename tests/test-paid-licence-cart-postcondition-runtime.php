<?php
/** Runtime reproduction: 10/10 -> payable licence -> native Woo cart. */
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
    private $code;
    private $message;
    public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action() {}
function add_filter() {}
function sanitize_text_field( $value ) { return is_scalar( $value ) ? (string) $value : ''; }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $value ) { return rtrim( (string) $value, '/' ); }
function wc_get_cart_url() { return 'https://example.test/panier/'; }
function wp_get_referer() { return 'https://example.test/tableau-de-bord-club/'; }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function __( $message ) { return $message; }
function add_query_arg( $args, $url ) {
    $query = http_build_query( $args );
    return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
}
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['notices'][] = array( $type, $message ); }
function ufsc_wc_log( $event, $context = array(), $level = 'info' ) { $GLOBALS['logs'][] = array( $event, $context, $level ); }
function ufsc_get_licences_table() { return 'wp_ufsc_licences'; }
function ufsc_get_licence_product_resolution() { return array( 'configured_id' => 55, 'valid' => true ); }
function ufsc_get_licence_product_id() { return 55; }
function ufsc_get_licence_season_label() { return '2026-2027'; }
function ufsc_is_licence_linked_to_order() { return false; }
function ufsc_ensure_woocommerce_cart() { return true; }
function ufsc_extract_licence_ids_from_cart_item( $item ) {
    $ids = array();
    if ( ! empty( $item['ufsc_licence_id'] ) ) { $ids[] = (int) $item['ufsc_licence_id']; }
    foreach ( (array) ( $item['ufsc_license_ids'] ?? array() ) as $id ) { $ids[] = (int) $id; }
    return array_values( array_unique( array_filter( $ids ) ) );
}

class UFSC_Test_Cart {
    public $items = array();
    public function get_cart() { return $this->items; }
}
class UFSC_Test_WC {
    public $cart;
    public function __construct() { $this->cart = new UFSC_Test_Cart(); }
}
$GLOBALS['wc'] = new UFSC_Test_WC();
function WC() { return $GLOBALS['wc']; }

class UFSC_Test_WPDB {
    public $row;
    public $updates = array();
    public function prepare( $sql ) { return $sql; }
    public function get_row() { return $this->row; }
    public function update( $table, $data, $where ) { $this->updates[] = array( $table, $data, $where ); return 1; }
}
$GLOBALS['wpdb'] = new UFSC_Test_WPDB();

class UFSC_Licence_Status {
    public static $updates = array();
    public static function update_status_columns( $table, $where, $status, $formats = array() ) {
        self::$updates[] = array( $table, $where, $status, $formats );
        return 1;
    }
}

$GLOBALS['helper_fail'] = false;
$GLOBALS['helper_calls'] = 0;
$GLOBALS['persist_calls'] = 0;
function ufsc_add_licence_ids_to_cart_idempotent( $product_id, $club_id, $licence_ids, $extra = array() ) {
    $GLOBALS['helper_calls']++;
    if ( $GLOBALS['helper_fail'] ) {
        return new WP_Error( 'add_failed', 'Ajout panier impossible.' );
    }
    $id = (int) reset( $licence_ids );
    WC()->cart->items['line-' . $id] = array(
        'product_id'       => (int) $product_id,
        'quantity'         => 1,
        'ufsc_club_id'     => (int) $club_id,
        'ufsc_licence_id'  => $id,
        'ufsc_license_ids' => array( $id ),
    );
    return array( 'added' => array( $id ), 'existing' => array(), 'included' => array() );
}
function ufsc_persist_woocommerce_cart() { $GLOBALS['persist_calls']++; return true; }

require dirname( __DIR__ ) . '/inc/common/paid-licence-cart-postcondition.php';

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action' => 'ufsc_save_licence',
    'licence_id' => 11,
    'ufsc_submit_action' => 'add_to_cart',
);
$GLOBALS['wpdb']->row = (object) array( 'id' => 11, 'club_id' => 7, 'is_included' => 0, 'statut' => 'en_attente' );
$result = ufsc_paid_cart_enforce_redirect_postcondition( wc_get_cart_url(), 302 );
$assert( wc_get_cart_url() === $result, 'payable request keeps cart redirect' );
$assert( 1 === $GLOBALS['helper_calls'], 'missing payable line is re-added exactly once' );
$assert( ufsc_paid_cart_contains_licence( 11 ), 'payable licence exists in native cart before redirect' );
$assert( 1 === $GLOBALS['persist_calls'], 'cart session is persisted after status hooks' );

// Included licence must never be forced into a payable cart line.
WC()->cart->items = array();
$GLOBALS['helper_calls'] = 0;
$GLOBALS['persist_calls'] = 0;
$GLOBALS['wpdb']->row = (object) array( 'id' => 11, 'club_id' => 7, 'is_included' => 1, 'statut' => 'en_attente' );
$result = ufsc_paid_cart_enforce_redirect_postcondition( wc_get_cart_url(), 302 );
$assert( wc_get_cart_url() === $result, 'included request redirect is left untouched' );
$assert( 0 === $GLOBALS['helper_calls'], 'included licence never creates a paid line' );
$assert( 0 === $GLOBALS['persist_calls'], 'included branch is not repersisted by paid guard' );

// Failed payable handoff remains editable instead of becoming stuck en_attente.
WC()->cart->items = array();
$GLOBALS['helper_calls'] = 0;
$GLOBALS['persist_calls'] = 0;
$GLOBALS['helper_fail'] = true;
UFSC_Licence_Status::$updates = array();
$GLOBALS['wpdb']->row = (object) array( 'id' => 11, 'club_id' => 7, 'is_included' => 0, 'statut' => 'en_attente' );
$result = ufsc_paid_cart_enforce_redirect_postcondition( wc_get_cart_url(), 302 );
$assert( false !== strpos( $result, 'tableau-de-bord-club' ) && false !== strpos( $result, 'ufsc_error=' ), 'failed handoff returns to licence workflow with error' );
$assert( ! empty( UFSC_Licence_Status::$updates ) && 'brouillon' === UFSC_Licence_Status::$updates[0][2], 'failed unpaid handoff is reverted to draft' );
$assert( ! ufsc_paid_cart_contains_licence( 11 ), 'failed handoff does not fabricate a cart line' );

echo "Paid licence cart postcondition runtime: OK\n";
