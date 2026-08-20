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

$assert( false !== strpos( $loader, 'ufsc-renewal-production-flow' ), 'the deterministic renewal controller must be enqueued' );
$assert( false !== strpos( $flow, 'ufsc-renewal-profile-row ufsc-renewal-profile-panel' ), 'promoted verification panels must preserve the legacy JS profile-row contract' );
$assert( false !== strpos( $flow, "panel.style.display = 'none'" ), 'unselected verification panels must be hidden explicitly despite legacy display rules' );
$assert( false !== strpos( $flow, 'Aucune licence sélectionnée.' ), 'selection summary must describe the selected set only' );
$assert( false !== strpos( $flow, 'prête(s)' ) && false !== strpos( $flow, 'à compléter' ), 'selection summary must distinguish ready from incomplete selected dossiers' );
$assert( false !== strpos( $flow, 'Rien n’est renouvelé avant votre confirmation finale.' ), 'step 1 must explain that selection does not renew immediately' );
$assert( false !== strpos( $flow, 'Le quota inclus est utilisé en priorité' ), 'final step must state the quota-first business rule' );
$assert( false !== strpos( $flow, "paid === 0 || productReady" ) || false !== strpos( $flow, "paid === 0 || productReady(w, button)" ), 'included-only renewals must not depend on WooCommerce product readiness' );
$assert( false !== strpos( $flow, 'Envoyer pour validation — inclus dans votre affiliation' ), 'included-only CTA must not present a cart action to the club' );
$assert( false !== strpos( $flow, "button[name=\"ufsc_renew_intent\"][value=\"add_to_cart\"]" ), 'the compatibility controller must keep the existing server finalisation contract' );
$assert( false !== strpos( $flow, "el.style.display = step === 1 ? '' : 'none'" ), 'filters and pagination must disappear during verification/finalisation' );

echo "Production renewal flow safeguards OK\n";
