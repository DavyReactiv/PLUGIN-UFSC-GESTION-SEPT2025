<?php
$root = dirname( __DIR__ );
$flow = file_get_contents( $root . '/assets/js/ufsc-renewal-production-flow.js' );
$loader = file_get_contents( $root . '/inc/common/portal-ui-cleanup.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'ufsc-renewal-production-flow.js' ), 'canonical renewal controller must remain enqueued' );
$assert( false === strpos( $loader, 'ufsc-renewal-final-submit-guard.js' ), 'no duplicate renewal submit guard may be enqueued' );
$assert( false !== strpos( $flow, 'function prepareNativeFinalSubmit' ), 'canonical controller prepares one native HTML submit contract' );
$assert( false !== strpos( $flow, "rememberIntent(f, 'add_to_cart')" ), 'final step must persist the canonical fallback intent before native submit' );
$assert( false !== strpos( $flow, "submitTrace(f, reason || 'native_html_submit')" ), 'final submit must remain observable when it reaches the server' );
$assert( false === strpos( $flow, 'HTMLFormElement.prototype.submit.call(f)' ), 'controller must not replace the browser native submit anymore' );
$assert( false === strpos( $flow, 'e.stopImmediatePropagation()' ), 'controller must not swallow the native click event' );
$assert( false !== strpos( $flow, 'Traitement du renouvellement en cours' ), 'club user must receive immediate visible feedback' );
$assert( false !== strpos( $flow, 'data-complete' ) && false !== strpos( $flow, 'data-blocked' ), 'final submit must still fail closed for incomplete or blocked dossiers' );
$assert( false === strpos( $flow, 'empty_cart(' ) && false === strpos( $flow, 'remove_cart_item' ), 'front controller must not mutate unrelated cart lines' );

echo "Renewal native HTML final submit safeguards OK\n";
