<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$journey = file_get_contents( $root . '/inc/common/club-journey.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-club-journey.css' );

$checks = array(
    'v2 not loaded at runtime' => false === strpos( $flags, "p0-dev-recipe-v2.php" ),
    'canonical journey loaded' => false !== strpos( $flags, 'club-journey.php' ),
    'single authoritative decision' => false !== strpos( $journey, 'ufsc_club_journey_decision' ),
    'validated connection count' => false !== strpos( $journey, 'validated_licences' ),
    'included CTA wording' => false !== strpos( $journey, 'Envoyer pour validation' ),
    'mobile navigation contract' => false !== strpos( $css, '@media (max-width: 782px)' ),
);

foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "Legacy v2 retired; consolidated journey safeguards OK\n";
