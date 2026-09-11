<?php
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/ufsc-renewal-production-flow.js' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $js, 'function canFinalSubmit' ), 'final submit eligibility must be owned by the production renewal controller' );
$assert( false !== strpos( $js, 'function enforceFinalButton' ), 'final button state must be repaired after legacy scripts mutate it' );
$assert( false !== strpos( $js, "attributeFilter:['disabled','aria-disabled','data-ufsc-product-ready']" ), 'final button mutations must be observed' );
$assert( false !== strpos( $js, 'function prepareNativeFinalSubmit' ), 'final step must prepare the canonical browser-native form POST' );
$assert( false !== strpos( $js, "submitTrace(f, reason || 'native_html_submit')" ), 'native final submit must carry an observable client trace' );
$assert( false !== strpos( $js, 'f.noValidate = true' ), 'final validated wizard step may bypass stale hidden-field browser validation' );
$assert( false === strpos( $js, 'HTMLFormElement.prototype.submit.call(f)' ), 'JavaScript must not replace the browser-native form POST' );
$assert( false === strpos( $js, 'native_validation_blocked' ), 'legacy validation-resubmit fallback must stay retired' );
$assert( false !== strpos( $js, 'var canSubmit = selected.length > 0 && c.ready === selected.length && c.blocked === 0;' ), 'client product-ready flag must not silently block the final submit' );
$assert( false !== strpos( $js, 'La disponibilité du produit Licence UFSC sera vérifiée par le serveur à la confirmation.' ), 'product availability must defer to server validation with visible copy' );

echo "Renewal final button authority safeguards OK\n";
