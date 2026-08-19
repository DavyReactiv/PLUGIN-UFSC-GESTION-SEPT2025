<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }

require_once dirname( __DIR__, 2 ) . '/inc/common/licence-finalization-runtime.php';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
    'action'             => 'ufsc_save_licence',
    'ufsc_submit_action' => 'submit_for_validation',
);

UFSC_Licence_Finalization_Runtime::normalize_final_intent_post();

if ( 'add_to_cart' !== ( $_POST['ufsc_submit_action'] ?? '' ) ) {
    fwrite( STDERR, "FAIL: submit_for_validation was not normalized to the canonical finalisation trigger\n" );
    exit( 1 );
}
if ( 'submit_for_validation' !== ( $_POST['ufsc_final_intent'] ?? '' ) ) {
    fwrite( STDERR, "FAIL: original finalisation intent was not preserved\n" );
    exit( 1 );
}

echo "OK: submit_for_validation reaches the canonical finalisation path\n";
