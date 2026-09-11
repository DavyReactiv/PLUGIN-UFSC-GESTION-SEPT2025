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
$assert( false !== strpos( $js, 'function prepareNativeFinalSubmit' ), 'final renewal must expose one native HTML submit preparation path' );
$assert( false === strpos( $js, 'HTMLFormElement.prototype.submit.call(f)' ), 'browser native form submission must no longer be replaced by JavaScript' );
$assert( false !== strpos( $js, "rememberIntent(f, 'add_to_cart')" ), 'final preparation must preserve the canonical add_to_cart fallback intent' );
$assert( false !== strpos( $js, "prepareNativeFinalSubmit(f, 'native_click_submit')" ), 'the final click must prepare the browser native POST' );
$assert( false !== strpos( $js, "prepareNativeFinalSubmit(f, 'native_submit_event')" ), 'keyboard/programmatic submit must reuse the same preparation path' );
$assert( false !== strpos( $js, "stepNumber(f) !== 3" ), 'native final preparation must remain scoped to final step 3' );
$assert( false === strpos( $js, 'e.stopImmediatePropagation()' ), 'final click must not suppress competing browser event propagation' );
$assert( false === strpos( $js, 'empty_cart(' ), 'front recovery must never empty the WooCommerce cart' );

echo "Renewal final native HTML submit safeguards OK\n";
