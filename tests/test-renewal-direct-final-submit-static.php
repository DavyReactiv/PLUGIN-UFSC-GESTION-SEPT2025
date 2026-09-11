<?php
$root = dirname( __DIR__ );
$guard = file_get_contents( $root . '/assets/js/ufsc-renewal-final-submit-guard.js' );
$loader = file_get_contents( $root . '/inc/common/portal-ui-cleanup.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $loader, 'ufsc-renewal-final-submit-guard.js' ), 'final renewal capture guard must be enqueued' );
$assert( false !== strpos( $guard, "document.addEventListener('click'" ) && false !== strpos( $guard, '}, true);' ), 'guard must observe the final click in capture phase' );
$assert( false !== strpos( $guard, 'event.stopImmediatePropagation()' ), 'legacy click handlers must not own the final renewal action' );
$assert( false !== strpos( $guard, "HTMLFormElement.prototype.submit.call(form)" ), 'final renewal must use native POST after front checks' );
$assert( false !== strpos( $guard, "ufsc_client_submit_trace" ) && false !== strpos( $guard, 'direct_capture_submit' ), 'direct submit must carry a privacy-safe server trace' );
$assert( false !== strpos( $guard, 'Traitement du renouvellement en cours' ), 'club user must receive immediate visible feedback' );
$assert( false !== strpos( $guard, 'data-complete' ) && false !== strpos( $guard, 'data-blocked' ), 'guard must still fail closed for incomplete or blocked dossiers' );
$assert( false === strpos( $guard, 'empty_cart' ) && false === strpos( $guard, 'remove_cart_item' ), 'front guard must not mutate unrelated cart lines' );

echo "Renewal direct final submit safeguards OK\n";
