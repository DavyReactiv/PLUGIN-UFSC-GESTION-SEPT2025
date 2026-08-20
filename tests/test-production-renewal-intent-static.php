<?php
$root = dirname( __DIR__ );
$js = file_get_contents( $root . '/assets/js/ufsc-renewal-production-flow.js' );
$compat = file_get_contents( $root . '/inc/common/renewal-intent-compat.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $js, 'ufsc_renew_intent_fallback' ), 'renewal UX must send a dedicated intent fallback field' );
$assert( false !== strpos( $js, "intent = 'add_to_cart'" ), 'step 3 keyboard submit must resolve to add_to_cart' );
$assert( false !== strpos( $js, "addEventListener('submit'" ), 'renewal UX must capture submit before POST serialization' );
$assert( false !== strpos( $compat, "'ufsc_bulk_renew_licences' !== \$action" ), 'compat bridge must be scoped to the renewal admin-post action' );
$assert( false !== strpos( $compat, "\$_POST['ufsc_renew_intent'] = \$resolved" ), 'server must restore the canonical renewal intent before the handler' );
$assert( false !== strpos( $compat, "'fallback' => \$fallback" ), 'debug output must expose the privacy-safe fallback/resolved intent' );
$assert( false !== strpos( $flags, "'/renewal-intent-compat.php'" ), 'runtime composition must load renewal intent compatibility before finalization' );

echo "Production renewal intent fallback safeguards OK\n";
