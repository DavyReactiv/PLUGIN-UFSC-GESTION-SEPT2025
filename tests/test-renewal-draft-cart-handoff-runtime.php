<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }

function add_action() {}
function add_filter() {}
function sanitize_text_field( $value ) { return (string) $value; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }

require dirname( __DIR__ ) . '/inc/common/renewal-draft-cart-handoff.php';

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$row = (object) array( 'previous_licence_id' => 101, 'renewed_from_licence_id' => 202 );
$assert( 101 === ufsc_renewal_draft_cart_source_id( $row ), 'previous_licence_id is the canonical source when present' );
$row = (object) array( 'previous_licence_id' => 0, 'renewed_from_licence_id' => 202 );
$assert( 202 === ufsc_renewal_draft_cart_source_id( $row ), 'renewed_from_licence_id remains a compatible source fallback' );
$row = (object) array();
$assert( 0 === ufsc_renewal_draft_cart_source_id( $row ), 'ordinary licence rows are not classified as renewal targets' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'action' => 'ufsc_save_licence', 'ufsc_submit_action' => 'add_to_cart' );
$assert( true === ufsc_renewal_draft_cart_is_final_request(), 'save licence + add_to_cart is a final unified request' );

$_POST = array( 'action' => 'ufsc_update_licence', 'ufsc_submit_action' => 'continue', 'ufsc_final_intent' => 'submit_for_validation' );
$assert( true === ufsc_renewal_draft_cart_is_final_request(), 'normalized submit_for_validation remains a final request' );

$_POST = array( 'action' => 'ufsc_save_licence', 'ufsc_submit_action' => 'save_draft' );
$assert( false === ufsc_renewal_draft_cart_is_final_request(), 'saving a draft never touches the Woo cart' );

$_POST = array( 'action' => 'ufsc_bulk_renew_licences', 'ufsc_submit_action' => 'add_to_cart' );
$assert( false === ufsc_renewal_draft_cart_is_final_request(), 'bulk renewal remains owned by its dedicated controller' );
