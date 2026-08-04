<?php
/** Executable server-side compliance validation regression tests. */
define( 'ABSPATH', __DIR__ . '/' );
function __( $text ) { return $text; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function current_time( $type ) { return 'Y-m-d' === $type ? '2026-08-04' : ( 'mysql' === $type ? '2026-08-04 12:00:00' : time() ); }
class WP_Error {
    private $message;
    public function __construct( $code, $message ) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
require dirname( __DIR__ ) . '/includes/core/class-unified-handlers.php';
$method = new ReflectionMethod( 'UFSC_Unified_Handlers', 'process_licence_data' );
$method->setAccessible( true );
$base = array(
    'prenom' => 'Ada', 'nom' => 'Lovelace', 'email' => 'ada@example.test',
    'adresse' => '1 rue UFSC', 'ville' => 'Paris', 'code_postal' => '75001',
    'telephone' => '0102030405', 'sexe' => 'F', 'ufsc_submit_action' => 'add_to_cart',
);
$minor = $base + array( 'date_naissance' => '2012-03-10', 'role' => 'adherent' );
$result = $method->invoke( null, $minor );
if ( ! $result instanceof WP_Error || false === strpos( $result->get_error_message(), 'mineur' ) ) {
    fwrite( STDERR, "FAIL: minor questionnaire and representative must be validated server-side\n" ); exit( 1 );
}
$minor['health_questionnaire_confirmed'] = '1';
$minor['legal_representative_name'] = 'Grace Lovelace';
$result = $method->invoke( null, $minor );
if ( $result instanceof WP_Error ) { fwrite( STDERR, 'FAIL: valid minor compliance rejected: ' . $result->get_error_message() . "\n" ); exit( 1 ); }
$adult = $base + array( 'date_naissance' => '1990-03-10', 'role' => 'coach', 'health_questionnaire_confirmed' => '1' );
$result = $method->invoke( null, $adult );
if ( ! $result instanceof WP_Error || false === strpos( $result->get_error_message(), 'honorabilité' ) ) {
    fwrite( STDERR, "FAIL: honorability must be role-conditional and server-side\n" ); exit( 1 );
}
$adult['honorability_confirmed'] = '1';
$result = $method->invoke( null, $adult );
if ( $result instanceof WP_Error ) { fwrite( STDERR, 'FAIL: valid adult compliance rejected: ' . $result->get_error_message() . "\n" ); exit( 1 ); }
foreach ( array_keys( $result ) as $key ) {
    if ( false !== strpos( $key, 'medical_answer' ) || false !== strpos( $key, 'questionnaire_response' ) ) {
        fwrite( STDERR, "FAIL: medical answers must never be stored\n" ); exit( 1 );
    }
}
echo "Season compliance runtime safeguards OK\n";
