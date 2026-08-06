<?php
/** Runtime contract for read-only historical licence context. */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text, $domain = null ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function remove_accents( $value ) { return $value; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
class WP_Error { private $message; public function __construct( $code, $message ) { $this->message = $message; } public function get_error_message() { return $this->message; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$GLOBALS['gate_allowed'] = true;
function ufsc_club_can_manage_licences_for_season() { return array( 'allowed' => $GLOBALS['gate_allowed'] ); }
class UFSC_SQL { public static function get_settings() { return array( 'table_licences' => 'wp_licences' ); } }
class Test_WPDB {
    public $prefix = 'wp_'; public $renewed = null;
    public function get_col() { return array( 'id', 'club_id', 'person_identifier', 'season', 'statut' ); }
    public function prepare( $sql, ...$values ) { return array( $sql, $values ); }
    public function get_var( $prepared ) { return $this->renewed ? (int) $this->renewed->id : 0; }
    public function get_row() { return $this->renewed; }
}
$GLOBALS['wpdb'] = new Test_WPDB();
$GLOBALS['pending_order'] = false;
function ufsc_wc_find_pending_renewal_order() { return $GLOBALS['pending_order']; }
class Test_Order { public function get_id() { return 91; } public function needs_payment() { return true; } public function get_checkout_payment_url() { return 'https://example.test/order-pay/91'; } }
require dirname( __DIR__ ) . '/includes/core/class-ufsc-identifier-resolver.php';
require dirname( __DIR__ ) . '/includes/core/class-ufsc-renewal-service.php';
$source = (object) array( 'id' => 12, 'club_id' => 4, 'season' => '2025-2026', 'statut' => 'validated', 'numero_licence_ufsc' => 'UFSC-L-000012' );
$assert = static function( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$context = UFSC_Renewal_Service::season_context_status( $source, '2026-2027' );
$assert( $context['is_historical'] && 'Saison terminée' === $context['label'] && $context['renewal_allowed'], 'Historical validated licence must be renewable contextually.' );
$assert( 'Renouveler pour 2026-2027' === $context['action_label'] && 'validated' === $source->statut, 'Stored historical status must remain intact.' );
$GLOBALS['wpdb']->renewed = (object) array( 'id' => 20, 'statut' => 'validated' );
$context = UFSC_Renewal_Service::season_context_status( $source, '2026-2027' );
$assert( 'renewed' === $context['renewal_state'] && 20 === $context['renewed_licence_id'], 'Existing annual row must prevent a second renewal.' );
$GLOBALS['wpdb']->renewed = (object) array( 'id' => 21, 'statut' => 'pending_payment' );
$GLOBALS['pending_order'] = new Test_Order();
$context = UFSC_Renewal_Service::season_context_status( $source, '2026-2027' );
$assert( 'payable' === $context['renewal_state'] && 91 === $context['payable_order_id'] && false !== strpos( $context['action_url'], 'order-pay' ), 'Payable order must be resumed.' );
$GLOBALS['wpdb']->renewed = null; $GLOBALS['pending_order'] = false; $GLOBALS['gate_allowed'] = false;
$context = UFSC_Renewal_Service::season_context_status( $source, '2026-2027' );
$assert( 'blocked' === $context['renewal_state'] && ! $context['renewal_allowed'] && '' !== $context['renewal_reason'], 'Inactive affiliation must block renewal with a reason.' );
echo "Licence season context runtime safeguards passed.\n";
