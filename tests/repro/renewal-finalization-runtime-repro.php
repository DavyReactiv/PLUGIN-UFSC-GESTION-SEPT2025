<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

class WP_Error {
    private $code;
    private $message;
    public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }

final class UFSC_Licence_Finalization_Service {
    public static $calls = array();
    public static $next_error = false;

    public static function finalize( $licence_id, $club_id = 0, $season = '', $context = 'licence' ) {
        self::$calls[] = array( $licence_id, $club_id, $season, $context );
        if ( self::$next_error ) {
            self::$next_error = false;
            return new WP_Error( 'forced_failure', 'forced renewal finalization failure' );
        }
        return array(
            'licence_id' => $licence_id,
            'club_id'    => $club_id,
            'season'     => $season,
            'included'   => true,
            'payable'    => false,
            'bucket'     => 'libre',
            'status'     => 'en_attente',
        );
    }
}

require_once dirname( __DIR__, 2 ) . '/inc/common/licence-finalization-runtime.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action'            => 'ufsc_bulk_renew_licences',
    'ufsc_renew_intent' => 'add_to_cart',
);
$source = (object) array( 'id' => 100, 'statut' => 'valide', 'season' => '2025-2026' );
$target = array( 'licence_id' => 200, 'created' => true );
$result = UFSC_Licence_Finalization_Runtime::finalize_renewal_target( $target, $source, 7, '2026-2027', array() );

if ( empty( $result['ufsc_finalization']['included'] ) || 'en_attente' !== $result['ufsc_finalization']['status'] ) {
    fwrite( STDERR, "FAIL: renewal target did not pass through the canonical included finalization\n" );
    exit( 1 );
}
if ( array( 200, 7, '2026-2027', 'renewal' ) !== UFSC_Licence_Finalization_Service::$calls[0] ) {
    fwrite( STDERR, "FAIL: renewal finalization service received the wrong target context\n" );
    exit( 1 );
}
if ( 'valide' !== $source->statut || '2025-2026' !== $source->season ) {
    fwrite( STDERR, "FAIL: historical source row was mutated by renewal finalization\n" );
    exit( 1 );
}

UFSC_Licence_Finalization_Service::$next_error = true;
$error = UFSC_Licence_Finalization_Runtime::finalize_renewal_target(
    array( 'licence_id' => 201, 'created' => true ),
    $source,
    7,
    '2026-2027',
    array()
);
if ( ! is_wp_error( $error ) ) {
    fwrite( STDERR, "FAIL: renewal finalization errors must propagate and block the cart flow\n" );
    exit( 1 );
}

echo "OK: renewals use canonical finalization, preserve history and fail closed\n";
