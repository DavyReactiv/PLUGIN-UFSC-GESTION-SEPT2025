<?php
/** Runtime proof for independent dossier/payment/validation resolution. */
define( 'ABSPATH', __DIR__ . '/' );
function __( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
$GLOBALS['orders'] = array();
class UFSC_Test_Order {
    private $status;
    public function __construct( $status ) { $this->status = $status; }
    public function get_status() { return $this->status; }
    public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
}
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
require dirname( __DIR__ ) . '/inc/common/licence-status.php';
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };

$draft = (object) array( 'statut' => '', 'status' => 'draft', 'paid' => 1, 'order_id' => 0, 'season' => '2026-2027' );
$state = ufsc_resolve_licence_business_state( $draft );
$assert( 'brouillon' === $state['dossier'] && ! $state['payment_received'] && ! $state['official'], 'draft and legacy paid flag stay unofficial and unpaid' );

$GLOBALS['orders'][11] = new UFSC_Test_Order( 'pending' );
$state = ufsc_resolve_licence_business_state( (object) array( 'statut' => 'en_attente', 'wc_order_id' => 11 ) );
$assert( 'requis' === $state['payment'] && ! $state['payment_received'], 'pending Woo order is not received' );

$GLOBALS['orders'][12] = new UFSC_Test_Order( 'processing' );
$state = ufsc_resolve_licence_business_state( (object) array( 'statut' => 'en_attente', 'wc_order_id' => 12, 'payment_source' => 'woocommerce', 'payment_status' => 'paid' ) );
$assert( 'recu' === $state['payment'] && $state['payment_received'], 'actually paid Woo order is received' );

$state = ufsc_resolve_licence_business_state( (object) array( 'statut' => 'en_attente', 'payment_source' => 'manuel', 'payment_status' => 'paye_manuellement' ) );
$assert( 'valide_manuellement' === $state['payment'] && $state['payment_received'], 'authorized manual trace is received' );

$GLOBALS['orders'][13] = new UFSC_Test_Order( 'refunded' );
$state = ufsc_resolve_licence_business_state( (object) array( 'statut' => 'valide', 'wc_order_id' => 13, 'payment_source' => 'woocommerce', 'payment_status' => 'refunded' ) );
$assert( 'rembourse' === $state['payment'] && ! $state['payment_received'] && $state['official'], 'refund reconciles payment without rewriting administrative validation' );

echo "Canonical licence business-state runtime safeguards OK\n";
