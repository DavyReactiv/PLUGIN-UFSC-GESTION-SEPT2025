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
$assert( false !== strpos( $flow, "document.addEventListener('click'" ) && false !== strpos( $flow, 'e.stopImmediatePropagation()' ), 'canonical controller must own the final click in capture phase' );
$assert( false !== strpos( $flow, "nativeFinalSubmit(f, 'direct_capture_submit')" ), 'final click must use the canonical native submit path' );
$assert( false !== strpos( $flow, 'HTMLFormElement.prototype.submit.call(f)' ), 'final renewal must issue a native POST after front checks' );
$assert( false !== strpos( $flow, 'Traitement du renouvellement en cours' ), 'club user must receive immediate visible feedback' );
$assert( false !== strpos( $flow, 'data-complete' ) && false !== strpos( $flow, 'data-blocked' ), 'final submit must still fail closed for incomplete or blocked dossiers' );
$assert( false === strpos( $flow, 'empty_cart(' ) && false === strpos( $flow, 'remove_cart_item' ), 'front controller must not mutate unrelated cart lines' );

echo "Renewal direct final submit safeguards OK\n";
