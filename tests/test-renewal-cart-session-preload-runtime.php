<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }

$GLOBALS['ufsc_preload_actions'] = array();
$GLOBALS['ufsc_preload_did'] = array();
$GLOBALS['ufsc_preload_load_calls'] = 0;

function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return (string) $value; }
function wp_unslash( $value ) { return $value; }
function add_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['ufsc_preload_actions'][] = array( $hook, $callback, $priority );
}
function did_action( $hook ) { return (int) ( $GLOBALS['ufsc_preload_did'][ $hook ] ?? 0 ); }
function ufsc_wc_log() {}

class UFSC_Preload_WC {
    public $cart = null;
}
$GLOBALS['ufsc_preload_wc'] = new UFSC_Preload_WC();
function WC() { return $GLOBALS['ufsc_preload_wc']; }
function wc_load_cart() {
    $GLOBALS['ufsc_preload_load_calls']++;
    WC()->cart = (object) array( 'bootstrapped' => true );
}

require dirname( __DIR__ ) . '/inc/common/renewal-cart-session-preload.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$registered = array_filter(
    $GLOBALS['ufsc_preload_actions'],
    static function ( $hook ) {
        return 'wp_loaded' === $hook[0] && 'ufsc_cart_preload_before_finalisation' === $hook[1] && 1 === $hook[2];
    }
);
$assert( 1 === count( $registered ), 'preloader is registered on wp_loaded priority 1' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'action' => 'ufsc_bulk_renew_licences', 'ufsc_renew_intent' => 'add_to_cart' );
$GLOBALS['ufsc_preload_wc']->cart = null;
$GLOBALS['ufsc_preload_did'] = array();
$GLOBALS['ufsc_preload_load_calls'] = 0;
ufsc_cart_preload_before_finalisation();
$assert( 1 === $GLOBALS['ufsc_preload_load_calls'], 'final renewal bootstraps Woo cart before admin-post handler' );
$assert( null !== WC()->cart, 'native cart exists after preload' );

$_POST = array( 'action' => 'ufsc_save_licence', 'ufsc_submit_action' => 'save_draft' );
$GLOBALS['ufsc_preload_wc']->cart = null;
$GLOBALS['ufsc_preload_load_calls'] = 0;
ufsc_cart_preload_before_finalisation();
$assert( 0 === $GLOBALS['ufsc_preload_load_calls'], 'saving a draft never initializes the payment cart' );

$_POST = array( 'action' => 'ufsc_update_licence', 'ufsc_submit_action' => 'add_to_cart' );
$GLOBALS['ufsc_preload_wc']->cart = null;
$GLOBALS['ufsc_preload_did']['woocommerce_load_cart_from_session'] = 1;
$GLOBALS['ufsc_preload_load_calls'] = 0;
ufsc_cart_preload_before_finalisation();
$assert( 0 === $GLOBALS['ufsc_preload_load_calls'], 'already restored Woo session is not loaded twice' );

$GLOBALS['ufsc_preload_did'] = array();
$GLOBALS['ufsc_preload_wc']->cart = (object) array( 'existing' => true );
$GLOBALS['ufsc_preload_load_calls'] = 0;
ufsc_cart_preload_before_finalisation();
$assert( 0 === $GLOBALS['ufsc_preload_load_calls'], 'an already-created cart keeps its pending native session callback' );

echo "Renewal cart early-session preload runtime: OK\n";
