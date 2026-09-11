<?php
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/ufsc-renewal-production-flow.js' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $js, 'function profileComplete' ), 'final step must recompute completeness from the selected profile DOM' );
$assert( false !== strpos( $js, "row.setAttribute('data-complete', '1')" ), 'a freshly completed profile must refresh its source-row state' );
$assert( false !== strpos( $js, 'function nativeFinalSubmit' ), 'final renewal must expose one native submit path' );
$assert( false !== strpos( $js, 'HTMLFormElement.prototype.submit.call(f)' ), 'legacy preventDefault must not be able to permanently swallow a valid final POST' );
$assert( false !== strpos( $js, "ensureCanonicalIntent(f, 'add_to_cart'" ), 'native finalizer must post the canonical add_to_cart intent' );
$assert( false !== strpos( $js, "nativeFinalSubmit(f, 'direct_capture_submit')" ), 'the final click must go directly through the canonical native finalizer' );
$assert( false !== strpos( $js, "nativeFinalSubmit(f, 'submit_event')" ), 'keyboard/programmatic submit must reuse the same native finalizer' );
$assert( false !== strpos( $js, "stepNumber(f) !== 3" ), 'native finalizer must remain scoped to final step 3' );
$assert( false === strpos( $js, 'empty_cart(' ), 'front recovery must never empty the WooCommerce cart' );

echo "Renewal final submit safeguards OK\n";
