<?php
$root = dirname( __DIR__ );
$php = file_get_contents( $root . '/inc/common/p0-dev-recipe-v2.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-p0-dev-recipe-v2.css' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$checks = array(
    'v2 loaded' => false !== strpos( $flags, "p0-dev-recipe-v2.php" ),
    'draft helper' => false !== strpos( $php, 'ufsc_p0v2_is_draft_status' ),
    'authoritative pack decision' => false !== strpos( $php, 'ufsc_p0_pack_decision' ),
    'legacy cart CTA fallback replaced' => false !== strpos( $php, 'Ajouter\\s+au\\s+panier' ),
    'validated connection count' => false !== strpos( $php, "validated_licences" ),
    'late shortcode filter' => false !== strpos( $php, "'do_shortcode_tag', 'ufsc_p0v2_shortcode_output', 90" ),
    'mobile compact two-column nav' => false !== strpos( $css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ),
    'very narrow single-column fallback' => false !== strpos( $css, '@media (max-width: 390px)' ),
    'profile full width' => false !== strpos( $css, '.ufsc-club-profile form' ) && false !== strpos( $css, 'max-width: none' ),
    'readable office status' => false !== strpos( $css, '.ufsc-pack-office' ),
);

foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "P0 DEV recipe v2 static guards OK\n";
