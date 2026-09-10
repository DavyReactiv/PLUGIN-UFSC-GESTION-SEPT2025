<?php
/**
 * P0 regression contract: a NEW licence draft may be persisted while the
 * affiliation is pending, without opening any finalisation path.
 */
define( 'ABSPATH', __DIR__ );

function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function add_action() { return true; }

require dirname( __DIR__ ) . '/inc/common/season.php';

$cases = array(
    array( 'POST', 'ufsc_save_licence', 0, 'save_draft', true,  'new licence draft through canonical save endpoint' ),
    array( 'POST', 'ufsc_add_licence',  0, 'save_draft', true,  'new licence draft through compatibility add endpoint' ),
    array( 'POST', 'ufsc_save_licence', 0, 'add_to_cart', false, 'cart finalisation remains gated' ),
    array( 'POST', 'ufsc_save_licence', 0, 'submit_for_validation', false, 'validation remains gated' ),
    array( 'POST', 'ufsc_save_licence', 42, 'save_draft', false, 'existing licences never use the new-draft bypass' ),
    array( 'GET',  'ufsc_save_licence', 0, 'save_draft', false, 'GET requests never use the bypass' ),
    array( 'POST', 'other_action',      0, 'save_draft', false, 'unrelated endpoints never use the bypass' ),
);

foreach ( $cases as $case ) {
    list( $method, $action, $licence_id, $intent, $expected, $label ) = $case;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = array(
        'action'             => $action,
        'licence_id'         => $licence_id,
        'ufsc_submit_action' => $intent,
    );

    $actual = ufsc_is_new_licence_draft_request();
    if ( $actual !== $expected ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

$source = file_get_contents( dirname( __DIR__ ) . '/inc/common/season.php' );
$helper_pos = strpos( $source, 'function ufsc_is_new_licence_draft_request()' );
$bypass_pos = strpos( $source, "'licence_draft_allowed'" );
$resolution_pos = strpos( $source, 'resolve_affiliation( $club_id, $normalized_season )' );

if ( false === $helper_pos || false === $bypass_pos || false === $resolution_pos || $bypass_pos > $resolution_pos ) {
    fwrite( STDERR, "FAIL: draft bypass must remain explicit and run before affiliation resolution.\n" );
    exit( 1 );
}

echo "Draft/affiliation P0 safeguards passed.\n";
