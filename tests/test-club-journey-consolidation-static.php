<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$journey = file_get_contents( $root . '/inc/common/club-journey.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-club-journey.css' );
$fail = static function ( $message ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); };

foreach ( array( 'p0-quota-cart-kpi.php', 'p0-quota-ui.php', 'p0-dev-recipe-v2.php', 'p0-paid-cart-handoff.php', 'p0-dev-recipe-v3.php' ) as $legacy ) {
    false === strpos( $flags, "require_once" . ' $' . $legacy ) || $fail( "legacy runtime layer still loaded: {$legacy}" );
    false === strpos( $flags, "'/{$legacy}'" ) || $fail( "legacy runtime file still referenced: {$legacy}" );
}
strpos( $flags, "club-journey.php" ) !== false || $fail( 'consolidated journey not loaded' );
strpos( $journey, 'ufsc_journey_pack_state' ) !== false || $fail( 'central quota decision missing' );
strpos( $journey, 'ufsc_allocate_pack_credit' ) !== false || $fail( 'server-side pack allocation missing' );
strpos( $journey, 'ufsc_handle_add_to_cart_secure' ) !== false || $fail( 'canonical Woo cart handoff missing' );
strpos( $journey, "validated_licences" ) !== false || $fail( 'validated-only connected count missing' );
strpos( $journey, 'Votre club dispose déjà de son espace UFSC' ) !== false || $fail( 'premium existing-club message missing' );
strpos( $journey, 'Information de règlement non disponible dans l’historique' ) !== false || $fail( 'explicit unknown payment trace missing' );
strpos( $journey, 'Validation : UFSC' ) !== false || $fail( 'public validator label must be UFSC' );
strpos( $journey, 'Envoyer pour validation — inclus dans votre affiliation' ) !== false || $fail( 'included CTA missing' );
strpos( $journey, 'Ajouter au panier — licence payante' ) !== false || $fail( 'paid CTA missing' );
strpos( $css, '.ufsc-existing-club-card' ) !== false || $fail( 'premium affiliation UI missing' );

echo "Club journey consolidation guards OK\n";
