<?php
$root = dirname( __DIR__ );
$journey = file_get_contents( $root . '/inc/common/club-journey.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-club-journey.css' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $journey, "'auto_consume_included'] = 1" ), 'included pack consumption is authoritative' );
$assert( false !== strpos( $journey, "'en_attente'" ), 'included finalisation moves licence out of draft' );
$assert( false !== strpos( $journey, 'ufsc_journey_record_submission' ), 'submission audit is persisted' );
$assert( false !== strpos( $journey, 'licence envoyée par un club est à valider' ), 'admin receives explicit pending-validation notice' );
$assert( false !== strpos( $journey, 'Renouveler — inclus dans votre affiliation' ), 'historical renewal CTA explains included quota' );
$assert( false !== strpos( $journey, 'Finaliser — quota inclus en priorité' ), 'renewal wizard uses quota-first final CTA' );
$assert( false !== strpos( $journey, 'ufsc_journey_replace_detail_cart_form' ), 'historical detail cart form is replaced by renewal journey' );
$assert( false !== strpos( $cart, 'ufsc_allocate_pack_credit' ), 'renewal backend allocates pack before paid cart path' );
$assert( false !== strpos( $cart, "if ( ! empty( \$allocation['included'] ) )" ), 'renewal included branch is explicit' );
$assert( false !== strpos( $css, 'max-width: 1180px' ), 'detail/profile width hardening exists' );
$assert( false !== strpos( $css, '@media (max-width: 600px)' ), 'mobile responsive contract exists' );
$assert( false !== strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ), 'mobile account navigation is compact' );

echo "Licence submission/renewal status static safeguards OK\n";
