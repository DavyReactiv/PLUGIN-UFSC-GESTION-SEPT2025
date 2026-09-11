<?php
/** Runtime reproduction: existing cart + failed renewal draft + retry. */
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
function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
function add_action() {}
function add_filter() {}
function ufsc_get_licence_product_id() { return 2934; }
function ufsc_is_licence_linked_to_order() { return false; }
function ufsc_cart_has_renewal_item() { return false; }
function ufsc_ensure_woocommerce_cart() { return true; }
function ufsc_persist_woocommerce_cart() { return true; }
function ufsc_extract_licence_ids_from_cart_item( $item ) {
    $ids = array();
    if ( ! empty( $item['ufsc_licence_id'] ) ) { $ids[] = absint( $item['ufsc_licence_id'] ); }
    if ( ! empty( $item['ufsc_license_ids'] ) ) { $ids = array_merge( $ids, array_map( 'absint', (array) $item['ufsc_license_ids'] ) ); }
    return array_values( array_unique( array_filter( $ids ) ) );
}

final class UFSC_Category_Repository {
    public static function normalize_weight( $value ) { return is_numeric( $value ) ? (float) $value : null; }
}
final class UFSC_Renewal_Service {
    public static function person_key( $source, $club_id ) { return 'person:' . absint( $club_id ) . ':' . absint( $source->id ?? 0 ); }
}

final class Recovery_Cart {
    public $items = array();
    public function get_cart() { return $this->items; }
}
final class Recovery_WC {
    public $cart;
    public function __construct() { $this->cart = new Recovery_Cart(); }
}
$GLOBALS['recovery_wc'] = new Recovery_WC();
function WC() { return $GLOBALS['recovery_wc']; }

function ufsc_add_licence_ids_to_cart_idempotent( $product_id, $club_id, $licence_ids, $extra = array() ) {
    $licence_id = absint( reset( $licence_ids ) );
    foreach ( WC()->cart->items as $item ) {
        if ( in_array( $licence_id, ufsc_extract_licence_ids_from_cart_item( $item ), true ) ) {
            return array( 'added' => array(), 'existing' => array( $licence_id ), 'included' => array() );
        }
    }
    WC()->cart->items[ 'renew-' . $licence_id ] = array_merge(
        $extra,
        array(
            'product_id'       => absint( $product_id ),
            'ufsc_club_id'     => absint( $club_id ),
            'ufsc_licence_id'  => $licence_id,
            'ufsc_license_ids' => array( $licence_id ),
            'quantity'         => 1,
        )
    );
    return array( 'added' => array( $licence_id ), 'existing' => array(), 'included' => array() );
}

require dirname( __DIR__ ) . '/inc/common/renewal-cart-recovery.php';

$source = (object) array( 'id' => 100, 'club_id' => 7 );
$target = (object) array(
    'id' => 200,
    'club_id' => 7,
    'nom' => 'TEST',
    'prenom' => 'RENEW',
    'sexe' => 'Homme',
    'date_naissance' => '1990-01-01',
    'adresse' => '1 rue Test',
    'code_postal' => '03100',
    'ville' => 'Montlucon',
    'pays' => 'France',
    'email' => 'test@example.test',
    'telephone' => '0600000000',
    'fighter_level' => 'classe_c',
    'poids' => '75',
    'is_included' => 0,
);

// Existing normal licence must remain untouched when the renewal is appended.
WC()->cart->items['existing-normal'] = array(
    'product_id' => 2934,
    'ufsc_licence_id' => 150,
    'ufsc_license_ids' => array( 150 ),
    'quantity' => 1,
);

$result = ufsc_renewal_recovery_add_target_to_cart( $source, $target, 7, '2026-2027' );
if ( is_wp_error( $result ) || empty( $result['added'] ) ) {
    fwrite( STDERR, "FAIL: renewal target was not appended to existing cart\n" );
    exit( 1 );
}
if ( 2 !== count( WC()->cart->items ) || ! isset( WC()->cart->items['existing-normal'] ) ) {
    fwrite( STDERR, "FAIL: adding renewal replaced or lost the existing cart line\n" );
    exit( 1 );
}
$renewal_item = WC()->cart->items['renew-200'] ?? array();
if ( 'renew_licence' !== ( $renewal_item['ufsc_action'] ?? '' ) || 100 !== ( $renewal_item['ufsc_renew_from_licence_id'] ?? 0 ) || 1 !== ( $renewal_item['quantity'] ?? 0 ) ) {
    fwrite( STDERR, "FAIL: renewal metadata or quantity is incorrect\n" );
    exit( 1 );
}

// Retry is idempotent: no third line.
$retry = ufsc_renewal_recovery_add_target_to_cart( $source, $target, 7, '2026-2027' );
if ( is_wp_error( $retry ) || empty( $retry['existing'] ) || 2 !== count( WC()->cart->items ) ) {
    fwrite( STDERR, "FAIL: renewal retry created a duplicate or failed\n" );
    exit( 1 );
}

// Missing legacy data must fail gracefully before WooCommerce, with the draft kept usable.
$incomplete = clone $target;
$incomplete->id = 201;
$incomplete->pays = '';
$missing = ufsc_renewal_recovery_add_target_to_cart( $source, $incomplete, 7, '2026-2027' );
if ( ! is_wp_error( $missing ) || false === strpos( $missing->get_error_message(), 'pays' ) || 2 !== count( WC()->cart->items ) ) {
    fwrite( STDERR, "FAIL: incomplete renewal did not fail gracefully with an explicit field\n" );
    exit( 1 );
}

// Cart wording: a licence item can never be displayed as an affiliation renewal.
$rows = array( array( 'key' => 'Demande', 'value' => 'Renouvellement d’affiliation' ) );
$fixed_new = ufsc_renewal_recovery_fix_cart_request_label( $rows, array( 'ufsc_licence_id' => 150 ) );
if ( 'Nouvelle licence' !== end( $fixed_new )['value'] ) {
    fwrite( STDERR, "FAIL: normal licence cart label is wrong\n" );
    exit( 1 );
}
$fixed_renewal = ufsc_renewal_recovery_fix_cart_request_label( $rows, array( 'ufsc_licence_id' => 200, 'ufsc_renew_from_licence_id' => 100 ) );
if ( 'Renouvellement de licence' !== end( $fixed_renewal )['value'] ) {
    fwrite( STDERR, "FAIL: renewal licence cart label is wrong\n" );
    exit( 1 );
}

echo "Renewal cart recovery runtime: OK\n";
