<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

function sanitize_key( $value ) {
    return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

require_once dirname( __DIR__, 2 ) . '/includes/core/class-unified-handlers.php';

$normalized = UFSC_Unified_Handlers::normalize_licence_intent( 'submit_for_validation' );
if ( 'submit_for_validation' !== $normalized ) {
    fwrite( STDERR, "REPRODUCED: submit_for_validation was normalized to {$normalized}\n" );
    exit( 2 );
}

if ( UFSC_Unified_Handlers::should_add_licence_to_cart( 'submit_for_validation' ) ) {
    fwrite( STDERR, "FAIL: submit_for_validation must not directly authorize a paid cart line\n" );
    exit( 1 );
}

echo "OK: submit_for_validation remains a first-class canonical intent\n";
