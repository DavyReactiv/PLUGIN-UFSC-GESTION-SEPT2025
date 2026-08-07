<?php
/** Runtime contracts for six-step drafts and former licence numbers. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
require dirname( __DIR__ ) . '/includes/core/class-unified-handlers.php';
foreach ( range( 1, 6 ) as $step ) {
    if ( UFSC_Unified_Handlers::normalize_licence_intent( 'save_draft' ) !== 'save_draft' || UFSC_Unified_Handlers::should_add_licence_to_cart( 'save_draft' ) ) { fwrite( STDERR, "FAIL draft step {$step}\n" ); exit( 1 ); }
}
$intents = array( 'continue' => false, 'verify' => false, 'add_to_cart' => true, 'forged' => false );
foreach ( $intents as $intent => $cart ) { if ( UFSC_Unified_Handlers::should_add_licence_to_cart( $intent ) !== $cart ) { fwrite( STDERR, "FAIL {$intent}\n" ); exit( 1 ); } }
$numbers = array( array( true, 'ab12', 'AB12' ), array( false, 'OLD123', '' ), array( true, '1234567890', '1234567890' ), array( true, '12345678901', false ), array( true, 'AB 12', false ), array( true, 'AB-12', false ), array( true, '', false ) );
foreach ( $numbers as $case ) { if ( UFSC_Unified_Handlers::normalize_previous_licence_number( $case[0], $case[1] ) !== $case[2] ) { fwrite( STDERR, "FAIL previous number {$case[1]}\n" ); exit( 1 ); } }
echo "Six-step draft and previous-number runtime safeguards passed.\n";
