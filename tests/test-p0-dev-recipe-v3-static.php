<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$journey = file_get_contents( $root . '/inc/common/club-journey.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-club-journey.css' );

$checks = array(
    'v3 not loaded at runtime' => false === strpos( $flags, 'p0-dev-recipe-v3.php' ),
    'consolidated journey loaded' => false !== strpos( $flags, 'club-journey.php' ),
    'payment wording consolidated' => false !== strpos( $journey, 'Vérification obligatoire avant envoi' ),
    'included decision enforced' => false !== strpos( $journey, '$decision[\'included\']' ) || false !== strpos( $journey, '$state[\'included\']' ),
    'included CTA enforced' => false !== strpos( $journey, 'Envoyer pour validation' ),
    'paid CTA enforced' => false !== strpos( $journey, 'Ajouter au panier — licence payante' ),
    'readable journey css' => false !== strpos( $css, '.ufsc-journey-decision' ),
);

foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "Legacy v3 retired; consolidated journey safeguards OK\n";
