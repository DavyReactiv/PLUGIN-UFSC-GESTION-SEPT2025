<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

function __( $text ) { return $text; }
function apply_filters( $hook, $value ) { return $value; }
function remove_accents( $value ) { return $value; }
function sanitize_title( $value ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( (string) $value ) ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }

require_once dirname( __DIR__, 2 ) . '/inc/common/compliance.php';

for ( $used = 0; $used < 10; $used++ ) {
    $roles = array_fill( 0, $used, 'adherent' );
    $decision = ufsc_resolve_pack_credit( 'adherent', $roles );
    if ( empty( $decision['included'] ) ) {
        fwrite( STDERR, "FAIL: licence " . ( $used + 1 ) . " should be included\n" );
        exit( 1 );
    }
}

$decision = ufsc_resolve_pack_credit( 'adherent', array_fill( 0, 10, 'adherent' ) );
if ( ! empty( $decision['included'] ) || 'payante' !== ( $decision['bucket'] ?? '' ) ) {
    fwrite( STDERR, "FAIL: licence 11 should be payable\n" );
    exit( 1 );
}

echo "OK: licences 1-10 included, licence 11 payable\n";
